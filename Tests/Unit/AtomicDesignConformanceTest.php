<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The atomic-design contract, enforced.
 *
 * A design system that is only a convention decays: the first template that
 * writes `class="astryx-button primary"` because it is quicker costs nothing,
 * and the two hundredth makes the system unmaintainable. Everything the 2.0
 * refactor established is asserted here instead of documented and hoped for.
 *
 * All of it reads Build/Data/component-contract.json and
 * Build/Data/component-map.json, so there is no second copy of the rules to
 * fall out of step with the first.
 */
final class AtomicDesignConformanceTest extends TestCase
{
    private const EXT_ROOT = __DIR__ . '/../..';

    private const NAMESPACE_URI = 'http://typo3.org/ns/Webconsulting/AstryxTypo3/Components/ComponentCollection';

    /**
     * Classes that may still appear in a template, and why.
     *
     * Each entry must still match something. An allowlist whose entries no
     * longer occur is an allowlist nobody has read in a year, and it will
     * eventually excuse something it was never meant to.
     */
    private const CLASS_ALLOWLIST = [
        'astryx-prose' => 'Styles the descendants of editor rich text — markup no template ever sees, '
            . 'so it is a stylesheet hook rather than a component with a call site.',
    ];

    /**
     * Which layer may use which. Brad Frost's direction, made one-way.
     *
     * @var array<string, list<string>>
     */
    private const LAYER_MAY_USE = [
        'Layout' => [],
        'Atom' => [],
        'Molecule' => ['Atom', 'Layout'],
        'Organism' => ['Atom', 'Layout', 'Molecule', 'Organism'],
    ];

    /** @var array<string, mixed> */
    private static array $contract;

    /** @var array<string, mixed> */
    private static array $map;

    public static function setUpBeforeClass(): void
    {
        self::$contract = self::readJson('Build/Data/component-contract.json');
        self::$map = self::readJson('Build/Data/component-map.json');
    }

    // ------------------------------------------------------------- fixtures

