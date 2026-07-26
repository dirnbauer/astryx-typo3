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

import {
  THEMES, HUES,
  composite, contrast,
  loadTokens,
} from './lib/theme-tokens.mjs';

const showAll = process.argv.includes('--all');

// The corrections are part of what ships, so the audit reads them too:
// measuring the generated theme alone would report a state the site never has.
const {resolve} = loadTokens({withOverrides: true});

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
    // A section with tone=accent redefines the text tokens to
    // --color-on-accent (00-primitives.css), so all of its copy — heading
    // included — resolves to this pair. The heading used to keep
    // --color-text-primary and vanish into the band.
    ['text on an accent band', '--color-on-accent', '--color-accent', 4.5],
    ['popover text', '--color-text-primary', '--color-background-popover', 4.5],

    // Every pair below is one a stylesheet actually declares. The toast paints
    // --color-background-surface on the inverted surface (08-overlay.css), and
    // the field status messages paint the hue TEXT token on the status tint
    // (05-forms.css) — not the bare --color-success/warning/error, which the
    // themes deliberately set equal to their own tint in several palettes and
    // which nothing renders as text.
    ['toast text', '--color-background-surface', '--color-background-inverted', 4.5],
    ['toast error text', '--color-on-error', '--color-background-error-inverted', 4.5],
    ['field success message', '--color-text-green', '--color-background-green', 4.5],
    ['field warning message', '--color-text-yellow', '--color-background-yellow', 4.5],
    ['field error message', '--color-text-red', '--color-background-red', 4.5],

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
