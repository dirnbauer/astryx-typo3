<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\Desiderio\Data\ContentBlockDefinitionRegistry;
use Webconsulting\Desiderio\Library\ElementCatalog;
use Webconsulting\Desiderio\Seeding\CollectionCleanupService;
use Webconsulting\Desiderio\Seeding\ContentBlockCollectionMap;
use Webconsulting\Desiderio\Seeding\ContentElementSeeder;
use Webconsulting\Desiderio\Seeding\DesiderioContentCleaner;
use Webconsulting\Desiderio\Seeding\StyleguideCollectionAliasPolicy;
use Webconsulting\Desiderio\Seeding\StyleguideDemoValueGenerator;
use Webconsulting\Desiderio\Seeding\StyleguideFixtureResolver;
use Webconsulting\Desiderio\Seeding\DatabaseSchemaHelper;
use Webconsulting\Desiderio\Seeding\LiveWorkspaceQueryHelper;
use Webconsulting\Desiderio\Seeding\SeedPageUpserter;
use Webconsulting\DesiderioGrande\Data\GrandeSiteDefinitions;

/**
 * Build (or bring up to date) the page tree of the Astryx showcase site.
 *
 * Idempotent: pages are matched by parent plus title-or-slug, so running this
 * again after adding a chapter adds that chapter and leaves everything else,
 * including any content an editor changed in the backend, where it is.
 *
 * The tree only — the content elements that fill the chapter pages are seeded
 * separately once they exist, and the site configuration YAML is written by
 * hand from the root uid this command prints.
 */
#[AsCommand(
    name: 'desiderio-grande:site:seed',
    description: 'Create or update the Astryx showcase site page tree.'
)]
final class SeedGrandeSiteCommand extends Command
{
    /** Space between sorting values, so a page can be moved between two others. */
    private const SORTING_STEP = 256;

    /** fileadmin subfolder the chapter pages' demo images land in. */
    private const FAL_FOLDER = 'desiderio-grande-site';

    private ?DesiderioContentCleaner $contentCleaner = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
        private readonly DatabaseSchemaHelper $databaseSchema,
        private readonly LiveWorkspaceQueryHelper $liveWorkspaceQueryHelper,
        private readonly StorageRepository $storageRepository,
        private readonly ElementCatalog $elementCatalog,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Page uid the site root is created below. Use 0 for the page tree root.', '0')
            ->addOption('content', null, InputOption::VALUE_NONE, 'Also fill each chapter page with its group\'s elements, from their fixture.json. Replaces the content this command seeded before; anything an editor added by hand stays.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would be created or updated and change nothing.')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Run even when the application context is Production.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Seeding writes live records directly, so a workspace context would
        // silently produce rows nobody can publish.
        $workspaceAspectId = $this->context->getPropertyFromAspect('workspace', 'id', 0);
        $workspaceId = is_numeric($workspaceAspectId) ? (int)$workspaceAspectId : 0;
        if ($workspaceId !== 0) {
            $io->error(sprintf('Refusing to seed inside workspace #%d. Switch to the live workspace first.', $workspaceId));
            return self::FAILURE;
        }
        if (!(bool)$input->getOption('allow-production') && Environment::getContext()->isProduction()) {
            $io->error('Refusing to run in Production application context. Pass --allow-production on a sandbox only.');
            return self::FAILURE;
        }

        $parentOption = $input->getOption('parent');
        $parentPid = is_numeric($parentOption) ? (int)$parentOption : 0;
        $dryRun = (bool)$input->getOption('dry-run');

        $chapters = GrandeSiteDefinitions::chapters();
        $supportPages = GrandeSiteDefinitions::supportPages();

        if ($dryRun) {
            $io->section('Would seed');
            $io->listing([
                sprintf('Site root "%s" below page %d', GrandeSiteDefinitions::ROOT_TITLE, $parentPid),
                sprintf('%d chapter pages below the Components hub', count($chapters)),
                sprintf('%d support pages (hub, legal, error)', count($supportPages)),
            ]);
            return self::SUCCESS;
        }