    /** @return array<string, mixed> */
    private static function readJson(string $relative): array
    {
        $path = self::EXT_ROOT . '/' . $relative;
        self::assertFileExists($path);

        return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> every template a content element, page or search result renders through */
    private static function templateFiles(): array
    {
        $files = glob(self::EXT_ROOT . '/ContentBlocks/ContentElements/*/templates/frontend.html') ?: [];
        foreach (['Templates/Pages', 'Templates/Partials/Pages', 'Templates/Layouts/Pages'] as $dir) {
            $files = [...$files, ...(glob(self::EXT_ROOT . '/Resources/Private/' . $dir . '/*.html') ?: [])];
        }
        $solr = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::EXT_ROOT . '/Resources/Private/Solr', \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($solr as $file) {
            if ($file->getExtension() === 'html') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<string> */
    private static function componentFiles(): array
    {
        return glob(self::EXT_ROOT . '/Resources/Private/Components/*/*/*.fluid.html') ?: [];
    }

    private static function relative(string $path): string
    {
        $root = realpath(self::EXT_ROOT);

        return $root === false ? $path : str_replace($root . '/', '', (string)realpath($path));
    }

    /**
     * Split a class attribute into tokens, keeping `{…}` expressions whole.
     *
     * @return list<string>
     */
    private static function classTokens(string $value): array
    {
        $tokens = [];
        $current = '';
        $depth = 0;
        foreach (str_split($value) as $char) {
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth = max(0, $depth - 1);
            }
            if ($depth === 0 && trim($char) === '') {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }
            $current .= $char;
        }
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /** @return list<array{0: string, 1: string}> every class attribute in a file, as [value, context] */
    private static function classAttributes(string $source): array
    {
        $found = [];
        if (preg_match_all('#class="([^"]*)"#s', $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $line = substr_count(substr($source, 0, $match[1]), "\n") + 1;
                $found[] = [$match[0], (string)$line];
            }
        }

        return $found;
    }

    // -------------------------------------------------------------- the rules

    #[Test]
    public function noTemplateAppliesAnAstryxClassDirectly(): void
    {
        $offences = [];
        $allowlistHits = array_fill_keys(array_keys(self::CLASS_ALLOWLIST), 0);

        foreach (self::templateFiles() as $file) {
            foreach (self::classAttributes((string)file_get_contents($file)) as [$value, $line]) {
                foreach (self::classTokens($value) as $token) {
                    if (!str_starts_with($token, 'astryx-')) {
                        continue;
                    }
                    if (array_key_exists($token, self::CLASS_ALLOWLIST)) {
                        $allowlistHits[$token]++;
                        continue;
                    }
                    $offences[] = sprintf('%s:%s  %s', self::relative($file), $line, $token);
                }
            }
        }

        self::assertSame(
            [],
            $offences,
            "A template applied an Astryx class directly instead of composing a component.\n"
            . "Run: php Build/Scripts/refactor-templates-to-components.php\n"
            . implode("\n", array_slice($offences, 0, 40))
            . (count($offences) > 40 ? sprintf("\n… and %d more", count($offences) - 40) : ''),
        );

        foreach ($allowlistHits as $class => $hits) {
            self::assertGreaterThan(
                0,
                $hits,
                sprintf(
                    'The class allowlist still excuses "%s" ("%s") but no template uses it any more. '
                    . 'Remove the entry: an allowlist nobody reads eventually excuses something it was '
                    . 'never meant to.',
                    $class,
                    self::CLASS_ALLOWLIST[$class],
                ),
            );
        }
    }

    #[Test]
    public function noTemplateCarriesABareModifierToken(): void
    {
        $modifiers = [];
        foreach (self::$contract['components'] as $component) {
            foreach (array_keys($component['modifiers']) as $token) {
                $modifiers[$token] = true;
            }
        }

        $offences = [];
        foreach (self::templateFiles() as $file) {
            foreach (self::classAttributes((string)file_get_contents($file)) as [$value, $line]) {
                foreach (self::classTokens($value) as $token) {
                    if (!isset($modifiers[$token])) {
                        continue;
                    }
                    $offences[] = sprintf('%s:%s  %s', self::relative($file), $line, $token);
                }
            }
        }

        self::assertSame(
            [],
            $offences,
            "A template used a bare modifier class. Astryx removed these at v0.6.0; they are\n"
            . "data attributes on the component now (Build/Data/component-contract.json).\n"
            . implode("\n", array_slice($offences, 0, 40)),
        );
    }

    #[Test]
    public function everyContentElementRootsInASection(): void
    {
        $offences = [];
        foreach (glob(self::EXT_ROOT . '/ContentBlocks/ContentElements/*/templates/frontend.html') ?: [] as $file) {
            $source = (string)file_get_contents($file);

            /*
             * Comments first. Three elements explain in prose why they do NOT
             * use a <time>, an <address> or an <article>, and a scan that reads
             * the comment finds the tag name in the explanation.
             */
            $source = (string)preg_replace('#<f:comment>.*?</f:comment>#s', '', $source);

            // The first tag that is neither a ViewHelper nor the <html> wrapper
            // is the element's own root. A ViewHelper before it is setting up a
            // variable, not opening the markup.
            if (!preg_match_all('#<((?:[a-z]+:)?[a-zA-Z][a-zA-Z0-9.]*)#', $source, $matches)) {
                $offences[] = self::relative($file) . '  (no tag at all)';
                continue;
            }

            $root = null;
            foreach ($matches[1] as $tag) {
                if ($tag === 'html') {
                    continue;
                }
                if (str_contains($tag, ':') && !str_starts_with($tag, 'a:')) {
                    continue;
                }
                $root = $tag;
                break;
            }

            if ($root !== 'a:layout.section') {
                $offences[] = sprintf('%s  roots in <%s>', self::relative($file), $root ?? 'nothing');
            }
        }

        self::assertSame(
            [],
            $offences,
            "Every content element roots in a:layout.section — that is what gives the catalog one\n"
            . "vertical rhythm and one surface vocabulary across 250 elements.\n"
            . implode("\n", $offences),
        );
    }

    #[Test]
    public function theLayerGraphIsOneWay(): void
    {
        $offences = [];
        foreach (self::componentFiles() as $file) {
            $layer = basename(dirname(dirname($file)));
            $allowed = self::LAYER_MAY_USE[$layer] ?? null;
            self::assertNotNull($allowed, sprintf('Unknown layer "%s" at %s', $layer, self::relative($file)));

            $source = (string)file_get_contents($file);
            if (!preg_match_all('#<a:([a-z]+)\.([a-zA-Z][a-zA-Z0-9]*)#', $source, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $usedLayer = ucfirst($match[1]);
                if ($usedLayer === $layer && $layer !== 'Organism') {
                    // A layer using itself is how Card reaches CardTitle. Allowed
                    // within a layer, never across one upwards.
                    continue;
                }
                if (!in_array($usedLayer, $allowed, true) && $usedLayer !== $layer) {
                    $offences[] = sprintf(
                        '%s (%s) uses a:%s.%s (%s)',
                        self::relative($file),
                        $layer,
                        $match[1],
                        $match[2],
                        $usedLayer,
                    );
                }
            }
        }

        self::assertSame(
            [],
            $offences,
            "The layer graph points one way: Atom and Layout import nothing, Molecule may use\n"
            . "Atom and Layout, only Organism may use everything.\n"
            . implode("\n", $offences),
        );
    }

    #[Test]
    public function everyComponentNamedInATemplateExistsOnDisk(): void
    {
        $onDisk = [];
        foreach (self::componentFiles() as $file) {
            $layer = basename(dirname(dirname($file)));
            $name = basename($file, '.fluid.html');
            $onDisk[lcfirst($layer) . '.' . lcfirst($name)] = true;
        }

        $offences = [];
        foreach ([...self::templateFiles(), ...self::componentFiles()] as $file) {
            $source = (string)file_get_contents($file);
            if (!preg_match_all('#</?a:([a-zA-Z][a-zA-Z0-9.]*)#', $source, $matches)) {
                continue;
            }
            foreach (array_unique($matches[1]) as $used) {
                if (!isset($onDisk[$used])) {
                    $offences[] = sprintf('%s  <a:%s>', self::relative($file), $used);
                }
            }
        }

        self::assertSame(
            [],
            array_values(array_unique($offences)),
            "A template names a component that is not in Resources/Private/Components/.\n"
            . implode("\n", array_unique($offences)),
        );
    }

    #[Test]
    public function everyComponentInTheContractExistsOnDisk(): void
    {
        $missing = [];
        foreach (self::$contract['components'] as $key => $component) {
            $path = sprintf(
                '%s/Resources/Private/Components/%s/%s/%s.fluid.html',
                self::EXT_ROOT,
                $component['layer'],
                $component['name'],
                $component['name'],
            );
            if (!is_file($path)) {
                $missing[] = sprintf('%s -> %s', $key, self::relative(dirname($path)));
            }
        }

        self::assertSame([], $missing, "The contract promises components that are not on disk:\n" . implode("\n", $missing));
    }

    #[Test]
    public function everyComponentDeclaresTheArgumentsItsCallSitesPass(): void
    {
        $declared = [];
        foreach (self::componentFiles() as $file) {
            $layer = basename(dirname(dirname($file)));
            $name = basename($file, '.fluid.html');
            $key = lcfirst($layer) . '.' . lcfirst($name);

            $source = (string)file_get_contents($file);
            preg_match_all('#<f:argument\s[^>]*name="([^"]+)"#', $source, $matches);
            $declared[$key] = array_flip($matches[1]);
        }

        $offences = [];
        foreach ([...self::templateFiles(), ...self::componentFiles()] as $file) {
            $source = (string)file_get_contents($file);
            if (!preg_match_all('#<a:([a-zA-Z][a-zA-Z0-9.]*)((?:\s[^>]*?)?)/?>#s', $source, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $key = $match[1];
                if (!isset($declared[$key])) {
                    continue; // reported by everyComponentNamedInATemplateExistsOnDisk
                }
                preg_match_all('#([a-zA-Z_][-a-zA-Z0-9_]*)\s*=\s*"#', $match[2], $attributes);
                foreach ($attributes[1] as $attribute) {
                    if (isset($declared[$key][$attribute])) {
                        continue;
                    }
                    $offences[] = sprintf('<a:%s %s="…">  in %s', $key, $attribute, self::relative($file));
                }
            }
        }

        $offences = array_values(array_unique($offences));
        self::assertSame(
            [],
            $offences,
            "A call site passes an attribute the component never declares with <f:argument>.\n"
            . "Fluid would silently drop it, so the markup would be right in the template and\n"
            . "wrong on the page.\n"
            . implode("\n", array_slice($offences, 0, 40))
            . (count($offences) > 40 ? sprintf("\n… and %d more", count($offences) - 40) : ''),
        );
    }

    #[Test]
    public function everyComponentRendersExactlyItsOwnRootClass(): void
    {
        $offences = [];
        foreach (self::$contract['components'] as $key => $component) {
            $path = sprintf(
                '%s/Resources/Private/Components/%s/%s/%s.fluid.html',
                self::EXT_ROOT,
                $component['layer'],
                $component['name'],
                $component['name'],
            );
            if (!is_file($path)) {
                continue; // reported by everyComponentInTheContractExistsOnDisk
            }

            $source = (string)file_get_contents($path);
            if (!str_contains($source, $component['rootClass'])) {
                $offences[] = sprintf('%s never renders its root class %s', $key, $component['rootClass']);
            }
        }

        self::assertSame([], $offences, implode("\n", $offences));
    }

    #[Test]
    public function everyMatrixAstryxReferenceIsMapped(): void
    {
        $mapped = self::$map['components'];
        $inventory = [];
        foreach (self::readJson('Build/astryx/components.json')['components'] as $component) {
            $inventory[$component['component']] = true;
        }

        $unmapped = [];
        $unknownUpstream = [];
        foreach (glob(self::EXT_ROOT . '/Build/Data/matrix/*.json') ?: [] as $file) {
            $rows = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $rows = $rows['elements'] ?? $rows;
            foreach ($rows as $row) {
                foreach ($row['astryx'] ?? [] as $name) {
                    if (!isset($mapped[$name])) {
                        $unmapped[] = sprintf('%s (row %s)', $name, $row['id'] ?? '?');
                    }
                    if (!isset($inventory[$name])) {
                        $unknownUpstream[] = sprintf('%s (row %s)', $name, $row['id'] ?? '?');
                    }
                }
            }
        }

        self::assertSame(
            [],
            array_values(array_unique($unmapped)),
            "A matrix row names an Astryx component that Build/Data/component-map.json does not map.\n"
            . implode("\n", array_unique($unmapped)),
        );
        self::assertSame(
            [],
            array_values(array_unique($unknownUpstream)),
            'A matrix row names an Astryx component that is not in the vendored inventory for '
            . self::readJson('Build/astryx/components.json')['release'] . ".\n"
            . implode("\n", array_unique($unknownUpstream)),
        );
    }

    #[Test]
    public function theComponentMapPointsAtRealComponents(): void
    {
        $offences = [];
        foreach (self::$map['components'] as $upstream => $entry) {
            if (!isset(self::$contract['components'][$entry['component']])) {
                $offences[] = sprintf('%s -> %s is not in the contract', $upstream, $entry['component']);
                continue;
            }
            $expected = self::$contract['components'][$entry['component']]['rootClass'];
            if ($entry['rootClass'] !== $expected) {
                $offences[] = sprintf(
                    '%s claims root class %s, the contract says %s',
                    $upstream,
                    $entry['rootClass'],
                    $expected,
                );
            }
        }

        self::assertSame([], $offences, implode("\n", $offences));
    }

    #[Test]
    public function everyTemplateUsingComponentsDeclaresTheNamespace(): void
    {
        $offences = [];
        foreach (self::templateFiles() as $file) {
            $source = (string)file_get_contents($file);
            if (!str_contains($source, '<a:')) {
                continue;
            }
            if (!str_contains($source, self::NAMESPACE_URI)) {
                $offences[] = self::relative($file);
            }
        }

        self::assertSame(
            [],
            $offences,
            "A template composes a: components without declaring the namespace. Fluid renders the\n"
            . "tag as literal text rather than failing, so this is invisible until someone looks at\n"
            . "the page source.\n"
            . implode("\n", $offences),
        );
    }

    #[Test]
    public function theComponentCssHasNoBareModifierSelectorLeft(): void
    {
        $modifiers = [];
        foreach (self::$contract['components'] as $component) {
            foreach ($component['modifiers'] as $token => $_) {
                $modifiers['.' . $component['rootClass'] . '.' . $token] = true;
            }
        }

        $sheets = glob(self::EXT_ROOT . '/Resources/Private/Css/components/*.css') ?: [];
        $sheets = [...$sheets, ...(glob(self::EXT_ROOT . '/Resources/Private/Css/astryx/*.css') ?: [])];
        $sheets = [...$sheets, ...(glob(self::EXT_ROOT . '/ContentBlocks/ContentElements/*/assets/frontend.css') ?: [])];

        $offences = [];
        foreach ($sheets as $sheet) {
            $source = (string)file_get_contents($sheet);
            if (!preg_match_all('#\.astryx-[a-z0-9-]+\.[a-zA-Z][a-zA-Z0-9_-]*#', $source, $matches)) {
                continue;
            }
            foreach (array_unique($matches[0]) as $selector) {
                if (isset($modifiers[$selector])) {
                    $offences[] = sprintf('%s  %s', self::relative($sheet), $selector);
                }
            }
        }

        self::assertSame(
            [],
            $offences,
            "A stylesheet still selects a modifier as a class. Run:\n"
            . "  node Build/Scripts/migrate-component-css.mjs\n"
            . implode("\n", array_slice($offences, 0, 30)),
        );
    }

    /** @return array<string, array{0: string}> */
    public static function layerProvider(): array
    {
        return [
            'Layout' => ['Layout'],
            'Atom' => ['Atom'],
            'Molecule' => ['Molecule'],
            'Organism' => ['Organism'],
        ];
    }

    #[Test]
    #[DataProvider('layerProvider')]
    public function theContractAndTheDiskAgreeAboutWhatIsInEachLayer(string $layer): void
    {
        $expected = [];
        foreach (self::$contract['components'] as $component) {
            if ($component['layer'] === $layer) {
                $expected[] = $component['name'];
            }
        }
        sort($expected);

        $actual = [];
        foreach (glob(self::EXT_ROOT . '/Resources/Private/Components/' . $layer . '/*/*.fluid.html') ?: [] as $file) {
            $actual[] = basename($file, '.fluid.html');
        }
        sort($actual);

        self::assertSame(
            $expected,
            $actual,
            sprintf(
                "The %s layer on disk does not match the contract.\nOnly in the contract: %s\nOnly on disk: %s",
                $layer,
                implode(', ', array_diff($expected, $actual)) ?: '—',
                implode(', ', array_diff($actual, $expected)) ?: '—',
            ),
        );
    }
}
