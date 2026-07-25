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

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
        private readonly DatabaseSchemaHelper $databaseSchema,
        private readonly LiveWorkspaceQueryHelper $liveWorkspaceQueryHelper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Page uid the site root is created below. Use 0 for the page tree root.', '0')
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
        $sorting = self::SORTING_STEP;
        $hubUid = 0;
        $legalUids = [];
        $errorUid = 0;

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

            if ($page['role'] === 'hub') {
                $hubUid = $uid;
            } elseif ($page['role'] === 'legal') {
                $legalUids[] = $uid;
            } elseif ($page['role'] === 'error') {
                $errorUid = $uid;
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

        $io->success(sprintf('Astryx site tree seeded — root page uid %d.', $rootUid));

        $io->section('Next: write config/sites/desiderio-grande/');
        $io->listing([
            sprintf('rootPageId: %d', $rootUid),
            sprintf('errorHandling 404 target: t3://page?uid=%d', $errorUid),
            sprintf('desiderioGrande.footer.legalPageIds: \'%s\'', implode(',', $legalUids)),
        ]);

        $rows = [];
        foreach ($created as $title => $uid) {
            $rows[] = [$uid, $title];
        }
        $io->table(['uid', 'page'], $rows);

        return self::SUCCESS;
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
