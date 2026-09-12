#!/usr/bin/env node
/**
 * Report what changed between two vendored Astryx manifests.
 *
 *   node Build/Scripts/diff-astryx-manifest.mjs --from HEAD~1 --to .
 *   node Build/Scripts/diff-astryx-manifest.mjs --from v1.0.2 --to . \
 *        --out Build/Reports/astryx-0.3.0-to-0.6.0.md
 *
 * `--from` and `--to` each name either a git ref (the manifests are read out of
 * that commit) or a directory containing Build/astryx/{components,tokens}.json.
 * `.` means the working tree.
 *
 * The report answers the three questions an upgrade actually raises:
 *
 *   1. Which components disappeared? Every matrix row naming one has to be
 *      remapped before the catalog regenerates.
 *   2. Which components are new, and which look like renames? A rename is
 *      guessed only when one component left and one arrived in the same group
 *      — it is offered as a candidate, never applied.
 *   3. Which tokens changed name, category or value? A token that moved
 *      category is not a break; a token that vanished is.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const MANIFESTS = ['Build/astryx/components.json', 'Build/astryx/tokens.json'];

function parseArgs(argv) {
  const args = {from: null, to: '.', out: null};
  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    const take = name => (arg.includes('=') ? arg.split('=').slice(1).join('=') : argv[++i]);
    if (arg.startsWith('--from')) args.from = take();
    else if (arg.startsWith('--to')) args.to = take();
    else if (arg.startsWith('--out')) args.out = take();
    else {
      console.error(`Unknown argument: ${arg}`);
      process.exit(1);
    }
  }
  if (!args.from) {
    console.error('Usage: diff-astryx-manifest.mjs --from <ref|dir> [--to <ref|dir>] [--out <file>]');
    process.exit(1);
  }
  return args;
}

/** Read both manifests out of a git ref or a directory. */
function readSide(spec) {
  const asDir = path.resolve(EXT_ROOT, spec);
  if (fs.existsSync(path.join(asDir, MANIFESTS[0]))) {
    return Object.fromEntries(
      MANIFESTS.map(rel => [rel, JSON.parse(fs.readFileSync(path.join(asDir, rel), 'utf8'))]),
    );
  }
  return Object.fromEntries(
    MANIFESTS.map(rel => [
      rel,
      JSON.parse(
        execFileSync('git', ['show', `${spec}:${rel}`], {
          cwd: EXT_ROOT,
          encoding: 'utf8',
          maxBuffer: 64 * 1024 * 1024,
        }),
      ),
    ]),
  );
}

const componentIndex = manifest =>
  new Map(manifest.components.map(entry => [entry.component, entry]));

/** Flatten the grouped token defaults to name -> {group, value}. */
function tokenIndex(manifest) {
  const index = new Map();
  for (const [group, tokens] of Object.entries(manifest.defaults)) {
    for (const [name, value] of Object.entries(tokens)) index.set(name, {group, value});
  }
  return index;
}

/**
 * Rename candidates: one component gone and one arrived within the same group.
 *
 * Deliberately conservative. Anything less certain than a one-for-one swap in a
 * single group is left in the added/removed lists, where a human reads it.
 */
function renameCandidates(removed, added, before, after) {
  const byGroup = (names, index) => {
    const map = new Map();
    for (const name of names) {
      const group = index.get(name).group;
      if (!map.has(group)) map.set(group, []);
      map.get(group).push(name);
    }
    return map;
  };

  const removedByGroup = byGroup(removed, before);
  const addedByGroup = byGroup(added, after);
  const pairs = [];
  for (const [group, gone] of removedByGroup) {
    const arrived = addedByGroup.get(group) ?? [];
    if (gone.length === 1 && arrived.length === 1) pairs.push({group, from: gone[0], to: arrived[0]});
  }
  return pairs;
}

const bullet = (items, empty = '_None._') =>
  items.length === 0 ? empty : items.map(line => `- ${line}`).join('\n');

// ---------------------------------------------------------------------- run

const args = parseArgs(process.argv.slice(2));
const before = readSide(args.from);
const after = readSide(args.to);

const beforeComponents = componentIndex(before[MANIFESTS[0]]);
const afterComponents = componentIndex(after[MANIFESTS[0]]);
const removedComponents = [...beforeComponents.keys()].filter(name => !afterComponents.has(name)).sort();
const addedComponents = [...afterComponents.keys()].filter(name => !beforeComponents.has(name)).sort();
const regrouped = [...afterComponents.entries()]
  .filter(([name, entry]) => beforeComponents.has(name) && beforeComponents.get(name).group !== entry.group)
  .map(([name, entry]) => `\`${name}\` — \`${beforeComponents.get(name).group}\` → \`${entry.group}\``)
  .sort();
const renames = renameCandidates(removedComponents, addedComponents, beforeComponents, afterComponents);

const beforeTokens = tokenIndex(before[MANIFESTS[1]]);
const afterTokens = tokenIndex(after[MANIFESTS[1]]);
const removedTokens = [...beforeTokens.keys()].filter(name => !afterTokens.has(name)).sort();
const addedTokens = [...afterTokens.keys()].filter(name => !beforeTokens.has(name)).sort();
const recategorised = [...afterTokens.entries()]
  .filter(([name, entry]) => beforeTokens.has(name) && beforeTokens.get(name).group !== entry.group)
  .map(([name, entry]) => `\`${name}\` — \`${beforeTokens.get(name).group}\` → \`${entry.group}\``)
  .sort();
