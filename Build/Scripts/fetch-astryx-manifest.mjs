#!/usr/bin/env node
/**
 * Harvest the vendored Astryx manifest from an official upstream release.
 *
 *   node Build/Scripts/fetch-astryx-manifest.mjs --tag v0.6.0
 *
 * Writes Build/astryx/components.json and Build/astryx/tokens.json, both
 * carrying `release`, `commit` and `harvestedBy` so a reader can tell exactly
 * which upstream artefact produced them.
 *
 * What "harvest" means here, precisely:
 *
 *   components.json  `astryx component --list --json` from @astryxdesign/cli at
 *                    the same version, flattened to one row per component. The
 *                    CLI groups components by category; the category becomes the
 *                    row's `group`. This is upstream's own inventory, not a
 *                    reading of the source tree — if the CLI is unavailable the
 *                    script falls back to parsing `export * from './X'` lines in
 *                    packages/core/src/index.ts and marks the file
 *                    `harvestedBy: "source-exports"` so the difference is never
 *                    silent.
 *
 *   tokens.json      `tokenDefaults` and the per-category `*Defaults` maps from
 *                    @astryxdesign/core/theme for the base block, and
 *                    `generateThemeRulesSplit(theme)` for each of the seven
 *                    official theme packages. Exactly what upstream's own theme
 *                    compiler emits — this extension only re-scopes the
 *                    selectors later, in build-astryx-theme.mjs.
 *
 * Nothing here is hand-edited afterwards. The source tarball is downloaded to
 * var/astryx/ (git-ignored) so the tag's tree can be inspected and so the
 * resolved commit SHA is the tag's, not a branch head's.
 *
 * Requires network access and npm. Offline, the pinned files stay as they are.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT_DIR = path.join(EXT_ROOT, 'Build/astryx');
const WORK_ROOT = path.join(EXT_ROOT, 'var/astryx');

const REPO = 'facebook/astryx';
const LICENSE = 'MIT — Copyright (c) 2026 Meta Platforms, Inc.';

/**
 * The official theme packages, in the order they should appear in the payload.
 * Kept as a literal because it is a claim about upstream that must fail loudly
 * when it stops being true, rather than quietly harvesting a shorter list.
 */
const THEME_PACKAGES = [
  'neutral',
  'butter',
  'chocolate',
  'matcha',
  'stone',
  'gothic',
  'y2k',
];

/**
 * Token categories of the base block, in payload order, each naming the
 * `*Defaults` export(s) from @astryxdesign/core/theme it is built from.
 *
 * `color` merges the brand/semantic colours with the domain tokens (data
 * visualisation ramps plus the syntax palette) because that is one namespace
 * for a consumer: they are all `--color-*` and they are all resolved the same
 * way.
 */
const TOKEN_GROUPS = [
  ['color', ['colorDefaults', 'domainTokenDefaults']],
  ['spacing', ['spacingDefaults']],
  ['size', ['sizeDefaults']],
  ['radius', ['radiusDefaults']],
  ['shadow', ['shadowDefaults']],
  ['border', ['borderDefaults']],
  ['focus', ['focusDefaults']],
  ['duration', ['durationDefaults']],
  ['ease', ['easeDefaults']],
  ['typography', ['typographyDefaults']],
  ['textSize', ['textSizeDefaults']],
  ['fontWeight', ['fontWeightDefaults']],
  ['typeScale', ['typeScaleDefaults']],
];

// --------------------------------------------------------------------- args

function parseArgs(argv) {
  const args = {tag: null, keepWork: false};
  for (const arg of argv) {
    if (arg.startsWith('--tag=')) args.tag = arg.slice('--tag='.length);
    else if (arg === '--tag') args.tagNext = true;
    else if (arg === '--keep-work') args.keepWork = true;
    else if (args.tagNext) {
      args.tag = arg;
      args.tagNext = false;
    } else {
      fail(`Unknown argument: ${arg}`);
    }
  }
  if (!args.tag) fail('Usage: fetch-astryx-manifest.mjs --tag v0.6.0');
  if (!/^v\d+\.\d+\.\d+$/.test(args.tag)) {
    fail(`--tag must be an upstream release tag like v0.6.0, got "${args.tag}"`);
  }
  return args;
}

function fail(message) {
  console.error(message);
  process.exit(1);
}

function run(command, commandArgs, options = {}) {
  return execFileSync(command, commandArgs, {
    encoding: 'utf8',
    maxBuffer: 256 * 1024 * 1024,
    stdio: ['ignore', 'pipe', 'inherit'],
    ...options,
  });
}

