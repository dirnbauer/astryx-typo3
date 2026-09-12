#!/usr/bin/env node
/**
 * Move the component CSS from bare modifier classes to data attributes.
 *
 *   node Build/Scripts/migrate-component-css.mjs          rewrite in place
 *   node Build/Scripts/migrate-component-css.mjs --check  fail if any remain
 *   node Build/Scripts/migrate-component-css.mjs --dry-run
 *
 * `.astryx-button.primary` becomes `.astryx-button[data-variant="primary"]`,
 * which is what Astryx itself emits from v0.6.0 on. The mapping is never
 * guessed: it comes from Build/Data/component-contract.json, the same file the
 * Fluid components and the template codemod read, so a modifier cannot mean one
 * thing in the CSS and another in the markup.
 *
 * Specificity is unchanged by the move — a class and an attribute selector
 * weigh the same — so nothing in the cascade shifts. The layer order declared
 * in astryx-theme.css still decides who wins.
 *
 * Selectors whose modifier is not in the contract are left alone and listed, so
 * the gap is visible rather than silently rewritten to something plausible.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
/**
 * Every stylesheet that may name a component: the component partials, the page
 * chrome, and each content element's own sheet. An element's CSS is allowed to
 * reach for a component class, so it has to speak the same dialect.
 */
const CSS_ROOTS = [
  'Resources/Private/Css/components',
  'Resources/Private/Css/astryx',
];
const ELEMENT_CSS_GLOB = 'ContentBlocks/ContentElements';
const CONTRACT = path.join(EXT_ROOT, 'Build/Data/component-contract.json');

const args = new Set(process.argv.slice(2));
const check = args.has('--check');
const dryRun = args.has('--dry-run');

const {components} = JSON.parse(fs.readFileSync(CONTRACT, 'utf8'));

/** rootClass -> {token -> `[data-x="y"]`} */
const byRoot = new Map();
for (const entry of Object.values(components)) {
  const root = `.${entry.rootClass}`;
  const map = byRoot.get(root) ?? new Map();
  for (const [token, {attribute, value}] of Object.entries(entry.modifiers)) {
    map.set(token, `[data-${attribute}="${value}"]`);
  }
  byRoot.set(root, map);
}

/**
 * State modifiers the CSS carries that no Fluid component takes as an argument.
 *
 * These are set by astryx.js at runtime, not chosen by a template — an active
 * typeahead option, a collapsed side navigation. They stay classes on purpose:
 * a script toggling `classList` is the plainest thing that works, and there is
 * no authoring surface for them to drift from.
 */
const RUNTIME_STATE = new Set([
  'is-active', 'is-open', 'is-closed', 'is-edge', 'is-mid', 'is-compact',
  'is-link', 'is-heading', 'is-ours', 'is-reference',
  'active', 'open', 'collapsed', 'hidden', 'visible',
]);

function collectFiles() {
  const found = [];
  for (const rel of CSS_ROOTS) {
    const dir = path.join(EXT_ROOT, rel);
    if (!fs.existsSync(dir)) continue;
    for (const name of fs.readdirSync(dir).sort()) {
      if (name.endsWith('.css')) found.push(path.join(dir, name));
    }
  }
  const elements = path.join(EXT_ROOT, ELEMENT_CSS_GLOB);
  if (fs.existsSync(elements)) {
    for (const id of fs.readdirSync(elements).sort()) {
      const sheet = path.join(elements, id, 'assets/frontend.css');
      if (fs.existsSync(sheet)) found.push(sheet);
    }
  }
  return found;
}

const files = collectFiles();
const unmapped = new Map();
let rewritten = 0;
let touchedFiles = 0;

for (const file of files) {
  const before = fs.readFileSync(file, 'utf8');

  // `.astryx-root.modifier` — the modifier must directly follow a known root
  // class, so `.astryx-card .astryx-badge` (a descendant) is never touched.
  const after = before.replace(
    /(\.astryx-[a-z0-9-]+)((?:\.[a-zA-Z][a-zA-Z0-9_-]*)+)/g,
    (match, root, chain) => {
      const map = byRoot.get(root);
      if (!map) return match;

      let out = root;
      for (const token of chain.split('.').filter(Boolean)) {
        if (map.has(token)) {
          out += map.get(token);
          rewritten++;
        } else if (RUNTIME_STATE.has(token)) {
          out += `.${token}`;
        } else {
          out += `.${token}`;
          const key = `${root}.${token}`;
          unmapped.set(key, (unmapped.get(key) ?? 0) + 1);
        }
      }
      return out;
    },
  );

  if (after === before) continue;
  touchedFiles++;
  if (!dryRun && !check) fs.writeFileSync(file, after);
}

for (const [selector, count] of [...unmapped].sort((a, b) => b[1] - a[1])) {
  console.warn(`unmapped  ${selector}  (${count}x)`);
}

if (check) {
  if (rewritten > 0) {
    console.error(
      `${rewritten} bare modifier selector(s) in ${touchedFiles} file(s) are not on data `
      + 'attributes. Run: node Build/Scripts/migrate-component-css.mjs',
    );
    process.exit(1);
  }
  console.log(`component CSS clean — no bare modifier selectors (${files.length} files checked)`);
} else {
  console.log(
    `${dryRun ? 'would rewrite' : 'rewrote'} ${rewritten} selector(s) `
    + `across ${touchedFiles} of ${files.length} files`,
  );
  if (unmapped.size > 0) {
    console.log(
      `${unmapped.size} selector(s) left as classes — either add them to the contract `
      + 'or to RUNTIME_STATE in this script.',
    );
  }
}
