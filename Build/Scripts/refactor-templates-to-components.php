<?php

declare(strict_types=1);

/**
 * Rewrite Fluid templates from raw `astryx-*` classes to `a:` components.
 *
 *   php Build/Scripts/refactor-templates-to-components.php --dry-run
 *   php Build/Scripts/refactor-templates-to-components.php --only=accordion-faq
 *   php Build/Scripts/refactor-templates-to-components.php --group=hero
 *   php Build/Scripts/refactor-templates-to-components.php --pages --solr
 *
 * `<section class="astryx-section g-x {data.tone}">` becomes
 * `<a:layout.section class="g-x" surface="{data.tone}">`, and its matching
 * `</section>` becomes `</a:layout.section>`.
 *
 * Why this is a regex and not a parser
 * ------------------------------------
 * A Fluid template is not XML and will not survive being treated as XML.
 * `<f:if>` around half an opening tag, `{f:if(condition: '{a} || {b}')}` inside
 * an attribute, `<f:for>` wrapping a `<li>` that closes in a different branch —
 * DOMDocument either refuses these or silently repairs them into something else.
 * So the rewrite is deliberately narrow: it matches ONE opening tag at a time by
 * its class attribute, then finds that tag's own closing tag by counting depth
 * over the same tag name. Anything it is not certain about, it leaves alone and
 * reports, which is the only safe failure mode for a codemod over 279 files.
 *
 * Order matters. Passes run outer-first — section and layout before grid and
 * split, those before card and item, those before heading and text, and the
 * inline atoms last — because rewriting an inner tag first would leave the
 * outer tag's depth count looking at tags that no longer exist.
 *
 * Everything it knows about modifiers comes from
 * Build/Data/component-contract.json. This script invents no mapping of its own.
 */
const EXT_ROOT = __DIR__ . '/../..';

/**
 * Passes, outer-first. Each entry is a list of component keys from the contract.
 *
 * A component not listed here is never rewritten automatically — Organisms, for
 * instance, are ported by hand because their markup carries JavaScript hooks and
 * site settings that no regex should be trusted with.
 */
const PASSES = [
    'structure' => ['layout.section', 'layout.container'],
    'arrangement' => ['layout.grid', 'layout.split', 'layout.stack', 'layout.center'],
    'blocks' => [
        'molecule.card', 'molecule.clickableCard', 'molecule.cardTitle', 'molecule.cardBody',
        'molecule.cardFooter', 'molecule.item', 'molecule.itemLabel', 'molecule.itemContent',
        'molecule.itemDescription', 'molecule.feature', 'molecule.list', 'molecule.metadataList',
        'molecule.metadataListItem', 'molecule.blockquote', 'molecule.citation',
        'molecule.banner', 'molecule.collapsible', 'molecule.collapsibleGroup',
        'molecule.table', 'molecule.carousel', 'molecule.overlay', 'molecule.emptyState',
        'molecule.overflowList', 'molecule.codeBlock', 'molecule.outline', 'molecule.figure',
        'molecule.buttonGroup', 'molecule.toolbar', 'molecule.pagination', 'molecule.tabList',
        'molecule.segmentedControl', 'molecule.dialog', 'molecule.field',
        'molecule.textInput', 'molecule.inputGroup', 'molecule.tooltip',
        'molecule.stepper', 'molecule.step', 'molecule.treeList', 'molecule.breadcrumbs',
    ],
    'typography' => ['atom.heading', 'atom.text', 'atom.eyebrow', 'atom.badge'],
    'inline' => [
        'atom.button', 'atom.iconButton', 'atom.link', 'atom.avatar', 'atom.divider',
        'atom.token', 'atom.statusDot', 'atom.timestamp', 'atom.visuallyHidden',
        'atom.thumbnail', 'atom.aspectRatio', 'atom.progressBar', 'atom.kbd',
        'atom.spinner', 'atom.skeleton', 'atom.icon',
    ],
];

/**
 * Classes that stay classes, with the reason. Mirrored by the allowlist in
 * Tests/Unit/AtomicDesignConformanceTest.php.
 */
const CLASS_ALLOWLIST = [
    'astryx-prose' => 'Wraps editor rich text. It styles descendants the template never sees, so it is a stylesheet hook rather than a component.',
];