// -------------------------------------------------------------------- steps

/**
 * Resolve the tag to the commit it points at.
 *
 * `git ls-remote` rather than the GitHub REST API: the API refuses anonymous
 * callers after sixty requests an hour, and a harvest that fails on a 403 in
 * the first second is a harvest nobody runs twice. The `^{}` row is the peeled
 * commit behind an annotated tag; a lightweight tag has only the plain row.
 */
function resolveTagCommit(tag) {
  const listing = run('git', ['ls-remote', '--tags', `https://github.com/${REPO}.git`, tag, `${tag}^{}`]);
  const rows = new Map(
    listing.trim().split('\n').filter(Boolean).map(line => {
      const [sha, ref] = line.split(/\s+/);
      return [ref, sha];
    }),
  );
  const commit = rows.get(`refs/tags/${tag}^{}`) ?? rows.get(`refs/tags/${tag}`);
  if (!commit) fail(`No tag ${tag} at https://github.com/${REPO}`);
  return commit;
}

/** Download and unpack the tag's source tarball into var/astryx/. */
async function fetchSource(tag) {
  fs.mkdirSync(WORK_ROOT, {recursive: true});
  const tarball = path.join(WORK_ROOT, `astryx-${tag}.tar.gz`);
  const extracted = path.join(WORK_ROOT, `astryx-${tag.replace(/^v/, '')}`);

  if (!fs.existsSync(extracted)) {
    if (!fs.existsSync(tarball)) {
      process.stderr.write(`downloading ${tag} source…\n`);
      const response = await fetch(`https://codeload.github.com/${REPO}/tar.gz/refs/tags/${tag}`);
      if (!response.ok) fail(`Source tarball download failed: HTTP ${response.status}`);
      fs.writeFileSync(tarball, Buffer.from(await response.arrayBuffer()));
    }
    run('tar', ['-xzf', tarball, '-C', WORK_ROOT]);
  }

  if (!fs.existsSync(extracted)) fail(`Expected ${extracted} after unpacking ${tag}`);
  return extracted;
}

/**
 * Install the release's own packages into a throwaway workspace.
 *
 * The published packages are used rather than the source tree because the token
 * defaults and the theme rule compiler are built artefacts: reading the
 * TypeScript would mean re-implementing upstream's build, and re-implementing it
 * is precisely the thing a vendored manifest exists to avoid.
 */
function installRelease(version) {
  const workspace = path.join(WORK_ROOT, `harvest-${version}`);
  fs.mkdirSync(workspace, {recursive: true});
  if (!fs.existsSync(path.join(workspace, 'package.json'))) {
    fs.writeFileSync(
      path.join(workspace, 'package.json'),
      JSON.stringify({name: 'astryx-harvest', private: true, type: 'module'}, null, 2) + '\n',
    );
  }

  const packages = [
    `@astryxdesign/core@${version}`,
    `@astryxdesign/cli@${version}`,
    ...THEME_PACKAGES.map(slug => `@astryxdesign/theme-${slug}@${version}`),
  ];

  process.stderr.write(`installing ${packages.length} upstream packages at ${version}…\n`);
  run('npm', ['install', '--no-audit', '--no-fund', '--silent', ...packages], {cwd: workspace});
  return workspace;
}

/** Upstream's own component inventory, via the CLI. */
function harvestComponents(workspace) {
  const cli = path.join(workspace, 'node_modules/.bin/astryx');
  if (!fs.existsSync(cli)) return null;

  let payload;
  try {
    payload = JSON.parse(run(cli, ['component', '--list', '--json'], {cwd: workspace}));
  } catch {
    return null;
  }
  if (payload.error || !payload.data?.components) return null;

  const components = [];
  for (const [group, entries] of Object.entries(payload.data.components)) {
    for (const entry of entries) {
      components.push({
        component: entry.name,
        name: entry.name,
        group,
        package: entry.package,
      });
    }
  }
  components.sort((a, b) => a.component.localeCompare(b.component));
  return components;
}

/**
 * Fallback inventory: the `export * from './X'` lines of the core entry point.
 *
 * Deterministic, but coarser than the CLI's — a directory that exports several
 * components (Avatar exports AvatarGroup, AvatarStatusDot, …) yields one row.
 * The payload records `harvestedBy: "source-exports"` so this is never mistaken
 * for the real inventory.
 */
