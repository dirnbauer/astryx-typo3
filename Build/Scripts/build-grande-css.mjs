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
    name: 'grande-components.css',
    source: 'Resources/Private/Css/components',
    // The Astryx component styles, written against the stable .astryx-* class
    // names the upstream themes target.
    layer: 'astryx-components',
  },
  {
    name: 'grande.css',
    source: 'Resources/Private/Css/grande',
    // Fonts, base typography and the page shell (header, footer, system pages).
    layer: 'astryx-chrome',
  },
];

/**
 * @font-face may not live inside a cascade layer that gets ordered after use —
 * font faces are not subject to the cascade at all, and wrapping them changes
 * nothing but risks confusion. Partials that only declare faces opt out.
 */
const UNLAYERED = new Set(['00-fonts.css']);

/**
 * Partials that belong in a different layer than their bundle's default.
 *
 * The contrast corrections restate theme tokens, so they have to sit in
 * astryx-theme: that layer is declared last and therefore beats every other,
 * and a correction emitted into astryx-chrome would be silently overruled by
 * the very theme block it is meant to correct. Within the layer, source order
 * decides, and grande.css is included after astryx-theme.css — so the
 * correction wins.
 */
const LAYER_OVERRIDES = new Map([
  ['07-contrast-overrides.css', 'astryx-theme'],
]);

function minifyCss(css) {
  return css
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/\s*([{};,])\s*/g, '$1')
    .replace(/;}/g, '}')
    .trim();
}

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
    parts.push(`@layer ${layer}{${chunks.join('')}}`);
  }

  const output = path.join(EXT_ROOT, 'Resources/Public/Css', bundle.name);
  fs.mkdirSync(path.dirname(output), {recursive: true});
  fs.writeFileSync(output, parts.join('\n'));

  const kb = (parts.join('').length / 1024).toFixed(1);
  console.log(`${bundle.name.padEnd(24)} ${String(entries.length).padStart(2)} partials  ${kb.padStart(6)} kB`);
}

process.exit(failed ? 1 : 0);