        $now = time();
        $pageColumns = $this->databaseSchema->getColumnNames('pages');
        $upserter = new SeedPageUpserter($this->connectionPool, $this->databaseSchema, $this->liveWorkspaceQueryHelper);

        // ------------------------------------------------------------ root
        // Deliberately NOT the shared upsert: that matches on title OR slug,
        // and every site root in an installation has the slug "/" at page-tree
        // level. Matching a root by slug would adopt — and overwrite — whatever
        // other site happens to sit next to this one. Title alone identifies it.
        $rootAttributes = [
            'is_siteroot' => 1,
            'backend_layout' => GrandeSiteDefinitions::LAYOUT_STARTPAGE,
            'backend_layout_next_level' => GrandeSiteDefinitions::LAYOUT_CONTENTPAGE,
            'abstract' => 'The Astryx design system, server-rendered for TYPO3.',
        ];
        $existingRootUid = $this->findRootPageUid($parentPid, GrandeSiteDefinitions::ROOT_TITLE);
        if ($existingRootUid !== null) {
            $upserter->update(
                $existingRootUid,
                GrandeSiteDefinitions::ROOT_TITLE,
                GrandeSiteDefinitions::ROOT_SLUG,
                self::SORTING_STEP,
                $now,
                $pageColumns,
                $rootAttributes,
            );
            $rootUid = $existingRootUid;
        } else {
            $rootUid = $upserter->create(
                $parentPid,
                GrandeSiteDefinitions::ROOT_TITLE,
                GrandeSiteDefinitions::ROOT_SLUG,
                self::SORTING_STEP,
                $now,
                $pageColumns,
                $rootAttributes,
            );
        }

        $created = [];
        $supportUids = [];
        $sorting = self::SORTING_STEP;
        $hubUid = 0;
        $legalUids = [];
        $errorUid = 0;
        $searchUid = 0;

        // -------------------------------------------------- support pages
        foreach ($supportPages as $page) {
            $sorting += self::SORTING_STEP;
            $uid = $this->upsertPage(
                $upserter,
                $rootUid,
                $page['title'],
                '/' . $page['slug'],
                $sorting,
                $now,
                $pageColumns,
                [
                    'backend_layout' => $page['layout'],
                    'backend_layout_next_level' => GrandeSiteDefinitions::LAYOUT_CONTENTPAGE,
                    'abstract' => $page['abstract'],
                    'description' => $page['abstract'],
                    'nav_hide' => $page['navHide'] ? 1 : 0,
                    'no_index' => $page['noIndex'] ? 1 : 0,
                ],
            );
            $created[$page['title']] = $uid;
            $supportUids[$page['slug']] = $uid;

            if ($page['role'] === 'hub') {
                $hubUid = $uid;
            } elseif ($page['role'] === 'legal') {
                $legalUids[] = $uid;
            } elseif ($page['role'] === 'error') {
                $errorUid = $uid;
            } elseif ($page['role'] === 'search') {
                $searchUid = $uid;
            }
        }

        // ------------------------------------------------------- chapters
        $chapterSorting = self::SORTING_STEP;
        foreach ($chapters as $chapter) {
            $chapterSorting += self::SORTING_STEP;
            $uid = $this->upsertPage(
                $upserter,
                $hubUid,
                $chapter['title'],
                '/components/' . $chapter['slug'],
                $chapterSorting,
                $now,
                $pageColumns,
                [
                    'backend_layout' => GrandeSiteDefinitions::LAYOUT_CONTENTPAGE,
                    'abstract' => $chapter['abstract'],
                    'description' => $chapter['abstract'],
                    // Each chapter wears a different theme, so walking the hub
                    // shows what switching one actually does.
                    'tx_desideriogrande_theme' => $chapter['theme'],
                ],
            );
            $created[$chapter['title']] = $uid;
        }

