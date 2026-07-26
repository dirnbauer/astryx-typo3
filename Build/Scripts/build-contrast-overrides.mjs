#!/usr/bin/env node
/**
 * Raise the few token values that miss WCAG 2.2 AA, and only those.
 *
 * Astryx's palettes are close to AA and a handful of pairs sit just under it.
 * Those are upstream's choices, and for a theme sold into public-sector and
 * corporate TYPO3 they are not choices we can pass on.
 *
 * This moves ONLY the failing foreground token, by the smallest step that
 * reaches the threshold on every surface it is read on, so the theme keeps its
 * hue and its character. Nothing that already passes is touched.
 *
 * Colour parsing is shared with the audit (lib/theme-tokens.mjs) so the two
 * cannot disagree about what a token resolves to — they did once, and the
 * corrections that came out of it were nonsense.
 *
 *   node Build/Scripts/build-contrast-overrides.mjs
 */

import * as fs from 'node:fs';

import {
  THEMES,
  OVERRIDE_CSS,
  composite, contrast, parseColor, toHex,
  loadTokens,
} from './lib/theme-tokens.mjs';

const TARGET = 4.5;

/**
 * The token to correct, and EVERY background it is read against.
 *
 * The background list is not a guess — it is what the stylesheets declare, and
 * audit-contrast.mjs checks the same pairs. A token read on two surfaces
 * (--color-text-red is a badge label and a field error message) gets one value
 * that clears the threshold on both, because CSS gives us one value.
 */
const CORRECT = [
  {token: '--color-text-secondary', on: ['--color-background-body', '--color-background-surface', '--color-background-card']},

  // Badge labels; three of the hues double as field status messages, which sit
  // on the same hue tint (see 05-forms.css) rather than on --color-*-muted.
  {token: '--color-text-red', on: ['--color-background-red']},
  {token: '--color-text-yellow', on: ['--color-background-yellow']},
  {token: '--color-text-green', on: ['--color-background-green']},
  {token: '--color-text-orange', on: ['--color-background-orange']},
  {token: '--color-text-teal', on: ['--color-background-teal']},
  {token: '--color-text-cyan', on: ['--color-background-cyan']},
  {token: '--color-text-blue', on: ['--color-background-blue']},
  {token: '--color-text-purple', on: ['--color-background-purple']},
  {token: '--color-text-pink', on: ['--color-background-pink']},

  // The error toast.
  {token: '--color-on-error', on: ['--color-background-error-inverted']},
];

// Measure the upstream palette, never our own previous output: that keeps
// re-running this idempotent instead of correcting the corrections.
const {side, resolve} = loadTokens({withOverrides: false});

/**
 * Walk the colour toward black or white until it clears the threshold.
 *
 * Both directions are tried and the shorter walk wins: guessing from the
 * background's luminance is right for body text on a page, but a mid-tone
 * badge tint can be reached from either side, and the shorter walk is the one
 * that changes the theme least.
 */
function correct(fg, backgrounds) {
  const clears = c => backgrounds.every(bg => contrast(c, bg) >= TARGET);

  const walk = toward => {
    for (let step = 0; step <= 100; step++) {
      const k = step / 100;
      // Round to the 8-bit value that will actually be written as hex before
      // testing it: checking the float and shipping the rounded colour is how
      // a "4.51:1" correction lands on the page measuring 4.48:1.
      const moved = (toward === 'black'
        ? fg.map(c => c * (1 - k))
        : fg.map(c => c + (255 - c) * k)).map(Math.round);
      moved[3] = 1;
      if (clears(moved)) return {steps: step, color: moved};
    }
    return null;
  };

  const options = [walk('black'), walk('white')].filter(Boolean);
  if (options.length === 0) return null;
  return options.sort((a, b) => a.steps - b.steps)[0].color;
}

const blocks = [];
const unfixable = [];
let fixed = 0;

for (const theme of THEMES) {
  const declarations = [];

  for (const {token, on} of CORRECT) {
    const value = {};
    let needed = false;
    let usable = true;

    for (const scheme of ['light', 'dark']) {
      // Translucent tints are composited onto the page canvas before being
      // measured, because that is what the reader's eye receives.
      const canvas = resolve(theme, '--color-background-body', scheme) ?? [255, 255, 255, 1];
      const rawFg = resolve(theme, token, scheme);
      const backgrounds = on.map(b => resolve(theme, b, scheme));

      if (!rawFg || backgrounds.some(b => !b)) { usable = false; break; }

      const surfaces = backgrounds.map(b => composite(b, canvas));
      const fg = composite(rawFg, surfaces[0]);
      const worst = Math.min(...surfaces.map(bg => contrast(fg, bg)));

      if (worst >= TARGET) {
        // Keep this side exactly as upstream wrote it.
        value[scheme] = side(theme, token, scheme);
        continue;
      }

      const corrected = correct(fg, surfaces);
      if (!corrected) {
        unfixable.push({theme, scheme, token, worst});
        usable = false;
        break;
      }

      value[scheme] = toHex(corrected);
      needed = true;
      fixed++;
      const after = Math.min(...surfaces.map(bg => contrast(corrected, bg)));
      console.log(
        `  ${theme.padEnd(10)} ${scheme.padEnd(5)} ${token.padEnd(24)} ` +
        `${worst.toFixed(2)}:1 -> ${after.toFixed(2)}:1  ${toHex(fg)} -> ${toHex(corrected)}`
      );
      continue;
    }

    // A light-dark() pair needs both halves; emit nothing rather than half a
    // correction, and never emit a side we could not parse.
    if (needed && usable && parseColor(value.light) && parseColor(value.dark)) {
      declarations.push(`  ${token}: light-dark(${value.light}, ${value.dark});`);
    }
  }

  if (declarations.length > 0) {
    blocks.push(`[data-astryx-theme="${theme}"] {\n${declarations.join('\n')}\n}`);
  }
}

const header = `/*
 * Contrast corrections — GENERATED by Build/Scripts/build-contrast-overrides.mjs.
 *
 * A handful of Astryx's own token values sit just below WCAG 2.2 AA. Each rule
 * below moves ONE foreground token by the smallest step that reaches 4.5:1
 * against every surface it is read on, so the theme keeps its hue and its
 * character; everything that already passed is untouched.
 *
 * This is a deliberate divergence from upstream, and the reason is that a theme
 * sold for public-sector and corporate TYPO3 has to meet AA.
 *
 * The build puts this file in the astryx-theme layer (see build-grande-css.mjs)
 * — in any earlier layer the theme block it corrects would simply win.
 *
 * Re-run the generator after updating the vendored tokens; verify with
 * Build/Scripts/audit-contrast.mjs.
 */`;

fs.writeFileSync(OVERRIDE_CSS, `${header}\n\n${blocks.join('\n\n')}\n`);

for (const u of unfixable) {
  console.warn(`  ! ${u.theme} ${u.scheme} ${u.token}: no value on the black–white axis reaches ${TARGET}:1 on every surface (worst ${u.worst.toFixed(2)}:1)`);
}
console.log(`\n${fixed} token value(s) corrected across ${blocks.length} theme(s).`);
