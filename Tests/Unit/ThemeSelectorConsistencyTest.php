<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A theme has to be named in three unrelated places before an editor can pick
 * it: the seed list the CSS is generated from, the page-properties selector,
 * and the site setting. Nothing linked them, so adding a theme to the seed
 * produced tokens in the stylesheet that no one could ever select — a failure
 * that looks like nothing at all, because the site keeps rendering in whatever
 * theme it already had.
 *
 * That is exactly what happened when frost, latte, solar, retro and midnight
 * were added: the build was green, the CSS was correct, and all five were
 * unreachable from the backend.
 *
 * These assertions are the link. They cost one test run; the alternative costs
 * a bug report from an editor who cannot find a theme that demonstrably exists.
 */
final class ThemeSelectorConsistencyTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Every theme id the system knows about: the seven Astryx ships plus the
     * ones this extension seeds.
     *
     * @return list<string>
     */
    private static function knownThemes(): array
    {
        $seeds = json_decode(
            (string)file_get_contents(self::root() . '/Build/Data/grande-themes.json'),
            true
        );
        self::assertIsArray($seeds['themes'] ?? null, 'grande-themes.json has no themes array');

        $upstream = json_decode(
            (string)file_get_contents(self::root() . '/Build/Data/upstream-theme-meta.json'),
            true
        );
        self::assertIsArray($upstream, 'upstream-theme-meta.json is not a list');

        $ids = array_merge(
            array_column($seeds['themes'], 'name'),
            array_column($upstream, 'id'),
        );
        sort($ids);

        return $ids;
    }

    /**
     * The page-properties dropdown. Read from the literal map rather than by
     * booting TCA, so the test stays a unit test.
     *
     * @return list<string>
     */
    private static function tcaThemes(): array
    {
        $php = (string)file_get_contents(self::root() . '/Configuration/TCA/Overrides/pages.php');

        $start = strpos($php, 'foreach ([');
        self::assertNotFalse($start, 'the theme map in pages.php has moved');
        $end = strpos($php, '] as $value => $label)', $start);
        self::assertNotFalse($end, 'the theme map in pages.php has moved');

        preg_match_all(
            "/'([a-z0-9-]+)'\s*=>\s*'/",
            substr($php, $start, $end - $start),
            $matches
        );
        $ids = $matches[1];
        sort($ids);

        return $ids;
    }

    /**
     * The site setting's enum.
     *
     * @return list<string>
     */
    private static function settingThemes(): array
    {
        $yaml = (string)file_get_contents(
            self::root() . '/Configuration/Sets/DesiderioGrande/settings.definitions.yaml'
        );

        $start = strpos($yaml, 'desiderioGrande.theme.default:');
        self::assertNotFalse($start, 'the theme setting has been renamed');
        $enum = strpos($yaml, 'enum:', $start);
        self::assertNotFalse($enum, 'the theme setting has no enum');
        $end = strpos($yaml, 'default:', $enum);
        self::assertNotFalse($end, 'the theme setting has no default');

        preg_match_all(
            "/^\s{6}([a-z0-9-]+):\s*'/m",
            substr($yaml, $enum, $end - $enum),
            $matches
        );
        $ids = $matches[1];
        sort($ids);

        return $ids;
    }

    public function testEveryThemeIsSelectableInPageProperties(): void
    {
        self::assertSame(
            self::knownThemes(),
            self::tcaThemes(),
            'Configuration/TCA/Overrides/pages.php does not offer exactly the themes that exist. '
            . 'A theme missing here is generated into the stylesheet but cannot be chosen on a page.'
        );
    }

    public function testEveryThemeIsSelectableAsSiteSetting(): void
    {
        self::assertSame(
            self::knownThemes(),
            self::settingThemes(),
            'settings.definitions.yaml does not offer exactly the themes that exist. '
            . 'A theme missing here cannot be made a site default.'
        );
    }

    /**
     * The two selectors are populated by hand from the same seed, so they can
     * disagree with each other even while both look plausible.
     */
    public function testBothSelectorsAgreeWithEachOther(): void
    {
        self::assertSame(self::tcaThemes(), self::settingThemes());
    }

    /**
     * Deliberately no contrast assertions here.
     *
     * The first version of this test asserted 4.5:1 on the seed values, and it
     * failed on `sand` — a theme that has shipped for months and is not in fact
     * broken. Build/Scripts/build-contrast-overrides.mjs raises any foreground
     * token that misses AA by the smallest step that clears it (sand's
     * secondary text goes #8A6D3C -> #876b3b), and audit-contrast.mjs then
     * checks 1500 resolved pairs across every theme and both schemes.
     *
     * So the contract is enforced one layer down from the seed, on the values a
     * browser actually paints. Restating it here only duplicated a weaker
     * version of that check and contradicted a design decision the codebase had
     * already made and documented.
     *
     * Run `npm run build` for the real thing; it ends in the audit.
     */
}
