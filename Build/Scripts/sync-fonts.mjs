#!/usr/bin/env node
// Copy the webfonts the seven Astryx themes ask for into
// Resources/Public/Css/files/ and generate the @font-face partial
// Resources/Private/Css/grande/00-fonts.css.
//
// Fonts are self-hosted, never fetched from a CDN: no third-party request from
// a visitor's browser, and the files are versioned with the extension.
//
// Two details worth knowing:
//
//   - Fontsource names its variable families "Figtree Variable". Astryx's theme
//     tokens say "Figtree". The @font-face rules generated here therefore use
//     the PLAIN family name, so the token resolves without rewriting upstream
//     values.
//   - All nine families are declared, but a browser only downloads the ones the
//     active theme actually renders with. Declaring an unused @font-face costs
//     nothing.
//
// Run after `npm install`; re-run when a theme changes its typography.

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const MODULES = path.join(EXT_ROOT, 'node_modules');
const FILES_OUT = path.join(EXT_ROOT, 'Resources/Public/Css/files');
const CSS_OUT = path.join(EXT_ROOT, 'Resources/Private/Css/grande/00-fonts.css');

/**
 * family  — the name Astryx's tokens use, and therefore the @font-face family
 * package — the Fontsource package to copy from
 * weights — omit for variable fonts (a single file covers the range);
 *           list the static weights to ship otherwise
 * italic  — ship the italic face when the package has one
 * usedBy  — which themes need it, for the generated comment
 */
const FAMILIES = [
  {family: 'Figtree', package: '@fontsource-variable/figtree', variable: '300 900', italic: true, usedBy: 'neutral, stone'},
  {family: 'Outfit', package: '@fontsource-variable/outfit', variable: '100 900', italic: false, usedBy: 'butter'},
  {family: 'DM Sans', package: '@fontsource-variable/dm-sans', variable: '100 1000', italic: true, usedBy: 'matcha'},
  {family: 'Fraunces', package: '@fontsource-variable/fraunces', variable: '100 900', italic: true, usedBy: 'chocolate (headings)'},
  {family: 'Montserrat', package: '@fontsource-variable/montserrat', variable: '100 900', italic: true, usedBy: 'stone (headings)'},
  {family: 'JetBrains Mono', package: '@fontsource-variable/jetbrains-mono', variable: '100 800', italic: true, usedBy: 'all themes except neutral (code)'},
  {family: 'Albert Sans', package: '@fontsource/albert-sans', weights: [400, 500, 600, 700], usedBy: 'chocolate'},
  {family: 'Fustat', package: '@fontsource/fustat', weights: [400, 500, 600, 700], usedBy: 'gothic'},
  {family: 'Poppins', package: '@fontsource/poppins', weights: [400, 500, 600, 700], usedBy: 'y2k'},
  // Playwrite is a handwriting face and ships light weights only; matcha uses
  // it for headings, where 300/400 is the whole usable range.
  {family: 'Playwrite US Trad', package: '@fontsource/playwrite-us-trad', weights: [300, 400], usedBy: 'matcha (headings)'},
];

/** Latin subsets, narrowest first. latin-ext is optional per package. */
const SUBSETS = ['latin', 'latin-ext'];

fs.mkdirSync(FILES_OUT, {recursive: true});
fs.mkdirSync(path.dirname(CSS_OUT), {recursive: true});

const missingPackages = FAMILIES.filter(f => !fs.existsSync(path.join(MODULES, f.package)));
if (missingPackages.length > 0) {
  console.error(`Missing font packages: ${missingPackages.map(f => f.package).join(', ')}`);
  console.error('Run `npm install` in the extension directory first.');
  process.exit(1);
}

const blocks = [];
let copied = 0;
let bytes = 0;

for (const font of FAMILIES) {
  const packageDir = path.join(MODULES, font.package);
  const filesDir = path.join(packageDir, 'files');
  const slug = font.package.split('/')[1];

  let unicode = {};
  const unicodePath = path.join(packageDir, 'unicode.json');
  if (fs.existsSync(unicodePath)) {
    unicode = JSON.parse(fs.readFileSync(unicodePath, 'utf8'));
  }

  const faces = [];

  /** One @font-face per (subset, style, weight-or-range). */
  const addFace = (fileName, {style, weight, subset, variable}) => {
    const source = path.join(filesDir, fileName);
    if (!fs.existsSync(source)) return false;

    fs.copyFileSync(source, path.join(FILES_OUT, fileName));
    copied++;
    bytes += fs.statSync(source).size;

    const range = unicode[subset];
    faces.push([
      '@font-face {',
      `  font-family: '${font.family}';`,
      `  font-style: ${style};`,
      '  font-display: swap;',
      `  font-weight: ${weight};`,
      `  src: url(./files/${fileName}) format('${variable ? 'woff2-variations' : 'woff2'}');`,
      ...(range ? [`  unicode-range: ${range};`] : []),
      '}',
    ].join('\n'));
    return true;
  };

  for (const subset of SUBSETS) {
    if (font.variable) {
      addFace(`${slug}-${subset}-wght-normal.woff2`, {
        style: 'normal',
        weight: font.variable,
        subset,
        variable: true,
      });
      if (font.italic) {
        addFace(`${slug}-${subset}-wght-italic.woff2`, {
          style: 'italic',
          weight: font.variable,
          subset,
          variable: true,
        });
      }
    } else {
      for (const weight of font.weights ?? []) {
        addFace(`${slug}-${subset}-${weight}-normal.woff2`, {
          style: 'normal',
          weight: String(weight),
          subset,
          variable: false,
        });
      }
    }
  }

  if (faces.length === 0) {
    console.warn(`  ! no files matched for ${font.family} (${font.package})`);
    continue;
  }

  blocks.push(`/* ${font.family} — ${font.usedBy} */\n${faces.join('\n\n')}`);
  console.log(`  ${font.family.padEnd(16)} ${faces.length} face(s)`);
}

const header = `/*
 * Self-hosted webfaces for the Astryx themes — GENERATED, do not edit.
 * Rebuild: npm run build:fonts (then npm run build:css)
 *
 * Family names match the values in Astryx's theme tokens, so a theme's
 * --font-family-* token resolves straight to these faces. Every family is
 * declared here; the browser downloads only what the active theme renders.
 *
 * Fonts are subsetted to latin + latin-ext and licensed under the SIL Open
 * Font License; see each package's LICENSE in node_modules.
 */`;

fs.writeFileSync(CSS_OUT, `${header}\n\n${blocks.join('\n\n')}\n`);

console.log(`\n00-fonts.css written — ${copied} files, ${(bytes / 1024).toFixed(0)} kB copied into Resources/Public/Css/files/`);
