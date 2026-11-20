<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * composer.json allows praetorius/vite-asset-collector 1.18.4 and 2.x. The
 * two majors share exactly one Fluid surface: <vite:asset entry="EXT:…"/> in
 * the Praetorius\ViteAssetCollector\ViewHelpers namespace. 2.0 removed the
 * <vac:asset.vite>/<vac:resource.vite> aliases and the ViteService class,
 * deprecated every embedding argument except "entry" and "manifest", and
 * added child ViewHelpers that 1.x does not know. This test keeps the
 * templates and PHP code inside that shared surface.
 */
final class ViteAssetUsageTest extends TestCase
{
    private const ALLOWED_ARGUMENTS = ['entry', 'manifest'];

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

    public function testTemplatesUseOnlyTheViteAssetTagSharedByBothMajors(): void
    {
        $tags = 0;
        foreach ([...self::files('Resources/Private', '.html'), ...self::files('ContentBlocks', '.html')] as $path) {
            $source = (string)file_get_contents($path);
            self::assertDoesNotMatchRegularExpression('/<vac:/', $source, $path . ' uses a ViewHelper alias that 2.0 removed');
            self::assertDoesNotMatchRegularExpression('/<vite:(?!asset[\s\/>])/', $source, $path . ' uses a vite ViewHelper that only one major provides');

            preg_match_all('/<vite:asset\s([^>]*?)\/?>/s', $source, $matches);
            foreach ($matches[1] as $attributes) {
                $tags++;
                preg_match_all('/([A-Za-z]+)\s*=/', $attributes, $names);
                self::assertSame([], array_values(array_diff($names[1], self::ALLOWED_ARGUMENTS)), $path . ' passes an argument 2.0 deprecated');
                self::assertMatchesRegularExpression('/entry="EXT:astryx_typo3\/Resources\/Private\/Assets\/[A-Za-z]+\.entry\.(css|js)"/', $attributes, $path);
            }
        }
        self::assertSame(4, $tags, 'Default layout and element preview each embed the CSS and JS entry');
    }

    public function testPhpCodeDoesNotUseTheCollectorApi(): void
    {
        foreach (self::files('Classes', '.php') as $path) {
            self::assertStringNotContainsString('Praetorius\\ViteAssetCollector', (string)file_get_contents($path), $path . ' uses a collector class; its PHP API differs between 1.x and 2.x');
        }
    }
}