        // ---------------------------------------------- theme detail pages
        $themesUid = $created['Themes'] ?? 0;
        $themePages = [];
        if ($themesUid > 0) {
            $themeSorting = self::SORTING_STEP;
            foreach (GrandeSiteDefinitions::themes() as $theme) {
                $id = is_string($theme['id'] ?? null) ? $theme['id'] : '';
                if ($id === '') {
                    continue;
                }
                $themeSorting += self::SORTING_STEP;
                $themePages[$id] = $this->upsertPage(
                    $upserter,
                    $themesUid,
                    (string)($theme['name'] ?? $id),
                    '/themes/' . $id,
                    $themeSorting,
                    $now,
                    $pageColumns,
                    [
                        'backend_layout' => GrandeSiteDefinitions::LAYOUT_CONTENTPAGE,
                        'abstract' => (string)($theme['character'] ?? ''),
                        'description' => (string)($theme['character'] ?? ''),
                        'nav_hide' => 1,
                        // The whole point of the page: it wears the theme it is about.
                        'tx_desideriogrande_theme' => $id,
                    ],
                );
            }
        }

        if ((bool)$input->getOption('content')) {
            $this->seedChapterContent($io, $created, $chapters, $now);
            $this->seedHomeContent($io, $rootUid, $hubUid, $created['Themes'] ?? 0, $now);
            $this->seedSupportContent($io, $supportUids, $now);
            $this->seedThemePages($io, $themePages, $now);
            $this->seedSearchContent($io, $searchUid, $now);
        }

        $io->success(sprintf('Astryx site tree seeded — root page uid %d.', $rootUid));

        $io->section('Next: write config/sites/desiderio-grande/');
        $io->listing([
            sprintf('rootPageId: %d', $rootUid),
            sprintf('errorHandling 404 target: t3://page?uid=%d', $errorUid),
            sprintf('desiderioGrande.footer.legalPageIds: \'%s\'', implode(',', $legalUids)),
            sprintf('desiderioGrande.search.targetPageId: \'%d\' (and search.enabled: true)', $searchUid),
            'The search page needs the webconsulting/desiderio-grande-search set '
                . 'and a Solr connection — see the Search section of the README.',
        ]);

        $rows = [];
        foreach ($created as $title => $uid) {
            $rows[] = [$uid, $title];
        }
        $io->table(['uid', 'page'], $rows);