function harvestComponentsFromSource(sourceRoot) {
  const indexPath = path.join(sourceRoot, 'packages/core/src/index.ts');
  if (!fs.existsSync(indexPath)) fail(`No CLI inventory and no ${indexPath} to fall back to.`);

  const seen = new Set();
  const components = [];
  for (const line of fs.readFileSync(indexPath, 'utf8').split('\n')) {
    const match = line.match(/^export \* from '\.\/([A-Za-z0-9_]+)'/);
    if (!match || seen.has(match[1])) continue;
    seen.add(match[1]);
    components.push({
      component: match[1],
      name: match[1],
      group: match[1],
      package: '@astryxdesign/core',
    });
  }
  components.sort((a, b) => a.component.localeCompare(b.component));
  return components;
}

/** Base token defaults plus the seven official themes' compiled rule sets. */
async function harvestTokens(workspace) {
  const script = path.join(workspace, 'harvest-tokens.mjs');
  fs.writeFileSync(script, `
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const theme = await import('@astryxdesign/core/theme');
const groups = ${JSON.stringify(TOKEN_GROUPS)};

const defaults = {};
for (const [name, exports] of groups) {
  const merged = {};
  for (const key of exports) {
    if (!theme[key]) throw new Error('Upstream no longer exports ' + key);
    Object.assign(merged, theme[key]);
  }
  defaults[name] = merged;
}

const themes = {};
for (const slug of ${JSON.stringify(THEME_PACKAGES)}) {
  const pkg = await import('@astryxdesign/theme-' + slug);
  const key = Object.keys(pkg).find(k => /Theme$/.test(k));
  if (!key) throw new Error('No *Theme export in @astryxdesign/theme-' + slug);
  const {component, prose} = theme.generateThemeRulesSplit(pkg[key]);
  themes[slug] = {component, prose};
}

process.stdout.write(JSON.stringify({defaults, themes}));
`);

  return JSON.parse(run(process.execPath, [script], {cwd: workspace}));
}

// ---------------------------------------------------------------------- run

const args = parseArgs(process.argv.slice(2));
const version = args.tag.replace(/^v/, '');

const commit = resolveTagCommit(args.tag);
const sourceRoot = await fetchSource(args.tag);
const workspace = installRelease(version);

let components = harvestComponents(workspace);
let componentsHarvestedBy = `@astryxdesign/cli@${version} component --list --json`;
if (!components) {
  process.stderr.write('CLI inventory unavailable — falling back to core source exports.\n');
  components = harvestComponentsFromSource(sourceRoot);
  componentsHarvestedBy = 'source-exports';
}

const tokens = await harvestTokens(workspace);

fs.mkdirSync(OUT_DIR, {recursive: true});

fs.writeFileSync(
  path.join(OUT_DIR, 'components.json'),
  JSON.stringify(
    {
      source: `https://github.com/${REPO}/releases/tag/${args.tag}`,
      release: args.tag,
      commit,
      harvestedBy: componentsHarvestedBy,
      harvested: components.length,
      note:
        'Exact component inventory from the official release. Runtime React APIs are '
        + 'documentation provenance only and are not shipped by this TYPO3 extension.',
      components,
    },
    null,
    2,
  ) + '\n',
);

fs.writeFileSync(
  path.join(OUT_DIR, 'tokens.json'),
  JSON.stringify(
    {
      release: args.tag,
      commit,
      harvestedBy:
        `@astryxdesign/core@${version} theme exports (tokenDefaults groups) `
        + '+ generateThemeRulesSplit() per official theme package',
      defaults: tokens.defaults,
      themes: tokens.themes,
      source: `https://github.com/${REPO}/releases/tag/${args.tag}`,
      license: LICENSE,
      note:
        `Generated exclusively from the official @astryxdesign/* ${version} release packages `
        + 'with generateThemeRulesSplit(). No canary or moving branch input.',
    },
    null,
    2,
  ) + '\n',
);

if (!args.keepWork) {
  fs.rmSync(path.join(workspace, 'harvest-tokens.mjs'), {force: true});
}

const tokenCount = Object.values(tokens.defaults).reduce((sum, group) => sum + Object.keys(group).length, 0);
console.log(`${args.tag} @ ${commit}`);
console.log(`components.json  ${components.length} components  (${componentsHarvestedBy})`);
console.log(`tokens.json      ${tokenCount} base tokens, ${Object.keys(tokens.themes).length} themes`);
