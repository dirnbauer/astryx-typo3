<?php

declare(strict_types=1);

/**
 * Audit every content element against the rules this catalog is built on.
 *
 * Two hundred and fifty elements cannot be held in anyone's head, so the rules
 * that keep them consistent are checked mechanically instead of reviewed. Each
 * finding names one element and one rule; the exit code is the gate.
 *
 *   php scripts/audit-content-elements.php            human-readable report
 *   php scripts/audit-content-elements.php --json     machine-readable
 *
 * Exits 1 when any finding is a gate (everything except the advisory checks).
 */

$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);

// Parse YAML through Symfony rather than the optional ext-yaml, so the audit
// runs on a bare PHP CLI as well as inside the container.
$autoload = dirname($root, 2) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
    fwrite(STDERR, "Symfony YAML is unavailable — run composer install in the project root.\n");
    exit(1);
}

$elementsDir = $root . '/ContentBlocks/ContentElements';
$directories = is_dir($elementsDir) ? array_values(array_filter(
    scandir($elementsDir) ?: [],
    static fn(string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($elementsDir . '/' . $entry),
)) : [];

/** Files every element must have. The set is uniform on purpose: a missing
 *  file is always a mistake, never a style. */
const REQUIRED_FILES = [
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

/** Advisory checks report but do not fail the build. */
const ADVISORY = ['css_no_responsive_rule', 'template_todo_marker'];

$findings = [];
$add = static function (string $element, string $check, string $detail) use (&$findings): void {
    $findings[] = ['element' => $element, 'check' => $check, 'detail' => $detail];
};

$seenTypeNames = [];
$seenTitles = [];

foreach ($directories as $name) {
    $dir = $elementsDir . '/' . $name;

    foreach (REQUIRED_FILES as $file) {
        if (!is_file($dir . '/' . $file)) {
            $add($name, 'missing_file', $file);
        }
    }

    $configPath = $dir . '/config.yaml';
    if (!is_file($configPath)) {
        continue;
    }
    $configText = (string)file_get_contents($configPath);
    try {
        $config = \Symfony\Component\Yaml\Yaml::parse($configText);
    } catch (\Throwable $error) {
        $add($name, 'invalid_config_yaml', $error->getMessage());
        continue;
    }
    if (!is_array($config)) {
        $add($name, 'invalid_config_yaml', 'did not parse to a mapping');
        continue;
    }

    // --- identity -----------------------------------------------------
    $expectedType = 'desiderio_grande_' . str_replace('-', '', $name);
    if (!str_contains($configText, 'typeName: ' . $expectedType)) {
        $add($name, 'typename_mismatch', 'expected typeName: ' . $expectedType);
    }
    if (!str_contains($configText, 'name: desiderio-grande/' . $name)) {
        $add($name, 'name_mismatch', 'expected name: desiderio-grande/' . $name);
    }
    if (!str_contains($configText, 'prefixFields: false')) {
        $add($name, 'missing_prefix_fields', 'prefixFields: false is required so field identifiers stay shared');
    }
    if (isset($seenTypeNames[$expectedType])) {
        $add($name, 'duplicate_ctype', 'collides with ' . $seenTypeNames[$expectedType]);
    }
    $seenTypeNames[$expectedType] = $name;

    {
        $title = (string)($config['title'] ?? '');
        if ($title === '') {
            $add($name, 'missing_title', 'config.yaml has no title');
        } elseif (isset($seenTitles[$title])) {
            $add($name, 'duplicate_title', 'same title as ' . $seenTitles[$title]);
        } else {
            $seenTitles[$title] = $name;
        }

        $description = (string)($config['description'] ?? '');
        $length = mb_strlen($description);
        if ($length < 100 || $length > 650) {
            $add($name, 'description_length', $length . ' characters (want 100-650)');
        }
        if ($description !== '' && !str_contains($description, 'Use it for:')) {
            $add($name, 'description_shape', 'no "Use it for:" clause');
        }
        if ($description !== '' && !str_contains($description, 'Prefer ')) {
            $add($name, 'description_shape', 'no "Prefer …" disambiguation against a sibling');
        }

        // A Collection without an explicit prefixField collides with the next
        // one on the same element.
        foreach ($config['fields'] ?? [] as $field) {
            if (($field['type'] ?? '') !== 'Collection') {
                continue;
            }
            if (($field['prefixField'] ?? null) !== true) {
                $add($name, 'collection_missing_prefix', (string)($field['identifier'] ?? '?'));
            }
            $shared = isset($field['foreign_table']);
            if ($shared && (($field['shareAcrossTables'] ?? null) !== true || ($field['shareAcrossFields'] ?? null) !== true)) {
                $add($name, 'shared_collection_missing_flag', (string)($field['identifier'] ?? '?'));
            }
            if (($field['identifier'] ?? '') === 'label') {
                $add($name, 'reserved_field_name', 'Content Blocks reserves "label"; the generated table breaks');
            }
        }
    }

    // --- template ------------------------------------------------------
    $templatePath = $dir . '/templates/frontend.html';
    if (is_file($templatePath)) {
        $template = (string)file_get_contents($templatePath);

        if (!str_contains($template, 'f:asset.css') && is_file($dir . '/assets/frontend.css')
            && trim((string)file_get_contents($dir . '/assets/frontend.css')) !== '') {
            $add($name, 'css_never_loaded', 'assets/frontend.css exists but the template does not include it');
        }
        if (preg_match('/<\s*script/i', $template) === 1) {
            $add($name, 'inline_script', 'behaviour belongs in grande.js, wired by a data-g-* attribute');
        }
        if (preg_match('/\sstyle\s*=\s*"[^"]*[a-z]/i', $template) === 1
            && preg_match('/\sstyle\s*=\s*"[^"]*--/', $template) !== 1) {
            $add($name, 'hardcoded_inline_style', 'inline style that is not a custom-property hand-off');
        }
        // Desiderio's d: components are styled by a stylesheet this theme does
        // not load, so its markup would render unstyled here.
        if (preg_match('/<d:/', $template) === 1) {
            $add($name, 'foreign_component_namespace', 'uses Desiderio d: components');
        }
        if (str_contains($template, 'TODO(grande)')) {
            $add($name, 'template_todo_marker', 'still carries the scaffolded TODO');
        }
    }

    // --- element stylesheet --------------------------------------------
    $cssPath = $dir . '/assets/frontend.css';
    if (is_file($cssPath)) {
        $css = (string)file_get_contents($cssPath);
        $withoutComments = (string)preg_replace('#/\*[\s\S]*?\*/#', '', $css);

        // A raw colour cannot answer to a theme; every colour must be a token.
        if (preg_match('/(?<!var\()(#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|\boklch\()/', $withoutComments) === 1) {
            $add($name, 'hardcoded_color', 'use a --color-* token');
        }
        // The tokens already carry both schemes; an element that reaches for
        // light-dark() or a media query is deciding something the theme decides.
        if (str_contains($withoutComments, 'light-dark(')) {
            $add($name, 'light_dark_in_element', 'light-dark() belongs to the generated theme file only');
        }
        if (str_contains($withoutComments, 'prefers-color-scheme')) {
            $add($name, 'color_scheme_query', 'the scheme is carried by color-scheme on :root');
        }
        if (preg_match('/font-family:\s*(?!var\()(?!inherit)/', $withoutComments) === 1) {
            $add($name, 'raw_font_family', 'use var(--font-family-*)');
        }
        if (str_contains($withoutComments, '!important')) {
            $add($name, 'important', 'the cascade layers make !important unnecessary');
        }
        // One breakpoint set across the catalog, or bands stop lining up.
        if (preg_match_all('/\(\s*(?:min|max)-width:\s*(\d+)px/', $withoutComments, $matches) > 0) {
            foreach ($matches[1] as $breakpoint) {
                if (!in_array((int)$breakpoint, [480, 639, 640, 641, 767, 768, 769, 1023, 1024, 1025], true)) {
                    $add($name, 'nonstandard_breakpoint', $breakpoint . 'px');
                }
            }
        } elseif (mb_strlen($withoutComments) > 400) {
            $add($name, 'css_no_responsive_rule', 'no media query in a stylesheet this size');
        }
    }

    // --- icon -----------------------------------------------------------
    $iconPath = $dir . '/assets/icon.svg';
    if (is_file($iconPath)) {
        $icon = (string)file_get_contents($iconPath);
        if (!str_contains($icon, 'viewBox="0 0 16 16"')) {
            $add($name, 'icon_viewbox', 'must be viewBox="0 0 16 16"');
        }
        if (!str_contains($icon, 'icon-signature')) {
            $add($name, 'icon_signature', 'exactly one shape must carry icon-signature');
        }
        if (!str_contains($icon, '<title>')) {
            $add($name, 'icon_title', 'no <title> for assistive technology');
        }
    }

    // --- demo content -----------------------------------------------------
    foreach (['library.json', 'library.de.json', 'fixture.json'] as $file) {
        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            continue;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            $add($name, 'invalid_demo_json', $file);
        } elseif ($decoded === []) {
            $add($name, 'empty_demo_content', $file . ' — the picker would show an empty preview');
        }
    }
}

// ------------------------------------------------------------------ report

$byCheck = [];
foreach ($findings as $finding) {
    $byCheck[$finding['check']][] = $finding;
}
ksort($byCheck);

$gateCount = 0;
foreach ($byCheck as $check => $items) {
    if (!in_array($check, ADVISORY, true)) {
        $gateCount += count($items);
    }
}

if ($json) {
    echo json_encode([
        'elements' => count($directories),
        'findings' => $findings,
        'summary' => array_map('count', $byCheck),
        'gating' => $gateCount,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit($gateCount > 0 ? 1 : 0);
}

printf("Audited %d elements.\n\n", count($directories));
if ($findings === []) {
    echo "No findings.\n";
    exit(0);
}

foreach ($byCheck as $check => $items) {
    $marker = in_array($check, ADVISORY, true) ? 'advisory' : 'GATE';
    printf("%-34s %3d  (%s)\n", $check, count($items), $marker);
    foreach (array_slice($items, 0, 6) as $item) {
        printf("    %-32s %s\n", $item['element'], $item['detail']);
    }
    if (count($items) > 6) {
        printf("    … and %d more\n", count($items) - 6);
    }
    echo "\n";
}

printf("%d gating finding(s).\n", $gateCount);
exit($gateCount > 0 ? 1 : 0);
