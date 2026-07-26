#!/usr/bin/env node
/**
 * Measure every theme against the contrast requirements of WCAG 2.2 AA.
 *
 * Seven themes × two colour schemes is fourteen palettes, and a person cannot
 * hold fourteen palettes in their head — so the pairs that have to pass are
 * listed once here and checked arithmetically.
 *
 * What this can and cannot tell you:
 *
 *   1.4.3 Contrast (Minimum) — text 4.5:1, large text 3:1. Checked.
 *   1.4.11 Non-text Contrast — UI boundaries, focus rings, meaningful
 *          graphics 3:1 against what sits behind them. Checked.
 *   1.4.1 Use of Colour, 2.4.7 Focus Visible, 2.4.11 Focus Not Obscured —
 *          structural, not arithmetic. NOT checked here; they are reviewed in
 *          the templates.
 *
 * Translucent tokens are composited over the surface they sit on before being
 * measured, because that is what a reader's eye receives. Measuring the raw
 * rgba() would flatter every muted background in the catalog.
 *
 *   node Build/Scripts/audit-contrast.mjs           report, exit 1 on any AA failure
 *   node Build/Scripts/audit-contrast.mjs --all     also list the passes
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const CSS = path.join(EXT_ROOT, 'Resources/Public/Css/astryx-theme.css');

const THEMES = ['neutral', 'butter', 'chocolate', 'matcha', 'stone', 'gothic', 'y2k'];
const HUES = ['red', 'orange', 'yellow', 'green', 'teal', 'cyan', 'blue', 'purple', 'pink'];

const showAll = process.argv.includes('--all');

// ---------------------------------------------------------------- colour

/** @returns {[number, number, number, number]|null} r,g,b 0-255 and alpha 0-1 */
function parseColor(value) {
  const input = value.trim();

  const hex = input.match(/^#([0-9a-f]{3,8})$/i);
  if (hex) {
    let h = hex[1];
    if (h.length === 3 || h.length === 4) h = [...h].map(c => c + c).join('');
    const n = p => parseInt(h.slice(p, p + 2), 16);
    return [n(0), n(2), n(4), h.length === 8 ? n(6) / 255 : 1];
  }

  const rgb = input.match(/^rgba?\(([^)]+)\)$/i);
  if (rgb) {
    const parts = rgb[1].split(/[,/]/).map(p => p.trim());
    const channel = p => (p.endsWith('%') ? Math.round(parseFloat(p) * 2.55) : parseFloat(p));
    return [channel(parts[0]), channel(parts[1]), channel(parts[2]), parts[3] === undefined ? 1 : parseFloat(parts[3])];
  }

  return null;
}

/** Paint a possibly translucent colour onto an opaque one. */
function composite(fg, bg) {
  if (fg[3] >= 1) return fg;
  return [0, 1, 2].map(i => Math.round(fg[i] * fg[3] + bg[i] * (1 - fg[3]))).concat(1);
}

function luminance([r, g, b]) {
  const channel = v => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function contrast(fg, bg) {
  const a = luminance(fg);
  const b = luminance(bg);
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

// ------------------------------------------------------------ the tokens

const css = fs.readFileSync(CSS, 'utf8');

/** @returns {Record<string,string>} token => raw value, from the first matching block */
function blockTokens(selectorPattern) {
  const match = css.match(selectorPattern);
  if (!match) return {};
  const out = {};
  for (const [, name, value] of match[1].matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/g)) {
    out[name] = value.trim();
  }
  return out;
}

const base = blockTokens(/:root\s*\{([\s\S]*?)\n\s*\}/);
const themeTokens = {};
for (const theme of THEMES) {
  const pattern = new RegExp(`\\[data-astryx-theme="${theme}"\\]\\s*\\{([\\s\\S]*?)\\n\\s*\\}`);
  themeTokens[theme] = {...base, ...blockTokens(pattern)};
}

/** Resolve a token for one theme and scheme, following var() and light-dark(). */
function resolve(theme, name, scheme, seen = new Set()) {
  if (seen.has(name)) return null;
  seen.add(name);

  let value = themeTokens[theme][name];
  if (!value) return null;

  const ref = value.match(/^var\(\s*(--[a-z0-9-]+)\s*(?:,\s*([^)]+))?\)$/);
  if (ref) {
    return resolve(theme, ref[1], scheme, seen) ?? (ref[2] ? parseColor(ref[2]) : null);
  }

  const pair = value.match(/^light-dark\(\s*([^,]+),\s*(.+)\s*\)$/);
  if (pair) value = (scheme === 'dark' ? pair[2] : pair[1]).trim();

  return parseColor(value);
}

