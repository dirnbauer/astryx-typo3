<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The rules 250 content elements are held to.
 *
 * Two hundred and fifty elements do not fit in anyone's head, so what keeps
 * them consistent is checked rather than reviewed. This was
 * scripts/audit-content-elements.php until 2.0; it is a test now for the plain
 * reason that a gate nobody runs is not a gate — CI runs the test suite, and
 * nobody remembered to run the script.
 *
 * Each method is one family of rules over every element, and reports every
 * element that breaks it rather than stopping at the first.
 */
final class ContentElementAuditTest extends TestCase
{
    private const EXT_ROOT = __DIR__ . '/../..';

    /**
     * Files every element must have. The set is uniform on purpose: a missing
     * file is always a mistake, never a style.
     */
    private const REQUIRED_FILES = [
        'config.yaml',
        'templates/frontend.html',
        'templates/backend-preview.fluid.html',
        'assets/frontend.css',
        'assets/icon.svg',
        'language/labels.xlf',
        'language/de.labels.xlf',
        'library.json',
        'library.de.json',
        'fixture.json',
    ];

    /**
     * The one breakpoint set the whole catalog shares. Elements that invent
     * their own stop lining up with the ones beside them on a page.
     */
    private const BREAKPOINTS = [480, 639, 640, 641, 767, 768, 769, 1023, 1024, 1025];

