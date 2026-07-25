<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The matrix is the catalog's source of truth, so its invariants are pinned
 * here rather than trusted: ten groups of twenty-five, every id unique, every
 * field and record type drawn from the shared vocabulary, and copy that says
 * enough for an editor to pick the right element out of two hundred and fifty.
 */
final class ElementMatrixTest extends TestCase
{
    private const GROUPS = [
        'hero', 'features', 'content', 'pricing', 'social-proof',
        'team', 'data', 'conversion', 'navigation', 'footer',
    ];

    private const ELEMENTS_PER_GROUP = 25;

    /** @return array<string, mixed> */
    private static function readJson(string $relativePath): array
    {
        $path = dirname(__DIR__, 2) . '/' . $relativePath;
        self::assertFileExists($path, $relativePath . ' is missing');
        $decoded = json_decode((string)file_get_contents($path), true);
        self::assertIsArray($decoded, $relativePath . ' is not valid JSON');

        return $decoded;
    }

    /** @return list<array<string, mixed>> */
    private static function allElements(): array
    {
        $elements = [];
        foreach (self::GROUPS as $group) {
            $data = self::readJson('Build/Data/matrix/' . $group . '.json');
            foreach ($data['elements'] ?? [] as $element) {
                $element['group'] = $group;
                $elements[] = $element;
            }
        }

        return $elements;
    }

    public function testEveryGroupCarriesTwentyFiveElements(): void
    {
        foreach (self::GROUPS as $group) {
            $data = self::readJson('Build/Data/matrix/' . $group . '.json');
            self::assertCount(
                self::ELEMENTS_PER_GROUP,
                $data['elements'] ?? [],
                sprintf('group "%s" must hold exactly %d elements', $group, self::ELEMENTS_PER_GROUP),
            );
        }

        self::assertCount(self::GROUPS === [] ? 0 : count(self::GROUPS) * self::ELEMENTS_PER_GROUP, self::allElements());
    }

