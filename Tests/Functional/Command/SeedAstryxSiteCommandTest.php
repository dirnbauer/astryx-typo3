<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Page\PageLayoutResolver;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\AstryxTypo3\Command\SeedAstryxSiteCommand;

/**
 * Seeding the site twice leaves it as seeding it once does.
 *
 * Desiderio's content cleaner recognises only its own `desiderio_*` types, so
 * until the command named its own element types every run added a second copy
 * of every element to every page. Collection children are checked as well: the
 * section headers used to render without their items.
 */
final class SeedAstryxSiteCommandTest extends FunctionalTestCase
{
    /**
     * Content Blocks collection tables of this extension.
     */
    private const CHILD_TABLES = [
        'astryx_typo3_icon_lead',
        'astryx_typo3_label_value',
        'astryx_typo3_logo_item',
        'astryx_typo3_metric_item',
        'astryx_typo3_nav_item',
        'astryx_typo3_person_item',
        'astryx_typo3_qa_item',
        'astryx_typo3_quote_item',
    ];

    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'form', 'workspaces'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'webconsulting/desiderio',
        'webconsulting/astryx-typo3',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SeedingBase.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function seedingTwiceKeepsOneCopyOfEveryElement(): void
    {
        self::assertSame(Command::SUCCESS, $this->seed());
        $first = $this->elementsPerPageAndType();
        $firstChildren = $this->liveChildren();

        self::assertNotSame([], $first, 'the first run seeds elements');
        self::assertGreaterThan(0, $firstChildren, 'collection elements get their items');

        self::assertSame(Command::SUCCESS, $this->seed());

        self::assertSame($first, $this->elementsPerPageAndType(), 'a second run replaces elements instead of adding copies');
        self::assertSame($firstChildren, $this->liveChildren(), 'a second run replaces collection items instead of adding copies');
    }

    #[Test]
    public function germanPagesRetainLayoutsAndThemesOnCreationAndAfterRepair(): void
    {
        self::assertSame(Command::SUCCESS, $this->seed(false));
        $this->assertGermanPageLayouts();

        // Reproduce translations created by the old seeder: layout and theme
        // fields were left at their database defaults, overriding the source.
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->update('pages', [
            'backend_layout' => '',
            'backend_layout_next_level' => '',
            'tx_desiderioastryx_theme' => '',
        ], ['sys_language_uid' => 1]);

        self::assertSame(Command::SUCCESS, $this->seed(false));
        $this->assertGermanPageLayouts();
    }

    private function assertGermanPageLayouts(): void
    {
        // The second assertion must read the repaired records, not the
        // PageRepository overlays cached during the first assertion.
        $this->get(CacheManager::class)->getCache('runtime')->flush();
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $originals = $connection->fetchAllAssociative('SELECT * FROM pages WHERE sys_language_uid = 0 AND uid != 1 AND deleted = 0');
        self::assertNotEmpty($originals);

        $repository = $this->get(PageRepository::class);
        $resolver = $this->get(PageLayoutResolver::class);
        foreach ($originals as $original) {
            $german = $repository->getPageOverlay($original, 1);
            self::assertSame(1, (int)$german['sys_language_uid'], $original['title'] . ' has a German page');
            self::assertSame(
                $resolver->getLayoutIdentifierForPageWithoutPrefix($original, []),
                $resolver->getLayoutIdentifierForPageWithoutPrefix($german, []),
                $original['title'] . ' selects the same PAGEVIEW template in German',
            );
            self::assertSame($original['backend_layout_next_level'], $german['backend_layout_next_level'], $original['title']);
            self::assertSame($original['tx_desiderioastryx_theme'], $german['tx_desiderioastryx_theme'], $original['title']);
        }
    }

    private function seed(bool $content = true): int
    {
        $command = $this->get(SeedAstryxSiteCommand::class);
        self::assertInstanceOf(SeedAstryxSiteCommand::class, $command);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--parent' => '1', '--content' => $content]);
        if ($exitCode !== Command::SUCCESS) {
            self::fail($tester->getDisplay());
        }

        return $exitCode;
    }

    /**
     * @return array<string, int> "pid:CType" => live rows
     */
    private function elementsPerPageAndType(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->executeQuery("SELECT pid, CType, COUNT(*) AS n FROM tt_content WHERE deleted = 0 AND CType LIKE 'astryx_typo3_%' GROUP BY pid, CType ORDER BY pid, CType")
            ->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $result[(is_scalar($row['pid']) ? (string)$row['pid'] : '') . ':' . (is_scalar($row['CType']) ? (string)$row['CType'] : '')] = is_numeric($row['n']) ? (int)$row['n'] : 0;
        }

        return $result;
    }

    private function liveChildren(): int
    {
        $total = 0;
        foreach (self::CHILD_TABLES as $table) {
            $count = $this->getConnectionPool()
                ->getConnectionForTable($table)
                ->executeQuery('SELECT COUNT(*) FROM ' . $table . ' WHERE deleted = 0')
                ->fetchOne();
            $total += is_numeric($count) ? (int)$count : 0;
        }

        return $total;
    }
}