/**
 * A bare `{data.foo}` in a class attribute is a modifier chosen by an editor.
 * Which modifier it is cannot be read off the expression, so the field names the
 * catalog actually uses are listed here, per component where they differ.
 *
 * Anything not listed is left in place and reported, never guessed.
 */
const DYNAMIC_FIELD_ATTRIBUTES = [
    '*' => [
        'tone' => 'surface',
        'width' => 'size',
        'align' => 'align',
        'variant' => 'variant',
        'columns' => 'cols',
        'size' => 'size',
        'level' => 'level',
        'status' => 'status',
    ],
    'layout.container' => ['width' => 'size', 'align' => 'align'],
    'layout.grid' => ['columns' => 'cols', 'align' => 'align'],
];

/** Tag name -> heading level, so `<h3 class="astryx-heading">` keeps its level. */
const HEADING_LEVELS = ['h1' => '1', 'h2' => '2', 'h3' => '3', 'h4' => '4', 'h5' => '5', 'h6' => '6'];

/** Elements with no closing tag. */
const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

const NAMESPACE_URI = 'http://typo3.org/ns/Webconsulting/AstryxTypo3/Components/ComponentCollection';

/**
 * An opening tag, HTML or ViewHelper.
 *
 * ViewHelper tags have to be in scope because 199 call sites render a button or
 * a link as `<f:link.typolink class="astryx-button primary lg">`: the class is
 * on the ViewHelper, not on an <a> this script could otherwise see. Those become
 * `<a:atom.button variant="primary" size="lg" parameter="…">`, and the component
 * does the typolink itself.
 */
const TAG_NAME = '#<([a-zA-Z][a-zA-Z0-9]*(?::[a-zA-Z][a-zA-Z0-9.]*)?)#';

/**
 * Attributes that may travel from the original tag onto the component tag.
 *
 * The map is closed on purpose. Fluid REJECTS an argument a component does not
 * declare - ComponentAdapter::validateAdditionalArguments throws - so carrying
 * an attribute the component never heard of does not degrade, it takes the page
 * down. The hyphenated ARIA attributes are renamed to the camelCase spelling the
 * components declare.
 *
 * A tag carrying anything outside this map is left alone and reported. That
 * loses a few conversions and costs nothing but a line in the residue list,
 * which is the right trade against a fatal at render time.
 */
const CARRIED_ATTRIBUTES = [
    'id' => 'id',
    'name' => 'name',
    'href' => 'href',
    'target' => 'target',
    'type' => 'type',
    'parameter' => 'parameter',
    'title' => 'title',
    'datetime' => 'datetime',
    'value' => 'value',
    'open' => 'open',
    'for' => 'for',
    'lang' => 'lang',
    'dir' => 'dir',
    'aria-label' => 'ariaLabel',
    'aria-labelledby' => 'ariaLabelledby',
    'aria-describedby' => 'ariaDescribedby',
    'aria-current' => 'ariaCurrent',
    'aria-expanded' => 'ariaExpanded',
    'aria-controls' => 'ariaControls',
    'aria-hidden' => 'ariaHidden',
    /*
     * Three data attributes appear on 80 element roots because they ARE
     * component decisions an editor makes - how the section aligns, how many
     * columns it runs, which variant it wears - that 1.x wrote straight onto the
     * tag. They become declared arguments and the component renders them back as
     * the same data attribute, so the element's own CSS keeps working unchanged.
     */
    'data-align' => 'align',
    'data-columns' => 'columns',
    'data-variant' => 'variant',
];

// --------------------------------------------------------------------- args