    /** @return list<string> */
    private static function elements(): array
    {
        $dir = self::EXT_ROOT . '/ContentBlocks/ContentElements';
        $names = array_filter(
            scandir($dir) ?: [],
            static fn(string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($dir . '/' . $entry),
        );
        sort($names);

        return array_values($names);
    }

    private static function path(string $element, string $relative = ''): string
    {
        return self::EXT_ROOT . '/ContentBlocks/ContentElements/' . $element . ($relative === '' ? '' : '/' . $relative);
    }

    private static function read(string $element, string $relative): ?string
    {
        $path = self::path($element, $relative);

        return is_file($path) ? (string)file_get_contents($path) : null;
    }

    /**
     * @param list<string> $findings
     */
    private static function report(array $findings, string $rule): string
    {
        return sprintf(
            "%d element(s) break the rule: %s\n%s%s",
            count($findings),
            $rule,
            implode("\n", array_slice($findings, 0, 25)),
            count($findings) > 25 ? sprintf("\n… and %d more", count($findings) - 25) : '',
        );
    }

    #[Test]
    public function theCatalogHasTheElementsTheMatrixDeclares(): void
    {
        self::assertNotSame([], self::elements(), 'No content elements found at all.');
    }

    #[Test]
    public function everyElementHasTheCompleteFileSet(): void
    {
        $findings = [];
        foreach (self::elements() as $element) {
            foreach (self::REQUIRED_FILES as $file) {
                if (!is_file(self::path($element, $file))) {
                    $findings[] = sprintf('%s: missing %s', $element, $file);
                }
            }
        }

        self::assertSame([], $findings, self::report($findings, 'every element ships the same ten files'));
    }

    #[Test]
    public function everyElementDeclaresItsOwnIdentity(): void
    {
        $findings = [];
        $seenTypeNames = [];

        foreach (self::elements() as $element) {
            $config = self::read($element, 'config.yaml');
            if ($config === null) {
                continue; // reported by everyElementHasTheCompleteFileSet
            }

            $expectedType = 'astryx_typo3_' . str_replace('-', '', $element);
            if (!str_contains($config, 'typeName: ' . $expectedType)) {
                $findings[] = sprintf('%s: expected "typeName: %s"', $element, $expectedType);
            }
            if (!str_contains($config, 'name: astryx-typo3/' . $element)) {
                $findings[] = sprintf('%s: expected "name: astryx-typo3/%s"', $element, $element);
            }
            if (!str_contains($config, 'prefixFields: false')) {
                $findings[] = sprintf(
                    '%s: needs "prefixFields: false" so field identifiers stay shared across the catalog',
                    $element,
                );
            }
            if (isset($seenTypeNames[$expectedType])) {
                $findings[] = sprintf('%s: CType collides with %s', $element, $seenTypeNames[$expectedType]);
            }
            $seenTypeNames[$expectedType] = $element;
        }

        self::assertSame([], $findings, self::report($findings, 'CType and name follow from the directory name'));
    }

    #[Test]
    public function everyElementHasADistinctTitleAndAUsefulDescription(): void
    {
        $findings = [];
        $seenTitles = [];

        foreach (self::elements() as $element) {
            $raw = self::read($element, 'config.yaml');
            if ($raw === null) {
                continue;
            }
            $config = Yaml::parse($raw);
            self::assertIsArray($config, sprintf('%s: config.yaml did not parse to a mapping', $element));

            $title = (string)($config['title'] ?? '');
            if ($title === '') {
                $findings[] = sprintf('%s: no title', $element);
            } elseif (isset($seenTitles[$title])) {
                $findings[] = sprintf('%s: same title as %s ("%s")', $element, $seenTitles[$title], $title);
            } else {
                $seenTitles[$title] = $element;
            }

            $description = (string)($config['description'] ?? '');
            $length = mb_strlen($description);
            if ($length < 100 || $length > 650) {
                $findings[] = sprintf('%s: description is %d characters (want 100-650)', $element, $length);
            }
            if ($description !== '' && !str_contains($description, 'Use it for:')) {
                $findings[] = sprintf('%s: description has no "Use it for:" clause', $element);
            }
            if ($description !== '' && !str_contains($description, 'Prefer ')) {
                $findings[] = sprintf('%s: description does not disambiguate against a sibling ("Prefer …")', $element);
            }
        }

        self::assertSame([], $findings, self::report($findings, 'an editor can tell two elements apart from the picker alone'));
    }

    #[Test]
    public function everyCollectionFieldIsSafeToShare(): void
    {
        $findings = [];
        foreach (self::elements() as $element) {
            $raw = self::read($element, 'config.yaml');
            if ($raw === null) {
                continue;
            }
            $config = Yaml::parse($raw);
            if (!is_array($config)) {
                continue;
            }

            foreach ($config['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'Collection') {
                    continue;
                }
                $identifier = (string)($field['identifier'] ?? '?');

                if (($field['prefixField'] ?? null) !== true) {
                    $findings[] = sprintf(
                        '%s.%s: a Collection without prefixField collides with the next one on the same element',
                        $element,
                        $identifier,
                    );
                }
                if (isset($field['foreign_table'])
                    && (($field['shareAcrossTables'] ?? null) !== true || ($field['shareAcrossFields'] ?? null) !== true)
                ) {
                    $findings[] = sprintf(
                        '%s.%s: a shared foreign_table needs shareAcrossTables and shareAcrossFields',
                        $element,
                        $identifier,
                    );
                }
                if ($identifier === 'label') {
                    $findings[] = sprintf('%s: Content Blocks reserves "label"; the generated table breaks', $element);
                }
            }
        }

        self::assertSame([], $findings, self::report($findings, 'collections do not collide'));
    }

    #[Test]
    public function noTemplateSmugglesInBehaviourOrForeignMarkup(): void
    {
        $findings = [];
        foreach (self::elements() as $element) {
            $template = self::read($element, 'templates/frontend.html');
            if ($template === null) {
                continue;
            }

            $css = self::read($element, 'assets/frontend.css');
            if ($css !== null && trim($css) !== '' && !str_contains($template, 'f:asset.css')) {
                $findings[] = sprintf('%s: has a stylesheet the template never includes', $element);
            }
            if (preg_match('/<\s*script/i', $template) === 1) {
                $findings[] = sprintf('%s: inline <script> — behaviour belongs in astryx.js, wired by a data-g-* attribute', $element);
            }
            if (preg_match('/\sstyle\s*=\s*"[^"]*[a-z]/i', $template) === 1
                && preg_match('/\sstyle\s*=\s*"[^"]*--/', $template) !== 1
            ) {
                $findings[] = sprintf('%s: inline style that is not a custom-property hand-off', $element);
            }
            if (str_contains($template, '<d:')) {
                $findings[] = sprintf(
                    '%s: uses Desiderio d: components, which this theme does not load a stylesheet for',
                    $element,
                );
            }
            if (str_contains($template, 'TODO(astryx)')) {
                $findings[] = sprintf('%s: still carries the scaffolded TODO', $element);
            }
        }

        self::assertSame([], $findings, self::report($findings, 'a template is markup, and only markup'));
    }

    #[Test]
    public function everyElementStylesheetSpeaksOnlyInTokens(): void
    {
        $findings = [];
        foreach (self::elements() as $element) {
            $css = self::read($element, 'assets/frontend.css');
            if ($css === null) {
                continue;
            }
            $source = (string)preg_replace('#/\*[\s\S]*?\*/#', '', $css);

            // A raw colour cannot answer to a theme switch.
            if (preg_match('/(?<!var\()(#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|\boklch\()/', $source) === 1) {
                $findings[] = sprintf('%s: raw colour — use a --color-* token', $element);
            }
            // The tokens already carry both schemes. An element reaching for
            // light-dark() is deciding something the theme decides.
            if (str_contains($source, 'light-dark(')) {
                $findings[] = sprintf('%s: light-dark() belongs to the generated theme file only', $element);
            }
            if (str_contains($source, 'prefers-color-scheme')) {
                $findings[] = sprintf('%s: the scheme is carried by color-scheme on :root', $element);
            }
            if (str_contains($source, '!important')) {
                $findings[] = sprintf('%s: the cascade layers make !important unnecessary', $element);
            }

            if (preg_match_all('/font-family:\s*([^;}]+)/', $source, $fonts) > 0) {
                foreach ($fonts[1] as $value) {
                    $value = trim($value);
                    if (!str_starts_with($value, 'var(') && $value !== 'inherit') {
                        $findings[] = sprintf('%s: raw font family "%s" — use var(--font-family-*)', $element, $value);
                    }
                }
            }

            if (preg_match_all('/\(\s*(?:min|max)-width:\s*(\d+)px/', $source, $breakpoints) > 0) {
                foreach ($breakpoints[1] as $breakpoint) {
                    if (!in_array((int)$breakpoint, self::BREAKPOINTS, true)) {
                        $findings[] = sprintf('%s: non-standard breakpoint %spx', $element, $breakpoint);
                    }
                }
            }
        }

        self::assertSame(
            [],
            $findings,
            self::report($findings, 'an element stylesheet may only speak in tokens — which is exactly why one theme switch reaches all 250'),
        );
    }

    #[Test]
    public function everyElementIconKeepsTheContract(): void
    {
        $findings = [];
        foreach (self::elements() as $element) {
            $icon = self::read($element, 'assets/icon.svg');
            if ($icon === null) {
                continue;
            }
            if (!str_contains($icon, 'viewBox="0 0 16 16"')) {
                $findings[] = sprintf('%s: icon must be viewBox="0 0 16 16"', $element);
            }
            if (!str_contains($icon, 'icon-signature')) {
                $findings[] = sprintf('%s: exactly one shape must carry icon-signature', $element);
            }
            if (!str_contains($icon, '<title>')) {
                $findings[] = sprintf('%s: icon has no <title> for assistive technology', $element);
            }
        }

        self::assertSame([], $findings, self::report($findings, 'the icon contract'));
    }

    #[Test]
    public function everyElementShipsDemoContentThatParses(): void
    {
        $findings = [];
        foreach (self::elements() as $element) {
            foreach (['library.json', 'library.de.json', 'fixture.json'] as $file) {
                $raw = self::read($element, $file);
                if ($raw === null) {
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    $findings[] = sprintf('%s: %s is not valid JSON', $element, $file);
                } elseif ($decoded === []) {
                    $findings[] = sprintf('%s: %s is empty — the picker would show a blank preview', $element, $file);
                }
            }
        }

        self::assertSame([], $findings, self::report($findings, 'the element library has something to show'));
    }
}
