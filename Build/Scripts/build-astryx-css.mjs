#!/usr/bin/env node
// Concatenate and minify the hand-written CSS partials into the two public
// stylesheets the site set loads.
//
// Each bundle is driven by a manifest.txt in its partial directory: one file
// name per line, concatenated in that order. Editing the order is editing the
// cascade, so it stays explicit rather than being derived from a glob.
//
// The minifier is deliberately conservative and dependency-free: it strips
// comments and collapses whitespace around `{};,` only. It never touches `:`,
// because `.frame :where(h2)` and `.frame:where(h2)` are different selectors.

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

const BUNDLES = [
  {
    name: 'astryx-components.css',
    source: 'Resources/Private/Css/components',
    // The Astryx component styles, written against the stable .astryx-* class
    // names the upstream themes target.
    layer: 'astryx-components',
  },
  {
    name: 'astryx.css',
    source: 'Resources/Private/Css/astryx',
    // Fonts, base typography and the page shell (header, footer, system pages).
    layer: 'astryx-chrome',
  },
];

/**
 * @font-face may not live inside a cascade layer that gets ordered after use —
 * font faces are not subject to the cascade at all, and wrapping them changes
 * nothing but risks confusion. Partials that only declare faces opt out.
 */
const UNLAYERED = new Set([
  '00-fonts.css',
  // The reduced-motion answer has to beat every layer, and an unlayered rule
  // does that without a single !important. See the partial's own comment.
  '09-reduced-motion.css',
]);

/**
 * Partials that belong in a different layer than their bundle's default.
 *
 * The contrast corrections restate theme tokens, so they have to sit in
 * astryx-theme: that layer is declared last and therefore beats every other,
 * and a correction emitted into astryx-chrome would be silently overruled by
 * the very theme block it is meant to correct. Within the layer, source order
 * decides, and astryx.css is included after astryx-theme.css — so the
 * correction wins.
 */
const LAYER_OVERRIDES = new Map([
  ['07-contrast-overrides.css', 'astryx-theme'],
  // An element-level reset must not outrank the components it resets, and a
  // layer beats specificity outright — so this partial is emitted into the
  // first layer rather than into the chrome layer the rest of astryx.css uses.
  ['00-reset.css', 'astryx-reset'],
]);

function minifyCss(css) {
  return css
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/\s*([{};,])\s*/g, '$1')
    .replace(/;}/g, '}')
    .trim();
}

/**
 * Which .astryx-* class names anything actually renders.
 *
 * Scanned, not assumed: every Fluid component, the 250 element templates, the
 * page and Solr templates, the element stylesheets, astryx.js, and the compiled
 * upstream theme payload. A class nobody renders is dead weight in every
 * visitor's download.
 *
 * Build/Data/css-keep-list.json is the escape hatch, with a reason per entry,
 * for names that are real but unscannable — a class a script composes at
 * runtime, or one an editor may type into a rich-text field.
 */
function collectUsedClasses() {
  const used = new Set();
  const add = text => {
    /*
     * Comments first. Every component explains itself in an <f:comment>, and
     * several name a class to say who is expected to render it — so prose
     * about `astryx-tooltip-anchor` kept the rules for a class no component
     * renders, which is precisely the state this scan exists to find.
     */
    text = text.replace(/<f:comment>[\s\S]*?<\/f:comment>/g, '').replace(/\/\*[\s\S]*?\*\//g, '');
    for (const match of text.matchAll(/astryx-[a-z0-9]+(?:-[a-z0-9]+)*(?:__[a-z0-9-]+)?/g)) {
      used.add(match[0]);
    }
  };

  const readIf = file => (fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '');
  const walk = (dir, extensions) => {
    if (!fs.existsSync(dir)) return;
    for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(full, extensions);
      else if (extensions.some(extension => entry.name.endsWith(extension))) add(fs.readFileSync(full, 'utf8'));
    }
  };

  walk(path.join(EXT_ROOT, 'Resources/Private/Components'), ['.html']);
  walk(path.join(EXT_ROOT, 'Resources/Private/Templates'), ['.html']);
  walk(path.join(EXT_ROOT, 'Resources/Private/Solr'), ['.html']);
  walk(path.join(EXT_ROOT, 'ContentBlocks'), ['.html', '.css']);
  add(readIf(path.join(EXT_ROOT, 'Resources/Public/Js/astryx.js')));
  add(readIf(path.join(EXT_ROOT, 'Resources/Private/Assets/Components.entry.js')));

  // The upstream themes style these by name. Dropping one would mean a theme
  // rule with nothing underneath it, which is worse than an unused base rule.
  const tokens = JSON.parse(readIf(path.join(EXT_ROOT, 'Build/astryx/tokens.json')) || '{"themes":{}}');
  for (const theme of Object.values(tokens.themes ?? {})) {
    for (const rule of [...(theme.component ?? []), ...(theme.prose ?? [])]) {
      add(rule.slice(0, rule.indexOf('{')));
    }
  }

  const keepListPath = path.join(EXT_ROOT, 'Build/Data/css-keep-list.json');
  const keepList = JSON.parse(readIf(keepListPath) || '{"keep":{}}');
  for (const name of Object.keys(keepList.keep ?? {})) used.add(name);

  return used;
}