$options = getopt('', ['dry-run', 'only:', 'group:', 'pages', 'solr', 'elements', 'pass:', 'quiet']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$onlyPass = $options['pass'] ?? null;

$contract = json_decode(
    (string)file_get_contents(EXT_ROOT . '/Build/Data/component-contract.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

/** @var array<string, array{key: string, tag: string, modifiers: array<string, array{attribute: string, value: string}>}> */
$byRootClass = [];
foreach ($contract['components'] as $key => $component) {
    $byRootClass[$component['rootClass']] = [
        'key' => $key,
        'tag' => 'a:' . $key,
        'modifiers' => $component['modifiers'],
    ];
}

// ------------------------------------------------------------------- files

/** @return list<string> */
function collectFiles(array $options): array
{
    $wantElements = isset($options['elements']) || isset($options['only']) || isset($options['group']);
    $wantPages = isset($options['pages']);
    $wantSolr = isset($options['solr']);
    if (!$wantElements && !$wantPages && !$wantSolr) {
        $wantElements = $wantPages = $wantSolr = true;
    }

    $files = [];

    if ($wantElements) {
        $ids = null;
        if (isset($options['only'])) {
            $ids = array_map('trim', explode(',', (string)$options['only']));
        } elseif (isset($options['group'])) {
            $matrix = EXT_ROOT . '/Build/Data/matrix/' . $options['group'] . '.json';
            if (!is_file($matrix)) {
                fwrite(STDERR, "No matrix group file: {$matrix}\n");
                exit(1);
            }
            $rows = json_decode((string)file_get_contents($matrix), true, 512, JSON_THROW_ON_ERROR);
            $rows = $rows['elements'] ?? $rows;
            $ids = array_column($rows, 'id');
        }

        foreach (glob(EXT_ROOT . '/ContentBlocks/ContentElements/*/templates/frontend.html') ?: [] as $file) {
            $id = basename(dirname(dirname($file)));
            if ($ids !== null && !in_array($id, $ids, true)) {
                continue;
            }
            $files[] = $file;
        }
    }

    if ($wantPages) {
        foreach (['Templates/Pages', 'Templates/Partials/Pages', 'Templates/Layouts/Pages'] as $dir) {
            foreach (glob(EXT_ROOT . '/Resources/Private/' . $dir . '/*.html') ?: [] as $file) {
                $files[] = $file;
            }
        }
    }

    if ($wantSolr) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(EXT_ROOT . '/Resources/Private/Solr', FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'html') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);
    return $files;
}

// ------------------------------------------------------------- tokenising

/**
 * Split a class attribute into tokens, keeping `{…}` expressions whole.
 *
 * `{f:if(condition: '{a} || {b}', then: 'x')}` contains both spaces and nested
 * braces; splitting it on whitespace would produce nonsense, so brace depth is
 * tracked and only top-level whitespace separates tokens.
 *
 * @return list<string>
 */
function splitClassTokens(string $value): array
{
    $tokens = [];
    $current = '';
    $depth = 0;
    $length = strlen($value);

    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth = max(0, $depth - 1);
        }

        if ($depth === 0 && ($char === ' ' || $char === "\t" || $char === "\n")) {
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

/**
 * Find the byte offset just past the matching closing tag for an opening tag.
 *
 * Counts depth over the same tag name rather than trusting indentation, and
 * ignores self-closing occurrences of it. Returns null when the tag does not
 * close in this file, which is what happens when a template opens a `<div>` in
 * one `<f:if>` branch and closes it in another — a real pattern, and one that
 * must be left to a human.
 */
function findClosingTag(string $source, string $tagName, int $afterOpeningTag): ?array
{
    $depth = 1;
    $offset = $afterOpeningTag;
    $openPattern = '#<' . preg_quote($tagName, '#') . '(?=[\s/>])#i';
    $closePattern = '#</' . preg_quote($tagName, '#') . '\s*>#i';

    while (true) {
        $nextOpen = preg_match($openPattern, $source, $openMatch, PREG_OFFSET_CAPTURE, $offset)
            ? $openMatch[0][1] : null;
        $nextClose = preg_match($closePattern, $source, $closeMatch, PREG_OFFSET_CAPTURE, $offset)
            ? $closeMatch[0][1] : null;

        if ($nextClose === null) {
            return null;
        }

        if ($nextOpen !== null && $nextOpen < $nextClose) {
            $end = endOfOpeningTag($source, $nextOpen);
            if ($end === null) {
                return null;
            }
            // A self-closing occurrence never needs a closing tag of its own.
            if (!str_ends_with(rtrim(substr($source, $nextOpen, $end - $nextOpen), '>'), '/')) {
                $depth++;
            }
            $offset = $end;
            continue;
        }

        $depth--;
        $end = $nextClose + strlen($closeMatch[0][0]);
        if ($depth === 0) {
            return ['start' => $nextClose, 'end' => $end];
        }
        $offset = $end;
    }
}

/**
 * The byte offset just past an opening tag that starts at $start.
 *
 * Not a regex, because an attribute value may contain a `>` and regularly does:
 * `style="--value: {f:if(condition: '{a} > 50', …)}"`. The first version stopped
 * at that `>` and wrote half a tag into the file. Quotes are tracked instead, so
 * only a `>` outside a quoted value ends the tag.
 */
function endOfOpeningTag(string $source, int $start): ?int
{
    $length = strlen($source);
    $quote = null;

    for ($i = $start; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === '"' || $char === "'") {
            $quote = $char;
            continue;
        }
        if ($char === '>') {
            return $i + 1;
        }
    }

    return null;
}

/**
 * Parse an opening tag's attributes into name => raw value.
 *
 * @return array<string, string>
 */
function parseAttributes(string $tag): array
{
    $attributes = [];
    if (preg_match_all('#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*"([^"]*)"#s', $tag, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }
    }
    return $attributes;
}

// -------------------------------------------------------------- rewriting

final class Residue
{
    /** @var array<string, list<string>> */
    public array $entries = [];

    public function add(string $file, string $reason): void
    {
        $this->entries[$file][] = $reason;
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->entries));
    }
}

