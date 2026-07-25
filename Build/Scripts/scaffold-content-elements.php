<?php

declare(strict_types=1);

/**
 * Turn the element matrix into Content Blocks.
 *
 * The matrix (Build/Data/matrix/<group>.json) is the source of truth; this
 * script writes everything that can be derived from it and never touches the
 * two files a human is expected to author by hand — the frontend template and
 * its stylesheet — once they exist.
 *
 *   --scaffold [--group=<id>] [--only=<element-id>]
 *       Create the per-element file set for rows that have no directory yet.
 *       Existing files are left alone, so re-running after adding rows is safe.
 *
 *   --derive
 *       Regenerate everything that is a projection of the matrix: the wizard
 *       allow-list set, the keyword and short-description catalogs in both
 *       languages, the record types, and the group manifest the site seeder
 *       reads. Idempotent, and the only way these files should ever change.
 *
 *   --check
 *       Exit non-zero if --derive would change anything. For CI, and for
 *       catching a matrix edit that never made it into the generated files.
 *
 * Per element the scaffold writes ten files:
 *
 *   config.yaml                      the Content Block definition
 *   templates/frontend.html          AUTHORED — a starting point only
 *   templates/backend-preview.fluid.html
 *   assets/frontend.css              AUTHORED — a starting point only
 *   assets/icon.svg
 *   language/labels.xlf              + de.labels.xlf
 *   library.json                     + library.de.json   picker demo content
 *   fixture.json                     showcase-page content
 */

/**
 * Identifiers an element may not declare.
 *
 * "categories" is contributed to every element by the TYPO3/Categories basic,
 * and "label" is reserved by Content Blocks — either one makes the compiled
 * definition throw at cache-flush time, far from the file that caused it.
 */
const RESERVED_IDENTIFIERS = ['categories', 'label'];

$root = dirname(__DIR__, 2);
$options = parseOptions($argv);

$fieldLibrary = readJson($root . '/Build/Data/field-library.json');
$recordTypes = readJson($root . '/Build/Data/record-types.json');
$matrix = readMatrix($root . '/Build/Data/matrix');

if ($matrix === []) {
    fwrite(STDERR, "No matrix files found in Build/Data/matrix.\n");
    exit(1);
}

$mode = $options['mode'];
if ($mode === 'scaffold') {
    scaffold($root, $matrix, $fieldLibrary, $recordTypes, $options);
    exit(0);
}

$generated = buildDerivedFiles($root, $matrix, $recordTypes);
if ($mode === 'check') {
    $stale = [];
    foreach ($generated as $path => $contents) {
        $current = is_file($path) ? (string)file_get_contents($path) : null;
        if ($current !== $contents) {
            $stale[] = substr($path, strlen($root) + 1);
        }
    }
    if ($stale !== []) {
        fwrite(STDERR, "Derived files are stale — run --derive:\n  " . implode("\n  ", $stale) . "\n");
        exit(1);
    }
    echo "Derived files are in step with the matrix.\n";
    exit(0);
}

foreach ($generated as $path => $contents) {
    writeFile($path, $contents);
}
printf("Derived %d files from %d elements.\n", count($generated), count(flatten($matrix)));
exit(0);

// ---------------------------------------------------------------- plumbing

/** @return array{mode: string, group: ?string, only: ?string} */
function parseOptions(array $argv): array
{
    $mode = null;
    $group = null;
    $only = null;
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--scaffold') {
            $mode = 'scaffold';
        } elseif ($argument === '--derive') {
            $mode = 'derive';
        } elseif ($argument === '--check') {
            $mode = 'check';
        } elseif (str_starts_with($argument, '--group=')) {
            $group = substr($argument, 8);
        } elseif (str_starts_with($argument, '--only=')) {
            $only = substr($argument, 7);
        }
    }
    if ($mode === null) {
        fwrite(STDERR, "Usage: scaffold-content-elements.php --scaffold [--group=<id>] [--only=<id>] | --derive | --check\n");
        exit(1);
    }
    return ['mode' => $mode, 'group' => $group, 'only' => $only];
}