    public function testEveryIdentifierIsUniqueAndUrlSafe(): void
    {
        $seen = [];
        foreach (self::allElements() as $element) {
            $id = $element['id'] ?? '';
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/',
                $id,
                'element ids are lower-case kebab-case: ' . var_export($id, true),
            );
            self::assertArrayNotHasKey($id, $seen, sprintf('id "%s" is used by %s and %s', $id, $seen[$id] ?? '?', $element['group']));
            $seen[$id] = $element['group'];
        }
    }

    /**
     * The catalog's cTypes strip hyphens, so two ids that differ only by a
     * hyphen would silently register the same content type.
     */
    public function testIdentifiersStayDistinctOnceHyphensAreStripped(): void
    {
        $seen = [];
        foreach (self::allElements() as $element) {
            $cType = 'desiderio_grande_' . str_replace('-', '', (string)$element['id']);
            self::assertArrayNotHasKey(
                $cType,
                $seen,
                sprintf('"%s" and "%s" both become %s', $element['id'], $seen[$cType] ?? '?', $cType),
            );
            $seen[$cType] = $element['id'];
        }
    }

    public function testTitlesAreUniqueInBothLanguages(): void
    {
        foreach (['title', 'titleDe'] as $key) {
            $seen = [];
            foreach (self::allElements() as $element) {
                $title = (string)($element[$key] ?? '');
                self::assertNotSame('', $title, $element['id'] . ' has no ' . $key);
                self::assertArrayNotHasKey(
                    $title,
                    $seen,
                    sprintf('%s "%s" is shared by %s and %s', $key, $title, $seen[$title] ?? '?', $element['id']),
                );
                $seen[$title] = $element['id'];
            }
        }
    }

    /**
     * The description is what an editor reads in the picker and what the search
     * ranks on. Its shape is the contract: what it renders, what it is for, and
     * which sibling to reach for instead.
     */
    public function testDescriptionsSayWhatTheElementIsForAndWhatToPreferInstead(): void
    {
        foreach (self::allElements() as $element) {
            foreach (['description', 'descriptionDe'] as $key) {
                $description = (string)($element[$key] ?? '');
                $length = mb_strlen($description);

                self::assertGreaterThanOrEqual(100, $length, $element['id'] . ' ' . $key . ' is too short');
                self::assertLessThanOrEqual(650, $length, $element['id'] . ' ' . $key . ' is too long');
            }

            self::assertStringContainsString(
                'Use it for:',
                (string)$element['description'],
                $element['id'] . ' names no situation it is for',
            );
            self::assertStringContainsString(
                'Prefer ',
                (string)$element['description'],
                $element['id'] . ' does not disambiguate itself from a sibling',
            );
        }
    }

    public function testEveryFieldComesFromTheSharedVocabulary(): void
    {
        $library = self::readJson('Build/Data/field-library.json');
        $known = array_keys($library['fields']);

        foreach (self::allElements() as $element) {
            foreach ($element['fields'] ?? [] as $reference) {
                $name = explode(':', (string)$reference, 2)[0];
                self::assertContains(
                    $name,
                    $known,
                    sprintf('%s uses field "%s", which is not in the field library', $element['id'], $name),
                );
            }
        }
    }

    public function testEveryCollectionUsesASharedRecordType(): void
    {
        $recordTypes = self::readJson('Build/Data/record-types.json');
        $known = array_keys($recordTypes['recordTypes']);

        foreach (self::allElements() as $element) {
            $collection = $element['collection'] ?? null;
            if (!is_array($collection)) {
                continue;
            }
            self::assertContains(
                $collection['recordType'] ?? '',
                $known,
                $element['id'] . ' points at an unknown record type',
            );
        }
    }

    public function testEveryReferencedAstryxComponentExistsUpstream(): void
    {
        $inventory = self::readJson('Build/astryx/components.json');
        $known = array_column($inventory['components'], 'component');

        foreach (self::allElements() as $element) {
            foreach ($element['astryx'] ?? [] as $component) {
                self::assertContains(
                    $component,
                    $known,
                    sprintf('%s references Astryx component "%s", which does not exist', $element['id'], $component),
                );
            }
        }
    }

    /**
     * The whole point of a server-rendered design system: a page that needs no
     * JavaScript to show its content. A handful of elements genuinely cannot be
     * built without it, and they are the exception that has to stay small.
     */
    public function testAtLeastNineInTenElementsNeedNoJavaScript(): void
    {
        $elements = self::allElements();
        $scripted = array_values(array_filter($elements, static fn(array $e): bool => !empty($e['js'])));

        self::assertLessThanOrEqual(
            (int)floor(count($elements) * 0.1),
            count($scripted),
            'too many elements need JavaScript: ' . implode(', ', array_column($scripted, 'id')),
        );
    }

    public function testScriptedElementsUseAKnownBehaviour(): void
    {
        foreach (self::allElements() as $element) {
            if (empty($element['js'])) {
                continue;
            }
            self::assertContains(
                $element['js'],
                ['carousel', 'tabs', 'dialog', 'dismiss'],
                sprintf('%s asks for behaviour "%s", which grande.js does not implement', $element['id'], $element['js']),
            );
        }
    }

    public function testKeywordsAndSynonymsAreAuthoredInBothLanguages(): void
    {
        foreach (self::allElements() as $element) {
            foreach (['keywords', 'keywordsDe'] as $key) {
                $keywords = $element[$key] ?? [];
                self::assertNotEmpty($keywords, $element['id'] . ' has no ' . $key);
                self::assertLessThanOrEqual(10, count($keywords), $element['id'] . ' ' . $key . ': only the first 10 become chips');
            }
            foreach (['synonyms', 'synonymsDe'] as $key) {
                self::assertNotEmpty($element[$key] ?? [], $element['id'] . ' has no ' . $key);
            }
        }
    }
}
