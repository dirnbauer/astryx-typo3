<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * composer.json requires praetorius/vite-asset-collector 2.x. Its plain
 * <vite:asset entry="…"/> changes the page head compared with 1.x: entry CSS
 * is added with priority, ahead of page.includeCSS, and every woff2 file the
 * CSS references is preloaded (48 for Main.entry.css). The templates keep the
 * 1.x output with <vite:asset.css priority="{false}"/> and
 * <vite:asset.font preload="{false}"/> inside every CSS entry. This test keeps
 * them there, keeps the deprecated embedding arguments (removed in 3.0) out
 * and the 1.x aliases, which 2.0 removed.
 */
final class ViteAssetUsageTest extends TestCase
{
    private const ALLOWED_ARGUMENTS = ['entry', 'manifest'];

    private const ALLOWED_VIEW_HELPERS = ['asset', 'asset.css', 'asset.font'];

    /** @return list<string> */
    private static function files(string $directory, string $suffix): array
    {
        $root = dirname(__DIR__, 2) . '/' . $directory;
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function testTemplatesKeepTheOneXCascadeAndFontLoading(): void
    {
        $entries = ['css' => 0, 'js' => 0];
        foreach ([...self::files('Resources/Private', '.html'), ...self::files('ContentBlocks', '.html')] as $path) {
            $source = (string)file_get_contents($path);
            self::assertDoesNotMatchRegularExpression('/<vac:/', $source, $path . ' uses a ViewHelper alias that 2.0 removed');

            preg_match_all('/<vite:([A-Za-z.]+)/', $source, $viewHelpers);
            self::assertSame([], array_values(array_diff($viewHelpers[1], self::ALLOWED_VIEW_HELPERS)), $path . ' uses a vite ViewHelper this test does not cover');

            preg_match_all('/<vite:asset\s([^>]*?)(?:\/>|>(.*?)<\/vite:asset>)/s', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attributes = $match[1];
                $children = $match[2] ?? '';
                preg_match_all('/([A-Za-z]+)\s*=/', $attributes, $names);
                self::assertSame([], array_values(array_diff($names[1], self::ALLOWED_ARGUMENTS)), $path . ' passes an argument 2.0 deprecated');
                self::assertSame(1, preg_match('/entry="EXT:astryx_typo3\/Resources\/Private\/Assets\/[A-Za-z]+\.entry\.(css|js)"/', $attributes, $entry), $path);
                $type = $entry[1];
                $entries[$type]++;
                if ($type === 'css') {
                    self::assertStringContainsString('<vite:asset.css priority="{false}"/>', $children, $path . ': entry CSS must follow page.includeCSS, as with 1.x');
                    self::assertStringContainsString('<vite:asset.font preload="{false}"/>', $children, $path . ': the woff2 files must not be preloaded, as with 1.x');
                }
            }
        }
        self::assertSame(['css' => 2, 'js' => 2], $entries, 'Default layout and element preview each embed the CSS and JS entry');
    }

    public function testPhpCodeDoesNotUseTheCollectorApi(): void
    {
        foreach (self::files('Classes', '.php') as $path) {
            self::assertStringNotContainsString('Praetorius\\ViteAssetCollector', (string)file_get_contents($path), $path . ' uses a collector class; the templates are the only contact point');
        }
    }
}