/**
 * Turn one class attribute into a component tag's attribute list.
 *
 * @param array{key: string, tag: string, modifiers: array<string, array{attribute: string, value: string}>} $component
 * @return array{attributes: array<string, string>, leftover: list<string>, unresolved: list<string>}
 */
function mapClassTokens(array $component, array $tokens, string $rootClass, string $tagName): array
{
    $attributes = [];
    $leftover = [];
    $unresolved = [];
    $modifiers = $component['modifiers'];
    $fieldMap = DYNAMIC_FIELD_ATTRIBUTES[$component['key']] ?? DYNAMIC_FIELD_ATTRIBUTES['*'];

    foreach ($tokens as $token) {
        if ($token === $rootClass) {
            continue;
        }

        // A static modifier the contract knows.
        if (isset($modifiers[$token])) {
            $attributes[$modifiers[$token]['attribute']] = $modifiers[$token]['value'];
            continue;
        }

        // `cols-{data.columns}` — a contract prefix with a dynamic tail.
        if (preg_match('#^([a-z][a-z0-9]*(?:-[a-z0-9]+)*?)-(\{.+\})$#s', $token, $match)) {
            $prefix = $match[1];
            $sample = $prefix . '-1';
            $attribute = $modifiers[$sample]['attribute'] ?? null;
            if ($attribute === null) {
                foreach ($modifiers as $name => $modifier) {
                    if (str_starts_with($name, $prefix . '-')) {
                        $attribute = $modifier['attribute'];
                        break;
                    }
                }
            }
            if ($attribute !== null) {
                $attributes[$attribute] = $match[2];
                continue;
            }
        }

        // `{f:if(condition: x, then: 'tight')}` — the literal names the modifier,
        // and the modifier names the attribute. No table needed.
        if (preg_match('#^\{f:if\(.*?then:\s*\'([a-z0-9-]+)\'(?:\s*,\s*else:\s*\'([a-z0-9-]*)\')?\s*\)\}$#s', $token, $match)) {
            $then = $match[1];
            $else = $match[2] ?? '';
            $attribute = $modifiers[$then]['attribute'] ?? null;
            if ($attribute !== null && ($else === '' || ($modifiers[$else]['attribute'] ?? null) === $attribute)) {
                $elseValue = $else === '' ? 'default' : $modifiers[$else]['value'];
                $condition = (string)preg_replace('#^\{f:if\(condition:\s*(.*?),\s*then:.*$#s', '$1', $token);
                $attributes[$attribute] = sprintf(
                    '{f:if(condition: %s, then: \'%s\', else: \'%s\')}',
                    $condition,
                    $modifiers[$then]['value'],
                    $elseValue,
                );
                continue;
            }
        }

        // A bare `{data.tone}` — the field name says which modifier it carries.
        if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)\}$#', $token, $match)) {
            $attribute = $fieldMap[$match[2]] ?? null;
            if ($attribute !== null) {
                $attributes[$attribute] = $token;
                continue;
            }
        }

        // Not a modifier at all: an element's own BEM class, or an allowlisted one.
        if (!str_starts_with($token, 'astryx-') && !str_contains($token, '{')) {
            $leftover[] = $token;
            continue;
        }
        if (array_key_exists($token, CLASS_ALLOWLIST)) {
            $leftover[] = $token;
            continue;
        }

        $leftover[] = $token;
        $unresolved[] = $token;
    }

    // A heading with no explicit level takes it from the tag it was written as.
    if ($component['key'] === 'atom.heading' && !isset($attributes['level']) && isset(HEADING_LEVELS[$tagName])) {
        $attributes['level'] = HEADING_LEVELS[$tagName];
    }

    // Text and the other polymorphic atoms keep the element they were authored as.
    if (in_array($component['key'], ['atom.text', 'atom.eyebrow', 'atom.badge', 'atom.visuallyHidden'], true)
        && !in_array($tagName, ['p', 'span'], true)
    ) {
        $attributes['as'] = $tagName;
    } elseif (in_array($component['key'], ['atom.text', 'atom.eyebrow', 'atom.badge', 'atom.visuallyHidden'], true)
        && $tagName === 'span'
    ) {
        $attributes['as'] = 'span';
    }

    return ['attributes' => $attributes, 'leftover' => $leftover, 'unresolved' => $unresolved];
}

