#!/usr/bin/env node
/**
 * Expand this extension's own theme seeds into Astryx-shaped theme payloads.
 *
 * Astryx ships seven themes and no more — verified against packages/themes/ in
 * the upstream repository. The thirteen in Build/Data/grande-themes.json are
 * ours. They are not a different mechanism: each one comes out of here in
 * exactly the structure Astryx's own generateThemeRulesSplit() produces, with
 * the same token names, so every component and every element stylesheet treats
 * them identically to Meta's.
 *
 * How a seed becomes a theme:
 *
 *   1. Take the neutral theme's rule set as the structural template. Every rule
 *      except the token block references tokens rather than values, so cloning
 *      them keeps the component behaviour identical and lets a theme's whole
 *      personality live in its tokens — which is what a token-driven system is
 *      supposed to mean.
 *   2. Replace the token block. Brand tokens come from the seed; everything
 *      else — the nine categorical hue ramps, the status colours, the syntax
 *      palette — is inherited from neutral, because those are semantic
 *      categories rather than brand decisions, and inheriting them keeps the
 *      contrast behaviour the audit already verified.
 *
 * Output is merged into the vendored payload by build-astryx-theme.mjs. The
 * vendored file itself is never touched: it records an upstream commit and has
 * to keep meaning exactly that.
 *
 *   node Build/Scripts/build-grande-themes.mjs
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const UPSTREAM = path.join(EXT_ROOT, 'Build/astryx/tokens.json');
const SEEDS = path.join(EXT_ROOT, 'Build/Data/grande-themes.json');
const OUT = path.join(EXT_ROOT, 'Build/Data/grande-themes.generated.json');

/** The template theme: its rules define the shape every theme is poured into. */
const TEMPLATE = 'neutral';

/** Tokens a seed decides. Everything else is inherited from the template. */
const BRAND_TOKENS = new Set([
  '--color-accent', '--color-accent-muted', '--color-neutral',
  '--color-background-surface', '--color-background-body', '--color-background-muted',
  '--color-background-card', '--color-background-popover', '--color-background-inverted',
  '--color-overlay', '--color-overlay-hover', '--color-overlay-pressed',
  '--color-text-primary', '--color-text-secondary', '--color-text-disabled', '--color-text-accent',
  '--color-on-dark', '--color-on-light', '--color-on-accent',
  '--color-icon-accent', '--color-icon-primary', '--color-icon-secondary', '--color-icon-disabled',
  '--color-border', '--color-border-emphasized',
  '--color-skeleton', '--color-shadow',
  '--font-family-heading', '--font-family-body',
  '--radius-inner', '--radius-element', '--radius-container',
]);

const upstream = JSON.parse(fs.readFileSync(UPSTREAM, 'utf8'));
const {themes: seeds} = JSON.parse(fs.readFileSync(SEEDS, 'utf8'));
const template = upstream.themes[TEMPLATE];
if (!template) {
  console.error(`The template theme "${TEMPLATE}" is not in the vendored payload.`);
  process.exit(1);
}

// ------------------------------------------------------------------ colour

const rgb = hex => {
  const h = hex.replace('#', '');
  return [0, 2, 4].map(i => parseInt(h.slice(i, i + 2), 16));
};
const hex = ([r, g, b]) =>
  '#' + [r, g, b].map(c => Math.max(0, Math.min(255, Math.round(c))).toString(16).padStart(2, '0')).join('');
const mix = (a, b, t) => rgb(a).map((c, i) => c + (rgb(b)[i] - c) * t);
/** Alpha as the two-hex-digit suffix Astryx's own tokens use. */
const alpha = (colour, percent) =>
  colour + Math.round((percent / 100) * 255).toString(16).padStart(2, '0').toUpperCase();