// ------------------------------------------------------------- the pairs

/**
 * Every pair the interface actually puts on screen.
 *
 * `min` is the ratio WCAG 2.2 AA requires for that role: 4.5 for body text,
 * 3 for large text and for non-text boundaries.
 */
function pairs(scheme) {
  const list = [
    ['body text', '--color-text-primary', '--color-background-body', 4.5],
    ['body text on surface', '--color-text-primary', '--color-background-surface', 4.5],
    ['body text on card', '--color-text-primary', '--color-background-card', 4.5],
    ['body text on muted', '--color-text-primary', '--color-background-muted', 4.5],
    ['secondary text', '--color-text-secondary', '--color-background-body', 4.5],
    ['secondary text on surface', '--color-text-secondary', '--color-background-surface', 4.5],
    ['secondary text on card', '--color-text-secondary', '--color-background-card', 4.5],
    ['link text', '--color-text-accent', '--color-background-body', 4.5],
    ['link text on surface', '--color-text-accent', '--color-background-surface', 4.5],
    ['primary button label', '--color-on-accent', '--color-accent', 4.5],
    ['popover text', '--color-text-primary', '--color-background-popover', 4.5],

    // An inverted surface flips with the scheme: it is dark on a light page and
    // light on a dark one, so the text on it flips too. Pairing it with
    // --color-on-dark in both schemes measures white on near-white and reports
    // a failure that no component would ever render.
    ['text on inverted', scheme === 'dark' ? '--color-on-light' : '--color-on-dark', '--color-background-inverted', 4.5],

    // Status colours are never text on the page canvas — every component that
    // uses them puts them on the matching muted tint. Measuring them against
    // the body background asks a question the CSS never poses.
    ['success on its tint', '--color-success', '--color-success-muted', 4.5],
    ['warning on its tint', '--color-warning', '--color-warning-muted', 4.5],
    ['error on its tint', '--color-error', '--color-error-muted', 4.5],

    // 1.4.11: a boundary or a focus ring is a graphical object, not text.
    ['focus ring on page', '--color-accent', '--color-background-body', 3],
    ['focus ring on surface', '--color-accent', '--color-background-surface', 3],
    ['icon on page', '--color-icon-primary', '--color-background-body', 3],
    ['secondary icon', '--color-icon-secondary', '--color-background-body', 3],
  ];

  // Badges: each hue's text on its own background. Nine per theme, and they are
  // the most likely place for a palette to slip.
  for (const hue of HUES) {
    list.push([`${hue} badge`, `--color-text-${hue}`, `--color-background-${hue}`, 4.5]);
  }

  return list;
}

// ------------------------------------------------------------------- run

const findings = [];
const passes = [];
let checked = 0;

for (const theme of THEMES) {
  for (const scheme of ['light', 'dark']) {
    // Everything translucent is composited onto the page canvas, which is what
    // sits behind a surface in practice.
    const canvas = resolve(theme, '--color-background-body', scheme) ?? [255, 255, 255, 1];

    for (const [role, fgToken, bgToken, min] of pairs(scheme)) {
      const rawFg = resolve(theme, fgToken, scheme);
      const rawBg = resolve(theme, bgToken, scheme);
      if (!rawFg || !rawBg) continue;

      const bg = composite(rawBg, canvas);
      const fg = composite(rawFg, bg);
      const ratio = contrast(fg, bg);
      checked++;

      const record = {theme, scheme, role, ratio: Math.round(ratio * 100) / 100, min};
      if (ratio + 0.005 < min) findings.push(record);
      else passes.push(record);
    }
  }
}

console.log(`Checked ${checked} colour pairs across ${THEMES.length} themes × 2 schemes.\n`);

if (showAll) {
  for (const p of passes) {
    console.log(`  ok    ${p.theme.padEnd(10)} ${p.scheme.padEnd(5)} ${p.role.padEnd(26)} ${p.ratio}:1 (needs ${p.min})`);
  }
  console.log('');
}

if (findings.length === 0) {
  console.log('No contrast failures against WCAG 2.2 AA.');
  process.exit(0);
}

const byTheme = {};
for (const f of findings) {
  (byTheme[`${f.theme} ${f.scheme}`] ??= []).push(f);
}

for (const [key, items] of Object.entries(byTheme)) {
  console.log(`${key}`);
  for (const f of items.sort((a, b) => a.ratio - b.ratio)) {
    console.log(`  FAIL  ${f.role.padEnd(26)} ${String(f.ratio).padStart(5)}:1  needs ${f.min}:1`);
  }
  console.log('');
}

console.log(`${findings.length} failure(s) of ${checked} pairs.`);
process.exit(1);