/**
 * Drop every rule whose selector list names only unused .astryx-* classes.
 *
 * Rule by rule, on the minified text, with brace depth counted so a nested
 * @media or @supports block is descended into rather than eaten whole. A rule
 * is kept if ANY selector in its list survives — dropping a whole list because
 * one of its selectors died would silently unstyle the others.
 */
function treeShake(css, used, removed) {
  const out = [];
  let index = 0;

  while (index < css.length) {
    const brace = css.indexOf('{', index);
    if (brace === -1) {
      out.push(css.slice(index));
      break;
    }

    const prelude = css.slice(index, brace);
    let depth = 1;
    let cursor = brace + 1;
    while (cursor < css.length && depth > 0) {
      if (css[cursor] === '{') depth++;
      else if (css[cursor] === '}') depth--;
      cursor++;
    }
    const body = css.slice(brace + 1, cursor - 1);

    // An at-rule with a block of rules inside it: recurse, and drop the wrapper
    // only when nothing survived inside.
    if (/^\s*@(media|supports|layer|container|scope)\b/.test(prelude)) {
      const inner = treeShake(body, used, removed);
      if (inner.trim() !== '') out.push(prelude + '{' + inner + '}');
      index = cursor;
      continue;
    }
    if (/^\s*@/.test(prelude)) {
      out.push(prelude + '{' + body + '}');
      index = cursor;
      continue;
    }

    const selectors = splitSelectorList(prelude);
    const kept = selectors.filter(selector => {
      const names = [...selector.matchAll(/\.(astryx-[a-zA-Z0-9_-]+)/g)].map(match => match[1]);
      if (names.length === 0) return true; // not ours to judge
      return names.some(name => used.has(name));
    });

    if (kept.length === 0) {
      for (const selector of selectors) removed.add(selector.trim());
    } else {
      out.push(kept.join(',') + '{' + body + '}');
    }

    index = cursor;
  }

  return out.join('');
}

/** Split on top-level commas only: `:where(h1, h2)` is one selector. */
function splitSelectorList(selector) {
  const parts = [];
  let depth = 0;
  let start = 0;
  for (let i = 0; i < selector.length; i++) {
    const char = selector[i];
    if (char === '(' || char === '[') depth++;
    else if (char === ')' || char === ']') depth = Math.max(0, depth - 1);
    else if (char === ',' && depth === 0) {
      parts.push(selector.slice(start, i));
      start = i + 1;
    }
  }
  parts.push(selector.slice(start));
  return parts.map(part => part.trim()).filter(part => part !== '');
}

const shake = !process.argv.includes('--no-tree-shake');
const usedClasses = shake ? collectUsedClasses() : null;
const removedSelectors = new Set();

let failed = false;

for (const bundle of BUNDLES) {
  const sourceDir = path.join(EXT_ROOT, bundle.source);
  const manifestPath = path.join(sourceDir, 'manifest.txt');

  if (!fs.existsSync(manifestPath)) {
    console.error(`! missing manifest: ${path.relative(EXT_ROOT, manifestPath)}`);
    failed = true;
    continue;
  }

  const entries = fs
    .readFileSync(manifestPath, 'utf8')
    .split('\n')
    .map(line => line.trim())
    .filter(line => line !== '' && !line.startsWith('#'));

  const unlayered = [];
  const byLayer = new Map();

  for (const entry of entries) {
    const filePath = path.join(sourceDir, entry);
    if (!fs.existsSync(filePath)) {
      console.error(`! ${bundle.name}: manifest lists a missing file: ${entry}`);
      failed = true;
      continue;
    }
    const css = minifyCss(fs.readFileSync(filePath, 'utf8'));
    if (css === '') continue;

    if (UNLAYERED.has(entry)) {
      unlayered.push(css);
      continue;
    }
    const layer = LAYER_OVERRIDES.get(entry) ?? bundle.layer;
    if (!byLayer.has(layer)) byLayer.set(layer, []);
    byLayer.get(layer).push(css);
  }

  const parts = [...unlayered];
  for (const [layer, chunks] of byLayer) {
    const joined = chunks.join('');
    parts.push(`@layer ${layer}{${shake ? treeShake(joined, usedClasses, removedSelectors) : joined}}`);
  }

  const output = path.join(EXT_ROOT, 'Resources/Public/Css', bundle.name);
  fs.mkdirSync(path.dirname(output), {recursive: true});
  fs.writeFileSync(output, parts.join('\n'));

  const kb = (parts.join('').length / 1024).toFixed(1);
  console.log(`${bundle.name.padEnd(24)} ${String(entries.length).padStart(2)} partials  ${kb.padStart(6)} kB`);
}

if (shake && removedSelectors.size > 0) {
  const list = [...removedSelectors].sort();
  console.log(`\ntree-shaken ${list.length} selector(s) nothing renders:`);
  for (const selector of list) console.log(`  ${selector}`);
  console.log('\nTo keep one deliberately, add it with a reason to Build/Data/css-keep-list.json.');
}

process.exit(failed ? 1 : 0);