/** The token block for one seed, as `--name: light-dark(a, b);` lines. */
function brandTokens(seed) {
  const {ink, mid, soft, paper} = seed.core;
  const dark = seed.dark;
  const white = '#FFFFFF';

  // In dark mode the roles swap: the soft tint becomes the accent and the
  // ink becomes what sits on top of it.
  return {
    '--color-accent': [ink, soft],
    '--color-accent-muted': [alpha(ink, 8), alpha(soft, 12)],
    '--color-neutral': [alpha(ink, 6), alpha(soft, 10)],

    '--color-background-surface': [white, dark.surface],
    '--color-background-body': [paper, dark.body],
    '--color-background-muted': [paper, ink],
    '--color-background-card': [white, dark.card],
    '--color-background-popover': [white, dark.card],
    '--color-background-inverted': [ink, soft],

    '--color-overlay': [alpha(ink, 50), alpha(ink, 80)],
    '--color-overlay-hover': [alpha(ink, 5), alpha(soft, 5)],
    '--color-overlay-pressed': [alpha(ink, 10), alpha(soft, 10)],

    '--color-text-primary': [ink, soft],
    '--color-text-secondary': [mid, hex(mix(soft, dark.body, 0.25))],
    '--color-text-disabled': [soft, hex(mix(soft, dark.body, 0.55))],
    '--color-text-accent': [ink, soft],

    '--color-on-dark': [white, white],
    '--color-on-light': [ink, ink],
    '--color-on-accent': [white, ink],

    '--color-icon-accent': [ink, soft],
    '--color-icon-primary': [ink, soft],
    '--color-icon-secondary': [mid, hex(mix(soft, dark.body, 0.25))],
    '--color-icon-disabled': [soft, hex(mix(soft, dark.body, 0.55))],

    '--color-border': [hex(mix(paper, ink, 0.14)), alpha(soft, 10)],
    '--color-border-emphasized': [hex(mix(paper, ink, 0.32)), hex(mix(soft, dark.body, 0.55))],

    '--color-skeleton': [hex(mix(paper, ink, 0.18)), hex(mix(soft, dark.body, 0.6))],
    '--color-shadow': [alpha(ink, 10), '#0000004D'],

    '--font-family-heading': [`"${seed.fonts.heading}", Georgia, "Times New Roman", Times, serif`],
    '--font-family-body': [`"${seed.fonts.body}", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`],

    // A radius scale, not a single value: inner sits inside element, container
    // outside it, so a card never has a tighter corner than the button in it.
    '--radius-inner': [`calc(${seed.radius} * 0.5)`],
    '--radius-element': [seed.radius],
    '--radius-container': [`calc(${seed.radius} * 1.5)`],
  };
}

/** Render one token as the declaration Astryx would have emitted. */
function declaration(name, value) {
  if (value.length === 1) return `    ${name}: ${value[0]};`;
  const [light, dark] = value;
  return light === dark ? `    ${name}: ${light};` : `    ${name}: light-dark(${light}, ${dark});`;
}

/**
 * Rewrite the template's `:scope { … }` block with this seed's tokens.
 *
 * Order is preserved from the template so a diff between two themes shows the
 * values that differ and nothing else.
 */
function tokenBlock(seed, templateBlock) {
  const brand = brandTokens(seed);
  const seen = new Set();

  const body = templateBlock
    .split('\n')
    .map(line => {
      const match = line.match(/^\s*(--[a-z0-9-]+):/);
      if (!match) return line;
      const name = match[1];
      if (!BRAND_TOKENS.has(name)) return line;
      seen.add(name);
      return declaration(name, brand[name]);
    })
    .join('\n');

  // A brand token the template does not declare is inherited from the base
  // :root — which means it would keep the NEUTRAL value on our theme. That is
  // wrong for anything brand-coloured (an inverted toast surface, for one), so
  // the missing declarations are appended rather than dropped.
  const missing = [...BRAND_TOKENS].filter(name => !seen.has(name));
  if (missing.length === 0) return body;

  const closing = body.lastIndexOf('}');
  return body.slice(0, closing)
    + missing.map(name => declaration(name, brand[name])).join('\n') + '\n'
    + body.slice(closing);
}

// -------------------------------------------------------------------- run

const generated = {};
for (const seed of seeds) {
  if (upstream.themes[seed.name]) {
    console.error(`"${seed.name}" is an upstream Astryx theme — pick another name.`);
    process.exit(1);
  }

  generated[seed.name] = {
    prose: template.prose,
    component: template.component.map(rule =>
      rule.includes(':scope') ? tokenBlock(seed, rule) : rule
    ),
  };
}

fs.writeFileSync(OUT, JSON.stringify({
  _comment: 'GENERATED by Build/Scripts/build-grande-themes.mjs — edit Build/Data/grande-themes.json instead.',
  themes: generated,
  meta: Object.fromEntries(seeds.map(s => [s.name, {label: s.label, description: s.description, family: 'webconsulting'}])),
}, null, 2) + '\n');

// One registry for the whole extension. Before this existed the theme list was
// repeated in nine files — two build scripts, the contrast audit, the token
// library, the TCA field, the site settings, the overview partial and the
// seeder — and adding a theme meant remembering all of them.
const REGISTRY = path.join(EXT_ROOT, 'Build/Data/theme-registry.json');
const upstreamMeta = JSON.parse(fs.readFileSync(path.join(EXT_ROOT, 'Build/Data/upstream-theme-meta.json'), 'utf8'));

const registry = [
  ...upstreamMeta.map(entry => ({...entry, family: 'astryx'})),
  ...seeds.map(seed => ({
    id: seed.name,
    name: seed.label,
    character: seed.description,
    use: seed.use ?? '',
    family: 'webconsulting',
  })),
];

fs.writeFileSync(REGISTRY, JSON.stringify({
  _comment: 'GENERATED by Build/Scripts/build-grande-themes.mjs. The single source of truth for which themes exist.',
  themes: registry,
}, null, 2) + '\n');

console.log(`${Object.keys(generated).length} themes generated: ${Object.keys(generated).join(', ')}`);
console.log(`registry: ${registry.length} themes (${registry.filter(t => t.family === 'astryx').length} from Astryx, ${registry.filter(t => t.family === 'webconsulting').length} ours)`);