const revalued = [...afterTokens.entries()]
  .filter(([name, entry]) => beforeTokens.has(name) && beforeTokens.get(name).value !== entry.value)
  .map(([name, entry]) => `\`${name}\`\n  - was \`${beforeTokens.get(name).value}\`\n  - now \`${entry.value}\``)
  .sort();

const beforeThemes = Object.keys(before[MANIFESTS[1]].themes);
const afterThemes = Object.keys(after[MANIFESTS[1]].themes);
const removedThemes = beforeThemes.filter(name => !afterThemes.includes(name));
const addedThemes = afterThemes.filter(name => !beforeThemes.includes(name));
const themeRuleCounts = afterThemes
  .filter(name => beforeThemes.includes(name))
  .map(name => {
    const was = before[MANIFESTS[1]].themes[name].component.length;
    const now = after[MANIFESTS[1]].themes[name].component.length;
    return was === now ? null : `\`${name}\` — ${was} → ${now} component rules`;
  })
  .filter(Boolean);

/**
 * Bare prop/state classes upstream removed in favour of data attributes.
 * Counted straight out of the compiled theme rules, so the number is upstream's
 * and not an estimate.
 */
const countSelectorShape = (manifest, pattern) => {
  let total = 0;
  for (const theme of Object.values(manifest.themes)) {
    for (const rule of theme.component) {
      const selector = rule.slice(0, rule.indexOf('{'));
      total += (selector.match(pattern) ?? []).length;
    }
  }
  return total;
};
const bareBefore = countSelectorShape(before[MANIFESTS[1]], /\.astryx-[a-z-]+\.[a-z]/g);
const bareAfter = countSelectorShape(after[MANIFESTS[1]], /\.astryx-[a-z-]+\.[a-z]/g);
const dataBefore = countSelectorShape(before[MANIFESTS[1]], /\.astryx-[a-z-]+\[data-/g);
const dataAfter = countSelectorShape(after[MANIFESTS[1]], /\.astryx-[a-z-]+\[data-/g);

const fromRelease = before[MANIFESTS[0]].release;
const toRelease = after[MANIFESTS[0]].release;

const report = `# Astryx ${fromRelease} → ${toRelease}

Generated by \`Build/Scripts/diff-astryx-manifest.mjs\`. Do not edit by hand.

| | ${fromRelease} | ${toRelease} |
| --- | --- | --- |
| commit | \`${before[MANIFESTS[0]].commit}\` | \`${after[MANIFESTS[0]].commit}\` |
| components | ${beforeComponents.size} | ${afterComponents.size} |
| base tokens | ${beforeTokens.size} | ${afterTokens.size} |
| official themes | ${beforeThemes.length} | ${afterThemes.length} |
| harvested by | ${before[MANIFESTS[0]].harvestedBy ?? '_unrecorded_'} | ${after[MANIFESTS[0]].harvestedBy ?? '_unrecorded_'} |

## Components

### Removed (${removedComponents.length})

Every matrix row naming one of these must be remapped before the catalog regenerates.

${bullet(removedComponents.map(name => `\`${name}\` (was in \`${beforeComponents.get(name).group}\`)`))}

### Added (${addedComponents.length})

${bullet(addedComponents.map(name => `\`${name}\` (\`${afterComponents.get(name).group}\`)`))}

### Rename candidates (${renames.length})

One component left and exactly one arrived in the same group. Offered, never applied.

${bullet(renames.map(pair => `\`${pair.from}\` → \`${pair.to}\` (group \`${pair.group}\`)`))}

### Regrouped (${regrouped.length})

${bullet(regrouped)}

## Tokens

### Removed (${removedTokens.length})

${bullet(removedTokens.map(name => `\`${name}\` (was \`${beforeTokens.get(name).group}\`: \`${beforeTokens.get(name).value}\`)`))}

### Added (${addedTokens.length})

${bullet(addedTokens.map(name => `\`${name}\` (\`${afterTokens.get(name).group}\`): \`${afterTokens.get(name).value}\``))}

### Moved category (${recategorised.length})

${bullet(recategorised)}

### Changed value (${revalued.length})

${bullet(revalued)}

## Themes

- removed: ${removedThemes.length === 0 ? '_none_' : removedThemes.map(n => `\`${n}\``).join(', ')}
- added: ${addedThemes.length === 0 ? '_none_' : addedThemes.map(n => `\`${n}\``).join(', ')}

### Rule-count changes (${themeRuleCounts.length})

${bullet(themeRuleCounts)}

## Selector shape

Upstream replaced bare prop/state classes with data attributes. Counted over
every compiled theme rule in each manifest.

| selector shape | ${fromRelease} | ${toRelease} |
| --- | --- | --- |
| \`.astryx-x.modifier\` | ${bareBefore} | ${bareAfter} |
| \`.astryx-x[data-…]\` | ${dataBefore} | ${dataAfter} |
`;

if (args.out) {
  const outPath = path.resolve(EXT_ROOT, args.out);
  fs.mkdirSync(path.dirname(outPath), {recursive: true});
  fs.writeFileSync(outPath, report);
  console.log(`${path.relative(EXT_ROOT, outPath)}  ${(report.length / 1024).toFixed(1)} kB`);
  console.log(
    `components +${addedComponents.length}/-${removedComponents.length}, `
    + `tokens +${addedTokens.length}/-${removedTokens.length}/~${revalued.length}`,
  );
} else {
  process.stdout.write(report);
}
