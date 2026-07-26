/**
 * Reading the Astryx theme tokens out of the generated stylesheet.
 *
 * Two scripts need this — the contrast audit and the contrast corrections
 * generator — and when they each had their own parser they disagreed: one
 * truncated the 8-digit hex `#0171E333` to a saturated `#0171E3` and reported
 * badge failures at 1.3:1 that no reader has ever seen, because those tints are
 * 20% alpha over the page. A correction computed from that would have turned
 * the pink badge label black to "fix" a problem that did not exist.
 *
 * So the parser lives here once, and both scripts import it.
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

export const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
export const THEME_CSS = path.join(EXT_ROOT, 'Resources/Public/Css/astryx-theme.css');
export const OVERRIDE_CSS = path.join(EXT_ROOT, 'Resources/Private/Css/grande/07-contrast-overrides.css');

/** All 20 themes, from the generated registry — never a second hardcoded list. */
export const THEMES = JSON.parse(
  fs.readFileSync(path.join(EXT_ROOT, 'Build/Data/theme-registry.json'), 'utf8')
).themes.map(theme => theme.id);
export const HUES = ['red', 'orange', 'yellow', 'green', 'teal', 'cyan', 'blue', 'purple', 'pink'];

// ------------------------------------------------------------------ colour

/** @returns {[number,number,number,number]|null} r,g,b in 0-255 plus alpha 0-1 */
export function parseColor(value) {
  const input = String(value ?? '').trim();

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

export const toHex = rgb =>
  '#' + rgb.slice(0, 3).map(c => Math.max(0, Math.min(255, Math.round(c))).toString(16).padStart(2, '0')).join('');

/** Paint a possibly translucent colour onto an opaque one. */
export function composite(fg, bg) {
  if (fg[3] >= 1) return fg;
  return [0, 1, 2].map(i => Math.round(fg[i] * fg[3] + bg[i] * (1 - fg[3]))).concat(1);
}

export function luminance([r, g, b]) {
  const channel = v => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

export function contrast(fg, bg) {
  const a = luminance(fg);
  const b = luminance(bg);
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

// --------------------------------------------------------------- the CSS

/**
 * Bodies of every rule whose selector is exactly `selector`.
 *
 * Brace-matched rather than regex-matched: a lazy `\{([\s\S]*?)\}` stops at the
 * first closing brace, which is wrong the moment a block contains a nested
 * rule, and the selector has to match exactly or `:root` would also collect
 * `:root.light` and `[data-astryx-theme="x"]` would collect its descendant
 * typography rules.
 */
function blockBodies(css, selector) {
  const bodies = [];
  let i = 0;

  while ((i = css.indexOf(selector, i)) !== -1) {
    const before = css[i - 1];
    // Not a selector start if something selector-ish runs into it.
    if (before !== undefined && /[\w.#\-\[\]"=:]/.test(before)) {
      i += selector.length;
      continue;
    }

    let j = i + selector.length;
    while (j < css.length && /\s/.test(css[j])) j++;
    if (css[j] !== '{') {
      i += selector.length;
      continue;
    }

    let depth = 0;
    let k = j;
    for (; k < css.length; k++) {
      if (css[k] === '{') depth++;
      else if (css[k] === '}' && --depth === 0) break;
    }

    bodies.push(css.slice(j + 1, k));
    i = k;
  }

  return bodies;
}

function declarationsOf(bodies) {
  const out = {};
  for (const body of bodies) {
    for (const [, name, value] of body.matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/g)) {
      out[name] = value.trim();
    }
  }
  return out;
}

/**
 * @param {{withOverrides?: boolean}} options
 *   withOverrides — include the generated corrections. The audit wants them
 *   (it measures what ships); the corrections generator must not have them
 *   (it measures the upstream palette, so re-running it is idempotent).
 */
export function loadTokens({withOverrides = true} = {}) {
  let css = fs.readFileSync(THEME_CSS, 'utf8');
  if (withOverrides && fs.existsSync(OVERRIDE_CSS)) {
    css += '\n' + fs.readFileSync(OVERRIDE_CSS, 'utf8');
  }

  const base = declarationsOf(blockBodies(css, ':root'));
  const tokens = {};
  for (const theme of THEMES) {
    tokens[theme] = {...base, ...declarationsOf(blockBodies(css, `[data-astryx-theme="${theme}"]`))};
  }

  /** The declared value, before light-dark() is split or var() is followed. */
  const raw = (theme, name) => tokens[theme][name];

  /** One side of a light-dark() pair, as written. */
  const side = (theme, name, scheme) => {
    const value = tokens[theme][name];
    const pair = String(value ?? '').match(/^light-dark\(\s*([^,]+),\s*(.+)\s*\)$/);
    return (pair ? (scheme === 'dark' ? pair[2] : pair[1]) : value ?? '').trim();
  };

  /** Follow var() and light-dark() down to an actual colour. */
  const resolve = (theme, name, scheme, seen = new Set()) => {
    if (seen.has(name)) return null;
    seen.add(name);

    let value = tokens[theme][name];
    if (!value) return null;

    const ref = value.match(/^var\(\s*(--[a-z0-9-]+)\s*(?:,\s*([^)]+))?\)$/);
    if (ref) return resolve(theme, ref[1], scheme, seen) ?? (ref[2] ? parseColor(ref[2]) : null);

    const pair = value.match(/^light-dark\(\s*([^,]+),\s*(.+)\s*\)$/);
    if (pair) value = (scheme === 'dark' ? pair[2] : pair[1]).trim();

    return parseColor(value);
  };

  return {tokens, raw, side, resolve};
}