/** Render a component tag's attributes, contract order first, then the rest. */
function renderAttributes(array $attributes): string
{
    $out = '';
    foreach ($attributes as $name => $value) {
        if ($value === '') {
            continue;
        }
        $out .= sprintf(' %s="%s"', $name, $value);
    }
    return $out;
}

/**
 * Run one pass over one file's source.
 *
 * @param array<string, array> $byRootClass
 * @return array{source: string, rewrites: int}
 */
function runPass(string $source, array $componentKeys, array $byRootClass, string $file, Residue $residue): array
{
    $wanted = [];
    foreach ($byRootClass as $rootClass => $component) {
        if (in_array($component['key'], $componentKeys, true)) {
            $wanted[$rootClass] = $component;
        }
    }
    if ($wanted === []) {
        return ['source' => $source, 'rewrites' => 0];
    }

    $rewrites = 0;
    $offset = 0;

    while (preg_match(TAG_NAME, $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $tagStart = $match[0][1];
        $tagName = $match[1][0];

        $tagEnd = endOfOpeningTag($source, $tagStart);
        if ($tagEnd === null) {
            break;
        }
        $tagText = substr($source, $tagStart, $tagEnd - $tagStart);
        $htmlTagName = strtolower($tagName);
        $selfClosing = str_ends_with(rtrim(substr($tagText, 0, -1)), '/');

        if (str_starts_with($tagName, 'a:')) {
            $offset = $tagEnd;
            continue;
        }

        $attributes = parseAttributes($tagText);
        $classValue = $attributes['class'] ?? null;
        if ($classValue === null) {
            $offset = $tagEnd;
            continue;
        }

        $tokens = splitClassTokens($classValue);
        $rootClass = $tokens[0] ?? '';
        if (!isset($wanted[$rootClass])) {
            $offset = $tagEnd;
            continue;
        }

        $component = $wanted[$rootClass];

        if ($selfClosing || in_array($htmlTagName, VOID_ELEMENTS, true)) {
            $residue->add($file, "{$rootClass} on a void/self-closing <{$tagName}> — left for hand-finishing");
            $offset = $tagEnd;
            continue;
        }

        $closing = findClosingTag($source, $tagName, $tagEnd);
        if ($closing === null) {
            $residue->add($file, "{$rootClass} on <{$tagName}> whose closing tag is not in this file");
            $offset = $tagEnd;
            continue;
        }

        $mapped = mapClassTokens($component, $tokens, $rootClass, $htmlTagName);
        foreach ($mapped['unresolved'] as $token) {
            $residue->add($file, "{$rootClass}: could not map class token `{$token}`");
        }

        $carried = [];
        $unmappable = [];
        foreach ($attributes as $name => $value) {
            if ($name === 'class') {
                continue;
            }
            if (!isset(CARRIED_ATTRIBUTES[$name])) {
                $unmappable[] = $name;
                continue;
            }
            $carried[CARRIED_ATTRIBUTES[$name]] = $value;
        }

        if ($unmappable !== []) {
            $residue->add(
                $file,
                sprintf(
                    '%s on <%s> carries %s, which no component declares — left for hand-finishing',
                    $rootClass,
                    $tagName,
                    implode(', ', $unmappable),
                ),
            );
            $offset = $tagEnd;
            continue;
        }

        $componentAttributes = $mapped['attributes'];
        $leftoverClass = implode(' ', $mapped['leftover']);
        if ($leftoverClass !== '') {
            $componentAttributes['class'] = $leftoverClass;
        }
        foreach ($carried as $name => $value) {
            $componentAttributes[$name] = $value;
        }

        $openTag = '<' . $component['tag'] . renderAttributes($componentAttributes) . '>';
        $closeTag = '</' . $component['tag'] . '>';

        $source = substr($source, 0, $closing['start'])
            . $closeTag
            . substr($source, $closing['end']);
        $source = substr($source, 0, $tagStart)
            . $openTag
            . substr($source, $tagEnd);

        $rewrites++;
        $offset = $tagStart + strlen($openTag);
    }

    return ['source' => $source, 'rewrites' => $rewrites];
}

/** Make sure the file declares the `a` namespace on its root <html> tag. */
function ensureNamespace(string $source, string $file, Residue $residue): string
{
    if (str_contains($source, NAMESPACE_URI)) {
        return $source;
    }
    if (preg_match('#<html\b[^>]*>#s', $source, $match, PREG_OFFSET_CAPTURE)) {
        $tag = $match[0][0];
        $replacement = (string)preg_replace(
            '#(\s*)(data-namespace-typo3-fluid="true")#',
            '$1xmlns:a="' . NAMESPACE_URI . '"$1$2',
            $tag,
            1,
        );
        if ($replacement === $tag) {
            $replacement = substr($tag, 0, -1) . "\n      xmlns:a=\"" . NAMESPACE_URI . '">';
        }
        return substr($source, 0, $match[0][1]) . $replacement . substr($source, $match[0][1] + strlen($tag));
    }

    $residue->add($file, 'no <html> tag to declare xmlns:a on');
    return $source;
}

// ---------------------------------------------------------------------- run

$files = collectFiles($options);
$residue = new Residue();
$totalRewrites = 0;
$changedFiles = 0;
$perPass = array_fill_keys(array_keys(PASSES), 0);

foreach ($files as $file) {
    $original = (string)file_get_contents($file);
    $source = $original;

    foreach (PASSES as $passName => $componentKeys) {
        if ($onlyPass !== null && $onlyPass !== $passName) {
            continue;
        }
        $result = runPass($source, $componentKeys, $byRootClass, $file, $residue);
        $source = $result['source'];
        $perPass[$passName] += $result['rewrites'];
        $totalRewrites += $result['rewrites'];
    }

    if ($source !== $original) {
        $source = ensureNamespace($source, $file, $residue);
        $changedFiles++;
        if (!$dryRun) {
            file_put_contents($file, $source);
        }
    }
}

$relative = static fn(string $path): string => (string)preg_replace('#^' . preg_quote(realpath(EXT_ROOT) ?: '', '#') . '/#', '', realpath($path) ?: $path);

if (!$quiet && $residue->entries !== []) {
    echo "Residue for hand-finishing:\n";
    foreach ($residue->entries as $file => $reasons) {
        $unique = array_count_values($reasons);
        echo '  ' . $relative($file) . "\n";
        foreach ($unique as $reason => $count) {
            echo '      ' . $reason . ($count > 1 ? " ({$count}x)" : '') . "\n";
        }
    }
    echo "\n";
}

printf(
    "%s %d tag(s) across %d of %d file(s)\n",
    $dryRun ? 'Would rewrite' : 'Rewrote',
    $totalRewrites,
    $changedFiles,
    count($files),
);
foreach ($perPass as $passName => $count) {
    printf("  %-12s %d\n", $passName, $count);
}
printf("Residue: %d item(s) in %d file(s)\n", $residue->count(), count($residue->entries));