        return self::SUCCESS;
    }

    /**
     * Put every element of a group onto its chapter page, in matrix order.
     *
     * The copy comes from each element's own fixture.json, which describes what
     * the element is — that is what makes the chapter a catalog rather than a
     * demo of one fictional company.
     *
     * @param array<string, int> $pages title => uid
     * @param list<array{group: string, title: string}> $chapters
     */
    private function seedChapterContent(SymfonyStyle $io, array $pages, array $chapters, int $now): void
    {
        $manifestPath = GeneralUtility::getFileAbsFileName(
            'EXT:desiderio_grande/Resources/Private/Data/grande-content-groups.json',
        );
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest['groups'] ?? null)) {
            $io->warning('No group manifest — run Build/Scripts/scaffold-content-elements.php --derive.');
            return;
        }

        $byGroup = [];
        foreach ($manifest['groups'] as $entry) {
            $byGroup[$entry['group']] = $entry['elements'] ?? [];
        }

        $catalog = [];
        foreach ($this->elementCatalog->getElements() as $element) {
            $catalog[$element['cType']] = $element;
        }

        $resolver = new StyleguideFixtureResolver(
            $this->databaseSchema,
            new StyleguideDemoValueGenerator(),
            new StyleguideCollectionAliasPolicy($this->databaseSchema),
        );
        $seeder = new ContentElementSeeder(
            $this->connectionPool,
            $this->storageRepository,
            $this->databaseSchema,
            self::FAL_FOLDER,
            1777300001,
        );
        $cleaner = $this->getContentCleaner();
        $columns = $this->databaseSchema->getColumnNames('tt_content');

        $seeded = 0;
        foreach ($chapters as $chapter) {
            $pageUid = $pages[$chapter['title']] ?? 0;
            $elements = $byGroup[$chapter['group']] ?? [];
            if ($pageUid === 0 || $elements === []) {
                continue;
            }

            // Only what a previous run put here: an editor's own additions on a
            // chapter page survive a reseed.
            $cleaner->softDeleteSeededContent($pageUid, $now);

            $sorting = 0;
            foreach ($elements as $element) {
                $cType = $element['ctype'];
                $record = $catalog[$cType] ?? null;
                if ($record === null) {
                    continue;
                }
                $sorting += self::SORTING_STEP;
                $contentData = $resolver->buildContentInsert(
                    $pageUid,
                    $cType,
                    $record['name'],
                    $record['fixture'],
                    $sorting,
                    $now,
                    $columns,
                    ContentBlockDefinitionRegistry::buildDefinitionFromConfig($record['config']),
                );
                $seeder->insert($pageUid, $now, $contentData);
                $seeded++;
            }

            $io->writeln(sprintf('  %-28s %d elements', $chapter['title'], count($elements)));
        }

        $io->writeln(sprintf("\n%d elements placed across %d chapter pages.", $seeded, count($chapters)));
    }

    /**
     * The home page: a marketing page assembled from the catalog's own elements,
     * which is the only honest way to sell them.
     */
    private function seedHomeContent(SymfonyStyle $io, int $rootUid, int $hubUid, int $themesUid, int $now): void
    {
        $resolver = new StyleguideFixtureResolver(
            $this->databaseSchema,
            new StyleguideDemoValueGenerator(),
            new StyleguideCollectionAliasPolicy($this->databaseSchema),
        );
        $seeder = new ContentElementSeeder(
            $this->connectionPool,
            $this->storageRepository,
            $this->databaseSchema,
            self::FAL_FOLDER,
            1777300002,
        );
        $columns = $this->databaseSchema->getColumnNames('tt_content');

        $catalog = [];
        foreach ($this->elementCatalog->getElements() as $element) {
            $catalog[$element['cType']] = $element;
        }

        $this->getContentCleaner()->softDeleteSeededContent($rootUid, $now);

        $sorting = 0;
        $placed = 0;
        foreach (GrandeSiteDefinitions::homeContent() as $block) {
            $record = $catalog[$block['ctype']] ?? null;
            if ($record === null) {
                $io->warning(sprintf('Home page references %s, which is not in the catalog.', $block['ctype']));
                continue;
            }

            // The links are written against page roles, not uids, because the
            // uids only exist once this run has created the pages.
            $fixture = json_decode(strtr(json_encode($block['fixture']), [
                '__HUB__' => (string)$hubUid,
                '__THEMES__' => (string)($themesUid ?: $hubUid),
            ]), true);

            $sorting += self::SORTING_STEP;
            $seeder->insert($rootUid, $now, $resolver->buildContentInsert(
                $rootUid,
                $block['ctype'],
                $record['name'],
                $fixture,
                $sorting,
                $now,
                $columns,
                ContentBlockDefinitionRegistry::buildDefinitionFromConfig($record['config']),
            ));
            $placed++;
        }

        $io->writeln(sprintf('  %-28s %d elements', 'Home', $placed));
    }

    /**
     * Fill the legal and error pages with their prose.
     *
     * One Rich Text element per page, from GrandeSiteDefinitions::supportContent().
     * Without this pass the imprint, privacy, accessibility and 404 pages render
     * their title over an empty band.
     *
     * @param array<string, int> $supportUids page uid keyed by slug
     */
    private function seedSupportContent(SymfonyStyle $io, array $supportUids, int $now): void
    {
        $resolver = new StyleguideFixtureResolver(
            $this->databaseSchema,
            new StyleguideDemoValueGenerator(),
            new StyleguideCollectionAliasPolicy($this->databaseSchema),
        );
        $seeder = new ContentElementSeeder(
            $this->connectionPool,
            $this->storageRepository,
            $this->databaseSchema,
            self::FAL_FOLDER,
            1777300003,
        );
        $columns = $this->databaseSchema->getColumnNames('tt_content');

        $catalog = [];
        foreach ($this->elementCatalog->getElements() as $element) {
            $catalog[$element['cType']] = $element;
        }

        $cType = 'desiderio_grande_richtext';
        $record = $catalog[$cType] ?? null;
        if ($record === null) {
            $io->warning(sprintf('Support pages need %s, which is not in the catalog.', $cType));
            return;
        }
        $definition = ContentBlockDefinitionRegistry::buildDefinitionFromConfig($record['config']);

        $placed = 0;
        foreach (GrandeSiteDefinitions::supportContent() as $slug => $copy) {
            $pageUid = $supportUids[$slug] ?? 0;
            if ($pageUid === 0) {
                $io->warning(sprintf('No page for support content "%s".', $slug));
                continue;
            }

            $this->getContentCleaner()->softDeleteSeededContent($pageUid, $now);

            $seeder->insert($pageUid, $now, $resolver->buildContentInsert(
                $pageUid,
                $cType,
                $record['name'],
                [
                    // The page title is already the one visible h1, so these
                    // headings are written to add something rather than repeat
                    // it. An empty value is not an option: the fixture resolver
                    // treats empty as unspecified and invents a demo heading.
                    'header' => $copy['header'],
                    'bodytext' => $copy['bodytext'],
                    'width' => 'md',
                    'align' => 'start',
                    'tone' => 'body',
                ],
                self::SORTING_STEP,
                $now,
                $columns,
                $definition,
            ));
            $placed++;
        }

        $io->writeln(sprintf('  %-28s %d pages', 'Legal and error pages', $placed));
    }

    /**
     * The search page: one lead paragraph, then EXT:solr's results plugin.
     *
     * The plugin is not a Content Block, so it cannot go through the fixture
     * resolver the rest of the seeder uses — it is a plain tt_content row. It
     * is still cleaned up on re-seed, because the cleaner is told about its
     * CType explicitly; without that the page would gain a second plugin on
     * every run.
     *
     * The page is seeded whether or not Solr is configured. An unconfigured
     * plugin renders EXT:solr's own "search unavailable" notice, which this
     * theme skins — and a page that exists is what makes the site setting
     * `desiderioGrande.search.targetPageId` something an editor can point at.
     */
    private function seedSearchContent(SymfonyStyle $io, int $searchUid, int $now): void
    {
        if ($searchUid === 0) {
            $io->warning('No search page was created, so its content was skipped.');
            return;
        }

        $resolver = new StyleguideFixtureResolver(
            $this->databaseSchema,
            new StyleguideDemoValueGenerator(),
            new StyleguideCollectionAliasPolicy($this->databaseSchema),
        );
        $seeder = new ContentElementSeeder(
            $this->connectionPool,
            $this->storageRepository,
            $this->databaseSchema,
            self::FAL_FOLDER,
            1777300004,
        );
        $columns = $this->databaseSchema->getColumnNames('tt_content');

        $this->getContentCleaner()->softDeleteSeededContent(
            $searchUid,
            $now,
            [GrandeSiteDefinitions::SEARCH_PLUGIN_CTYPE],
        );

        $cType = 'desiderio_grande_leadparagraph';
        $record = null;
        foreach ($this->elementCatalog->getElements() as $element) {
            if ($element['cType'] === $cType) {
                $record = $element;
                break;
            }
        }

        if ($record === null) {
            $io->warning(sprintf('The search page needs %s, which is not in the catalog.', $cType));
        } else {
            $seeder->insert($searchUid, $now, $resolver->buildContentInsert(
                $searchUid,
                $cType,
                $record['name'],
                [
                    // No header: the page title is already the visible h1, and
                    // a second "Search" above the field would only repeat it.
                    'lead' => GrandeSiteDefinitions::SEARCH_LEAD,
                    'width' => 'lg',
                    'align' => 'start',
                ],
                self::SORTING_STEP,
                $now,
                $columns,
                ContentBlockDefinitionRegistry::buildDefinitionFromConfig($record['config']),
            ));
        }

        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        $row = [
            'pid' => $searchUid,
            'CType' => GrandeSiteDefinitions::SEARCH_PLUGIN_CTYPE,
            'colPos' => 0,
            'sorting' => self::SORTING_STEP * 2,
            'header' => 'Search results',
            // Layout 100 is "hidden" in core's header layouts: the plugin
            // carries its own visually hidden heading.
            'header_layout' => '100',
            'tstamp' => $now,
            'crdate' => $now,
        ];
        // getColumnNames() returns name => true, so the column list is already
        // in the shape array_intersect_key wants.
        $connection->insert('tt_content', array_intersect_key($row, $columns));

        $io->writeln(sprintf('  %-28s page %d', 'Search page', $searchUid));
    }

    /**
     * Fill each theme's detail page with the shared showcase cross-section.
     *
     * Every page gets the SAME elements in the same order. That is what makes
     * them worth having: a reader can open two themes side by side and the only
     * thing that differs is the theme. Picking flattering elements per theme
     * would produce twenty prettier pages that cannot be compared with each
     * other.
     *
     * @param array<string, int> $themePages page uid keyed by theme id
     */
    private function seedThemePages(SymfonyStyle $io, array $themePages, int $now): void
    {
        if ($themePages === []) {
            return;
        }

        $resolver = new StyleguideFixtureResolver(
            $this->databaseSchema,
            new StyleguideDemoValueGenerator(),
            new StyleguideCollectionAliasPolicy($this->databaseSchema),
        );
        $seeder = new ContentElementSeeder(
            $this->connectionPool,
            $this->storageRepository,
            $this->databaseSchema,
            self::FAL_FOLDER,
            1777300004,
        );
        $columns = $this->databaseSchema->getColumnNames('tt_content');

        $catalog = [];
        foreach ($this->elementCatalog->getElements() as $element) {
            $catalog[$element['cType']] = $element;
        }

        $showcase = GrandeSiteDefinitions::themeShowcaseElements();
        $missing = array_values(array_filter($showcase, static fn (string $cType): bool => !isset($catalog[$cType])));
        if ($missing !== []) {
            $io->warning(sprintf('Showcase elements not in the catalog: %s', implode(', ', $missing)));
        }

        $pages = 0;
        $placed = 0;
        foreach ($themePages as $pageUid) {
            $this->getContentCleaner()->softDeleteSeededContent($pageUid, $now);

            $sorting = 0;
            foreach ($showcase as $cType) {
                $record = $catalog[$cType] ?? null;
                if ($record === null) {
                    continue;
                }

                $sorting += self::SORTING_STEP;
                $seeder->insert($pageUid, $now, $resolver->buildContentInsert(
                    $pageUid,
                    $cType,
                    $record['name'],
                    $record['fixture'],
                    $sorting,
                    $now,
                    $columns,
                    ContentBlockDefinitionRegistry::buildDefinitionFromConfig($record['config']),
                ));
                $placed++;
            }
            $pages++;
        }

        $io->writeln(sprintf('  %-28s %d pages, %d elements', 'Theme detail pages', $pages, $placed));
    }

    private function getContentCleaner(): DesiderioContentCleaner
    {
        return $this->contentCleaner ??= new DesiderioContentCleaner(
            $this->connectionPool,
            $this->liveWorkspaceQueryHelper,
            new CollectionCleanupService($this->connectionPool, $this->databaseSchema, $this->liveWorkspaceQueryHelper),
            new ContentBlockCollectionMap(),
        );
    }

    /**
     * Find this site's root by title within its parent — never by slug.
     *
     * Restricted to live, default-language rows so a translation or a workspace
     * version can never be mistaken for the root itself.
     */
    private function findRootPageUid(int $parentPid, string $title): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $uid = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentPid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return is_numeric($uid) ? (int)$uid : null;
    }

    /**
     * @param array<string, true> $columns
     * @param array<string, mixed> $attributes
     */
    private function upsertPage(
        SeedPageUpserter $upserter,
        int $parentPid,
        string $title,
        string $slug,
        int $sorting,
        int $now,
        array $columns,
        array $attributes,
    ): int {
        $existingUid = $upserter->findExistingPageUid($parentPid, $title, $slug, $columns);
        if ($existingUid !== null) {
            $upserter->update($existingUid, $title, $slug, $sorting, $now, $columns, $attributes);
            return $existingUid;
        }

        return $upserter->create($parentPid, $title, $slug, $sorting, $now, $columns, $attributes);
    }
}
