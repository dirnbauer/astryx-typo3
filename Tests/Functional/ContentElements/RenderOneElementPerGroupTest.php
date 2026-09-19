<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Functional\ContentElements;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\AstBuilder;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LossyTokenizer;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * One content element out of each of the ten groups renders.
 *
 * Two hundred and fifty elements is too many to render on every push, and one
 * element is too few to mean anything: this takes the first element of every
 * group in Build/Data/matrix/<group>.json, so a Fluid mistake in any group's
 * shared idiom — the section wrapper, the heading, the actions row, the
 * collection loop — is caught within seconds.
 *
 * The demo content is the element's own library.json, the same file the
 * element library shows an editor in the picker. Scalar fields go through a
 * real tt_content row and the Record API, so `<f:render.text>` sees exactly
 * what it sees in the frontend. Relations (files, collection children) are not
 * seeded: they resolve to empty, which is a state every element has to survive
 * anyway, and it keeps this test about Fluid rather than about FAL.
 *
 * Nothing here skips. A missing library.json, a missing config.yaml or a
 * missing template is a failure, because each of them means the catalog and
 * the matrix have drifted apart.
 */
final class RenderOneElementPerGroupTest extends FunctionalTestCase
{
    private const EXTENSION_ROOT = __DIR__ . '/../../..';
    // astryx_typo3 declares desiderio in ext_emconf.php, desiderio declares
    // content_blocks, workspaces and form: the testing framework refuses to
    // build an instance whose declared dependencies are absent.
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'form', 'workspaces'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'webconsulting/desiderio',
        'webconsulting/astryx-typo3',
    ];

    /**
     * One element per group, read before TYPO3 boots.
     *
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
     */
    public static function oneElementPerGroupProvider(): iterable
    {
        $groupFiles = glob(self::EXTENSION_ROOT . '/Build/Data/matrix/*.json');
        self::assertIsArray($groupFiles, 'Build/Data/matrix is unreadable');
        sort($groupFiles);
        self::assertCount(10, $groupFiles, 'the matrix must hold exactly ten groups');

        foreach ($groupFiles as $groupFile) {
            $group = basename($groupFile, '.json');
            $matrix = self::json($groupFile);
            self::assertIsArray($matrix['elements'] ?? null, $groupFile . ' has no elements');
            $first = $matrix['elements'][0] ?? null;
            self::assertIsArray($first, $groupFile . ' holds no first element');
            $id = $first['id'] ?? null;
            self::assertIsString($id, $groupFile . ' first element has no id');

            $directory = self::EXTENSION_ROOT . '/ContentBlocks/ContentElements/' . $id;

            $libraryFile = $directory . '/library.json';
            self::assertFileExists(
                $libraryFile,
                sprintf('%s is in the %s matrix but ships no library.json demo content', $id, $group),
            );

            $configFile = $directory . '/config.yaml';
            self::assertFileExists($configFile, $id . ' ships no config.yaml');
            $config = Yaml::parseFile($configFile);
            self::assertIsArray($config);
            $typeName = $config['typeName'] ?? null;
            self::assertIsString($typeName, $id . ' declares no typeName in config.yaml');

            self::assertFileExists(
                $directory . '/templates/frontend.html',
                $id . ' ships no frontend template',
            );

            yield $group => [$group, $id, $typeName, self::json($libraryFile)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(string $path): array
    {
        $raw = file_get_contents($path);
        self::assertIsString($raw, $path . ' is unreadable');
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, $path . ' does not decode to an array');

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $library
     */
    #[Test]
    #[DataProvider('oneElementPerGroupProvider')]
    public function elementRendersItsDemoContent(string $group, string $id, string $typeName, array $library): void
    {
        $record = $this->persistDemoRecord($typeName, $library);

        $renderingContext = $this->get(RenderingContextFactory::class)->create([], $this->frontendRequest());
        $view = new TemplateView($renderingContext);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource(
            (string)file_get_contents(self::EXTENSION_ROOT . '/ContentBlocks/ContentElements/' . $id . '/templates/frontend.html'),
        );
        $view->assignMultiple([
            'data' => $record,
            // What <cb:assetPath> reads when it is not handed a name, and what
            // Content Blocks itself assigns when it renders an element.
            'settings' => ['_content_block_name' => 'astryx-typo3/' . $id],
        ]);

        $rendered = $view->render();

        self::assertNotSame(
            '',
            trim($rendered),
            sprintf('%s (group %s) rendered nothing from its own library.json', $id, $group),
        );
    }

    /**
     * Writes the demo content into tt_content and hands back the Record the
     * frontend would see. Only scalar fields are written: a value that is an
     * array in library.json describes a relation (files, collection children),
     * and relations are resolved by the Record API from the database, not from
     * an assigned array.
     *
     * @param array<string, mixed> $library
     */
    private function persistDemoRecord(string $typeName, array $library): \TYPO3\CMS\Core\Domain\RecordInterface
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        $columns = array_map(
            static fn(object $column): string => (string)$column->getName(),
            $connection->createSchemaManager()->listTableColumns('tt_content'),
        );

        $row = [
            'uid' => 1,
            'pid' => 1,
            'CType' => $typeName,
        ];
        foreach ($library as $field => $value) {
            if (str_starts_with($field, '_') || is_array($value) || !in_array($field, $columns, true)) {
                continue;
            }
            $row[$field] = is_bool($value) ? (int)$value : $value;
        }

        $connection->insert('tt_content', $row);

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $persisted = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($persisted, 'the demo tt_content row was not written');

        return $this->get(RecordFactory::class)->createResolvedRecordFromDatabaseRow('tt_content', $persisted);
    }

    /**
     * Element templates link, translate, collect assets and render rich text;
     * all four want a frontend request carrying a site, a language and the
     * TypoScript that defines lib.parseFunc_RTE — <f:format.html>, which
     * <f:render.text> delegates to for an RTE field, throws without it.
     */
    private function frontendRequest(): ServerRequest
    {
        $site = new Site('astryx', 1, [
            'base' => 'https://example.com/',
            'websiteTitle' => 'Astryx',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);

        return (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage())
            ->withAttribute('frontend.typoscript', $this->defaultFrontendTypoScript());
    }

    /**
     * Compiles EXT:frontend's own default TypoScript — the snippet that
     * carries lib.parseFunc and lib.parseFunc_RTE since TYPO3 13.2 — into the
     * setup array a ContentObjectRenderer resolves "< lib.parseFunc_RTE"
     * against. Using the real definition rather than a hand-written stub is the
     * point: rich text then renders here the way it renders on a page.
     */
    private function defaultFrontendTypoScript(): FrontendTypoScript
    {
        $source = (string)($GLOBALS['TYPO3_CONF_VARS']['FE']['defaultTypoScript_setup'] ?? '');
        self::assertStringContainsString(
            'lib.parseFunc',
            $source,
            'EXT:frontend no longer ships lib.parseFunc in its default TypoScript',
        );

        $ast = $this->get(AstBuilder::class)->build((new LossyTokenizer())->tokenize($source), new RootNode());

        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupTree($ast);
        $typoScript->setSetupArray($ast->toArray());
        // "config." is read by stdWrap even when nothing in it is set; without
        // it the ContentObjectRenderer refuses to run at all.
        $typoScript->setConfigTree(new RootNode());
        $typoScript->setConfigArray([]);

        return $typoScript;
    }
}