/** @return array<string, mixed> */
function readJson(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Not valid JSON: {$path}\n");
        exit(1);
    }
    return $decoded;
}

/** @return array<string, list<array<string, mixed>>> group => rows */
function readMatrix(string $directory): array
{
    $matrix = [];
    foreach (glob($directory . '/*.json') ?: [] as $path) {
        $data = readJson($path);
        $group = is_string($data['group'] ?? null) ? $data['group'] : basename($path, '.json');
        $elements = is_array($data['elements'] ?? null) ? $data['elements'] : [];
        $matrix[$group] = array_values($elements);
    }
    ksort($matrix);
    return $matrix;
}

/** @return list<array<string, mixed>> */
function flatten(array $matrix): array
{
    $all = [];
    foreach ($matrix as $group => $rows) {
        foreach ($rows as $row) {
            $row['group'] = $group;
            $all[] = $row;
        }
    }
    usort($all, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
    return $all;
}

function writeFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0o775, true);
    }
    file_put_contents($path, $contents);
}

function cType(string $id): string
{
    return 'desiderio_grande_' . str_replace('-', '', $id);
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function yamlString(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

// ------------------------------------------------------------- derivation

/** @return array<string, string> path => contents */
function buildDerivedFiles(string $root, array $matrix, array $recordTypes): array
{
    $elements = flatten($matrix);
    $files = [];

    // The wizard allow-list. A block missing here renders but never shows up
    // in the New Content Element wizard on a site that includes this set.
    $lines = [
        '# Generated by Build/Scripts/scaffold-content-elements.php --derive. Do not edit by hand.',
        '#',
        '# Content Blocks exposes every block as a virtual site set. Listing them here',
        '# puts the New Content Element wizard into allow-list mode for sites that',
        '# include this set: they see exactly these elements (plus the native TYPO3',
        '# types), and none of the other theme\'s. An element missing from this list',
        '# renders fine but never appears in the wizard.',
        'name: webconsulting/desiderio-grande-content-elements',
        'label: "Desiderio Grande Content Elements"',
        'hidden: true',
        'dependencies:',
        '  - webconsulting/desiderio-grande',
        'optionalDependencies:',
    ];
    foreach ($elements as $element) {
        $lines[] = '  - desiderio-grande/' . $element['id'];
    }
    $files[$root . '/Configuration/Sets/DesiderioGrandeContentElements/config.yaml'] = implode("\n", $lines) . "\n";

    // Keyword chips and the search index. One unit per cType; the value packs
    // ranked chips and unshown synonyms into "a | b || syn | syn".
    foreach ([['', 'keywords', 'synonyms'], ['de.', 'keywordsDe', 'synonymsDe']] as [$prefix, $keywordKey, $synonymKey]) {
        $units = [];
        foreach ($elements as $element) {
            $keywords = implode(' | ', $element[$keywordKey] ?? []);
            $synonyms = implode(' | ', $element[$synonymKey] ?? []);
            $units[] = unit(cType($element['id']), $keywords . ' || ' . $synonyms, $prefix !== '');
        }
        $files[$root . '/Resources/Private/Language/' . $prefix . 'library_keywords.xlf'] =
            xliff('library_keywords', $units, $prefix !== '');
    }

    // The one-line blurb on a picker card.
    foreach ([['', 'short'], ['de.', 'shortDe']] as [$prefix, $key]) {
        $units = [];
        foreach ($elements as $element) {
            $units[] = unit(cType($element['id']), (string)($element[$key] ?? ''), $prefix !== '');
        }
        $files[$root . '/Resources/Private/Language/' . $prefix . 'library_short.xlf'] =
            xliff('library_short', $units, $prefix !== '');
    }

    // What the site seeder puts on each chapter page, in matrix order.
    $groups = [];
    foreach ($matrix as $group => $rows) {
        $groups[] = [
            'group' => $group,
            'elements' => array_map(
                static fn(array $row): array => ['name' => $row['title'], 'ctype' => cType($row['id'])],
                $rows,
            ),
        ];
    }
    $files[$root . '/Resources/Private/Data/grande-content-groups.json'] =
        json_encode(['groups' => $groups], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    // Shared child tables.
    foreach (collectUsedRecordTypes($elements, $recordTypes) as $key => $definition) {
        $files[$root . '/ContentBlocks/RecordTypes/' . $key . '/config.yaml'] = $definition;
    }

    return $files;
}

/** @return array<string, string> */
function collectUsedRecordTypes(array $elements, array $recordTypes): array
{
    $used = [];
    foreach ($elements as $element) {
        $recordType = $element['collection']['recordType'] ?? null;
        if (is_string($recordType)) {
            $used[$recordType][] = $element['id'];
        }
    }
    ksort($used);

    $definitions = [];
    foreach ($used as $key => $consumers) {
        $type = $recordTypes['recordTypes'][$key] ?? null;
        if ($type === null) {
            fwrite(STDERR, "Unknown record type \"{$key}\" used by: " . implode(', ', $consumers) . "\n");
            exit(1);
        }

        sort($consumers);
        $lines = [
            '# Generated by Build/Scripts/scaffold-content-elements.php --derive. Do not edit by hand.',
            '#',
            '# ' . $type['purpose'],
            '#',
            '# Shared child table: consumers reference it with foreign_table plus',
            '# shareAcrossTables/shareAcrossFields, so one table serves them all rather',
            '# than each collection generating a near-identical one.',
            '# Used by: ' . implode(', ', $consumers),
            'name: desiderio-grande/' . $key,
            'table: ' . $type['table'],
            'prefixFields: false',
            'labelField: ' . $type['labelField'],
            'fields:',
        ];
        foreach ($type['fields'] as $fieldName) {
            $definition = $recordTypes['childFields'][$fieldName] ?? null;
            if ($definition === null) {
                fwrite(STDERR, "Record type {$key} uses undefined child field \"{$fieldName}\".\n");
                exit(1);
            }
            $lines[] = '  -';
            $lines[] = '    identifier: ' . $fieldName;
            foreach ($definition as $property => $value) {
                if (str_starts_with($property, '_')) {
                    continue;
                }
                $lines[] = '    ' . $property . ': ' . formatYamlScalar($value);
            }
            $lines[] = '    label: ' . yamlString(ucfirst(str_replace('_', ' ', $fieldName)));
        }
        $definitions[$key] = implode("\n", $lines) . "\n";
    }

    return $definitions;
}

function formatYamlScalar(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return yamlString((string)$value);
}

/** @return string one <unit> */
function unit(string $id, string $value, bool $translated): string
{
    $escaped = xmlEscape($value);
    if (!$translated) {
        return "    <unit id=\"{$id}\">\n      <segment>\n        <source>{$escaped}</source>\n      </segment>\n    </unit>";
    }
    return "    <unit id=\"{$id}\">\n      <segment state=\"final\">\n        <source>{$escaped}</source>\n        <target>{$escaped}</target>\n      </segment>\n    </unit>";
}

/** @param list<string> $units */
function xliff(string $fileId, array $units, bool $translated): string
{
    $header = $translated
        ? '<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="de">'
        : '<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en">';

    return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
        . $header . "\n"
        . "  <file id=\"{$fileId}\">\n"
        . implode("\n", $units) . "\n"
        . "  </file>\n"
        . "</xliff>\n";
}

// -------------------------------------------------------------- scaffold

function scaffold(string $root, array $matrix, array $fieldLibrary, array $recordTypes, array $options): void
{
    $created = 0;
    $skipped = 0;

    foreach ($matrix as $group => $rows) {
        if ($options['group'] !== null && $options['group'] !== $group) {
            continue;
        }
        foreach ($rows as $row) {
            if ($options['only'] !== null && $options['only'] !== $row['id']) {
                continue;
            }
            $directory = $root . '/ContentBlocks/ContentElements/' . $row['id'];
            if (is_dir($directory)) {
                $skipped++;
                continue;
            }
            writeElement($directory, $row, $group, $fieldLibrary, $recordTypes);
            $created++;
            echo "  + {$row['id']}\n";
        }
    }

    printf("\nScaffolded %d element(s), %d already present.\n", $created, $skipped);
    if ($created > 0) {
        echo "Next: author templates/frontend.html and assets/frontend.css for each, then run --derive.\n";
    }
}

function writeElement(string $directory, array $row, string $group, array $fieldLibrary, array $recordTypes): void
{
    writeFile($directory . '/config.yaml', elementConfig($row, $group, $fieldLibrary, $recordTypes));
    writeFile($directory . '/templates/frontend.html', frontendTemplate($row));
    writeFile($directory . '/templates/backend-preview.fluid.html', backendPreview($row));
    writeFile($directory . '/assets/frontend.css', frontendCss($row));
    writeFile($directory . '/assets/icon.svg', icon($row));
    writeFile($directory . '/language/labels.xlf', elementLabels($row, false));
    writeFile($directory . '/language/de.labels.xlf', elementLabels($row, true));
    writeFile($directory . '/library.json', "{}\n");
    writeFile($directory . '/library.de.json', "{}\n");
    writeFile($directory . '/fixture.json', "{}\n");
}

function elementConfig(array $row, string $group, array $fieldLibrary, array $recordTypes): string
{
    $id = $row['id'];
    $lines = [
        'name: desiderio-grande/' . $id,
        // Always explicit: the element library derives the cType from this, and
        // a vendor segment that differs from the extension key would otherwise
        // produce a cType that does not match the registered one.
        'typeName: ' . cType($id),
        'title: ' . yamlString($row['title']),
        'description: ' . yamlString($row['description']),
        'group: ' . $group,
        'prefixFields: false',
        'basics:',
        '  - TYPO3/Appearance',
        '  - TYPO3/Links',
        '  - TYPO3/Categories',
        'fields:',
    ];

    foreach ($row['fields'] ?? [] as $fieldReference) {
        [$name] = explode(':', $fieldReference, 2);
        $definition = $fieldLibrary['fields'][$name] ?? null;
        if ($definition === null) {
            fwrite(STDERR, "Element {$id} uses unknown field \"{$name}\".\n");
            exit(1);
        }

        $lines[] = '  -';
        foreach ($definition as $property => $value) {
            if (str_starts_with($property, '_')) {
                continue;
            }
            $lines[] = '    ' . $property . ': ' . formatYamlScalar($value);
        }

        // Shared select vocabularies live in the field library; the element's
        // own variants come from the matrix row.
        $itemSet = $definition['_items'] ?? null;
        if ($name === 'variant') {
            $lines[] = '    label: ' . yamlString('Variant');
            $lines[] = '    items:';
            foreach ($row['variants'] ?? [] as $variant) {
                $lines[] = '      -';
                $lines[] = '        label: ' . yamlString(ucfirst(str_replace('-', ' ', (string)$variant)));
                $lines[] = '        value: ' . yamlString((string)$variant);
            }
            $lines[] = '    default: ' . yamlString((string)(($row['variants'] ?? ['default'])[0]));
        } elseif (is_string($itemSet) && isset($fieldLibrary['itemSets'][$itemSet])) {
            $lines[] = '    label: ' . yamlString(ucfirst($name));
            $lines[] = '    items:';
            foreach ($fieldLibrary['itemSets'][$itemSet] as $item) {
                $lines[] = '      -';
                $lines[] = '        label: ' . yamlString($item['label']);
                $lines[] = '        value: ' . yamlString($item['value']);
            }
            $lines[] = '    default: ' . yamlString((string)$fieldLibrary['itemSets'][$itemSet][0]['value']);
        } elseif ($itemSet === 'icon-library') {
            // Resolved at render time from the shared icon registry, so stored
            // content keeps a stable semantic key.
            $lines[] = '    label: ' . yamlString('Icon');
            $lines[] = '    itemsProcConfig:';
            $lines[] = '      itemsProcFunc: Webconsulting\\Desiderio\\DataHandling\\IconItemsProcessor';
        } elseif (!isset($definition['useExistingField'])) {
            $lines[] = '    label: ' . yamlString(ucfirst(str_replace('_', ' ', $name)));
        }
    }

    $collection = $row['collection'] ?? null;
    if (is_array($collection)) {
        $type = $recordTypes['recordTypes'][$collection['recordType']] ?? null;
        if ($type === null) {
            fwrite(STDERR, "Element {$id} uses unknown record type \"{$collection['recordType']}\".\n");
            exit(1);
        }
        $identifier = (string)($collection['identifier'] ?? 'items');
        // "categories" arrives on every element from the TYPO3/Categories basic
        // and "label" is reserved by Content Blocks itself; either one silently
        // breaks the compiled definition, so catch it here rather than at
        // cache-flush time three hundred elements later.
        if (in_array($identifier, RESERVED_IDENTIFIERS, true)) {
            fwrite(STDERR, "Element {$id}: \"{$identifier}\" is reserved and cannot name a collection.\n");
            exit(1);
        }
        $lines[] = '  -';
        $lines[] = '    identifier: ' . $identifier;
        $lines[] = '    type: Collection';
        $lines[] = '    foreign_table: ' . $type['table'];
        $lines[] = '    shareAcrossTables: true';
        $lines[] = '    shareAcrossFields: true';
        // Always prefixed: without it a second collection on the same element
        // would collide on the parent column.
        $lines[] = '    prefixField: true';
        $lines[] = '    label: ' . yamlString($collection['labelEn'] ?? 'Items');
        // Lower-case, as in TCA: content-blocks:lint rejects the camel-case
        // spelling, which is easy to write and silently unvalidated.
        //
        // A row asking for no minimum means the list is optional, and the way
        // to say that is to leave the key out — minitems: 0 is not a valid
        // lower bound.
        $minimum = (int)($collection['min'] ?? 1);
        if ($minimum >= 1) {
            $lines[] = '    minitems: ' . $minimum;
        }
        $lines[] = '    maxitems: ' . max(1, (int)($collection['max'] ?? 12));
    }

    return implode("\n", $lines) . "\n";
}

function frontendTemplate(array $row): string
{
    $id = $row['id'];
    $class = 'g-' . $id;
    $notes = $row['notes'] ?? '';

    return <<<HTML
    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:cb="http://typo3.org/ns/TYPO3/CMS/ContentBlocks/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <f:asset.css identifier="{$class}" href="{cb:assetPath()}/frontend.css"/>

    <f:comment>
        TODO(grande): {$notes}
    </f:comment>

    <section class="astryx-section {$class}" data-variant="{data.variant}">
        <div class="astryx-layout">
            <f:if condition="{data.header}">
                <h2 class="astryx-heading level-2">{data -> f:render.text(field: 'header')}</h2>
            </f:if>
        </div>
    </section>

    </html>

    HTML;
}

/**
 * The page-module card.
 *
 * It lists the element's own text fields rather than trying to look like the
 * frontend: in the page module an editor is scanning for "which one is the
 * paragraph about pricing", and a shrunken render answers that far worse than
 * the words themselves do.
 */
function backendPreview(array $row): string
{
    $title = xmlEscape($row['title']);

    // Text-bearing fields, in the order the element declares them. Files,
    // selects and checkboxes say nothing useful at this size.
    $textual = ['header', 'subheader', 'eyebrow', 'lead', 'text', 'bodytext', 'quote_text', 'author', 'role', 'value', 'price', 'cta_label', 'note', 'caption'];
    $fields = [];
    foreach ($row['fields'] ?? [] as $reference) {
        $name = explode(':', (string)$reference, 2)[0];
        if ($name !== 'header' && in_array($name, $textual, true)) {
            $fields[] = $name;
        }
    }

    $fieldMarkup = '';
    foreach ($fields as $field) {
        $label = xmlEscape(ucfirst(str_replace('_', ' ', $field)));
        $fieldMarkup .= <<<HTML

            <f:if condition="{data.{$field}}">
                <div class="g-ce-preview__field">
                    <span class="g-ce-preview__label">{$label}</span>
                    <span class="g-ce-preview__value">{data.{$field} -> f:format.stripTags() -> f:format.htmlspecialchars()}</span>
                </div>
            </f:if>
    HTML;
    }

    return <<<HTML
    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:cb="http://typo3.org/ns/TYPO3/CMS/ContentBlocks/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <f:layout name="Preview"/>

    <f:section name="Header"></f:section>

    <f:section name="Content">
        <f:asset.css identifier="grande-content-preview" href="EXT:desiderio_grande/Resources/Public/Css/content-preview.css"/>
        <div class="g-ce-preview" data-slot="card">
            <div class="g-ce-preview__meta">
                <span class="g-ce-preview__ctype">{$title}</span>
                <span class="g-ce-preview__ctype">uid {data.uid}</span>
            </div>
            <f:if condition="{data.header}">
                <h3 class="g-ce-preview__title">{data.header -> f:format.stripTags() -> f:format.htmlspecialchars()}</h3>
            </f:if>{$fieldMarkup}
        </div>
    </f:section>

    </html>

    HTML;
}

function frontendCss(array $row): string
{
    $class = 'g-' . $row['id'];

    return <<<CSS
    /*
     * {$row['title']}
     *
     * Tokens only: no raw colour, no literal font family, no light-dark() and no
     * prefers-color-scheme — the theme carries all four, and the audit enforces it.
     */

    .{$class} {
      display: block;
    }

    CSS;
}

function icon(array $row): string
{
    // Deterministic per element, so an icon does not change under a rerun.
    $seed = crc32($row['id']);
    $x = 3 + ($seed % 3);
    $y = 3 + (($seed >> 3) % 3);
    $width = 10 - (($seed >> 6) % 3);
    $title = xmlEscape($row['title']);

    return <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" class="icon-root">
      <title>{$title} icon</title>
      <style>.icon-root{color-scheme:light dark}.s{fill:currentColor}.sd{fill:currentColor;opacity:.22}.a{fill:var(--icon-color-accent)}</style>
      <rect class="sd" x="2" y="2" width="12" height="12" rx="2"/>
      <rect class="s" x="{$x}" y="{$y}" width="{$width}" height="2" rx="1"/>
      <rect class="a icon-signature" x="{$x}" y="10" width="6" height="2" rx="1"/>
    </svg>

    SVG;
}

function elementLabels(array $row, bool $translated): string
{
    $title = $translated ? $row['titleDe'] : $row['title'];
    $description = $translated ? $row['descriptionDe'] : $row['description'];

    $units = [
        unitPair('title', $row['title'], $title, $translated),
        unitPair('description', $row['description'], $description, $translated),
    ];

    return xliff('labels', $units, $translated);
}

function unitPair(string $id, string $source, string $target, bool $translated): string
{
    $sourceEscaped = xmlEscape($source);
    if (!$translated) {
        return "    <unit id=\"{$id}\">\n      <segment>\n        <source>{$sourceEscaped}</source>\n      </segment>\n    </unit>";
    }
    $targetEscaped = xmlEscape($target);
    return "    <unit id=\"{$id}\">\n      <segment state=\"final\">\n        <source>{$sourceEscaped}</source>\n        <target>{$targetEscaped}</target>\n      </segment>\n    </unit>";
}
