<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ThemeOverviewTranslationsTest extends TestCase
{
    public function testGeneratedOverviewLabelsHaveCompleteGermanTranslations(): void
    {
        $root = dirname(__DIR__, 2);
        $source = $this->labels($root . '/Resources/Private/Language/themes.xlf');
        $german = $this->labels($root . '/Resources/Private/Language/de.themes.xlf');
        self::assertSame(array_keys($source), array_keys($german));

        foreach ($source as $key => $label) {
            self::assertSame($label['source'], $german[$key]['source'], 'Stale German source: ' . $key);
            self::assertNotSame('', $german[$key]['target'], 'Missing German translation: ' . $key);
            preg_match_all('/%[ds]/', $label['source'], $sourcePlaceholders);
            preg_match_all('/%[ds]/', $german[$key]['target'], $targetPlaceholders);
            self::assertSame($sourcePlaceholders[0], $targetPlaceholders[0], 'Changed placeholders: ' . $key);
        }

        $template = (string)file_get_contents($root . '/Resources/Private/Templates/Partials/Pages/ThemeOverview.fluid.html');
        preg_match_all('/themes\.xlf:([a-zA-Z0-9.]+)\'/s', $template, $references);
        $referencedKeys = array_values(array_unique($references[1]));
        $sourceKeys = array_keys($source);
        sort($referencedKeys);
        sort($sourceKeys);
        self::assertSame($sourceKeys, $referencedKeys, 'Every generated label must resolve in both languages.');
    }

    public function testEveryRegisteredThemeHasCurrentTranslatableDescriptions(): void
    {
        $root = dirname(__DIR__, 2);
        $labels = $this->labels($root . '/Resources/Private/Language/themes.xlf');
        $registry = json_decode((string)file_get_contents($root . '/Build/Data/theme-registry.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($registry['themes'] as $theme) {
            foreach (['character', 'use'] as $field) {
                self::assertSame($theme[$field], $labels['theme.' . $theme['id'] . '.' . $field]['source']);
            }
        }
    }

    /** @return array<string, array{source: string, target: string}> */
    private function labels(string $path): array
    {
        $document = simplexml_load_file($path);
        self::assertNotFalse($document, 'Invalid XLIFF: ' . $path);
        $document->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:2.0');
        self::assertCount(1, $document->xpath('/x:xliff/x:file'));
        $labels = [];
        foreach ($document->xpath('/x:xliff/x:file/x:unit') as $unit) {
            $key = (string)$unit['id'];
            self::assertArrayNotHasKey($key, $labels, 'Duplicate label: ' . $key);
            $labels[$key] = [
                'source' => (string)$unit->segment->source,
                'target' => (string)$unit->segment->target,
            ];
        }
        self::assertNotEmpty($labels, 'Empty catalog: ' . $path);

        return $labels;
    }
}
