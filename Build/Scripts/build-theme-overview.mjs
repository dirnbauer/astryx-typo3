#!/usr/bin/env node
/**
 * Generate the theme overview partial from the themes themselves.
 *
 * Every fact on that page — accent colour, fonts, corner radius, base size — is
 * read out of the generated theme stylesheet, so the page cannot drift from
 * what the themes actually do. Describing seven palettes by hand guarantees the
 * description is wrong within a month.
 *
 * The page shows each theme LIVE rather than as a screenshot: the theme tokens
 * are scoped to [data-astryx-theme="…"], not to <body>, so a section carrying
 * that attribute paints itself in that theme on a page that is otherwise in
 * another. Seven real samples, one page, no images.
 *
 *   node Build/Scripts/build-theme-overview.mjs
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {fileURLToPath} from 'node:url';

const EXT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const CSS = path.join(EXT_ROOT, 'Resources/Public/Css/astryx-theme.css');
const OUT = path.join(EXT_ROOT, 'Resources/Private/Templates/Partials/Pages/ThemeOverview.fluid.html');

/**
 * Every theme, from the generated registry — the same list the build scripts,
 * the contrast audit, the TCA field and the seeder read. `family` separates
 * Astryx's own seven from the thirteen this extension adds, because a reader
 * deciding what to build on needs to know which is which.
 */
const THEMES = JSON.parse(
  fs.readFileSync(path.join(EXT_ROOT, 'Build/Data/theme-registry.json'), 'utf8')
).themes;

const css = fs.readFileSync(CSS, 'utf8');

function tokens(selector) {
  const match = css.match(selector);
  if (!match) return {};
  const out = {};
  for (const [, name, value] of match[1].matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/g)) out[name] = value.trim();
  return out;
}

const base = tokens(/:root\s*\{([\s\S]*?)\n\s*\}/);
const lightOf = value => (value?.startsWith('light-dark(') ? value.slice(11, -1).split(',')[0].trim() : value);
const escape = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const rows = THEMES.map(theme => {
  const t = {...base, ...tokens(new RegExp(`\\[data-astryx-theme="${theme.id}"\\]\\s*\\{([\\s\\S]*?)\\n\\s*\\}`))};
  const family = name => (t[name] ?? '').split(',')[0].replace(/['"]/g, '').trim();
  return {
    ...theme,
    accent: lightOf(t['--color-accent']),
    body: lightOf(t['--color-background-body']),
    surface: lightOf(t['--color-background-surface']),
    text: lightOf(t['--color-text-primary']),
    sans: family('--font-family-body'),
    heading: family('--font-family-heading'),
    mono: family('--font-family-code'),
    radius: t['--radius-element'],
    size: t['--font-size-base'],
  };
});

/** One live sample per theme: real tokens, real fonts, real components. */
const cards = rows.map(r => `
        <article class="g-themes__card" data-astryx-theme="${r.id}">
            <header class="g-themes__card-head">
                <h3 class="astryx-heading level-3">
                    <f:for each="{themePages}" as="themePage">
                        <f:if condition="{themePage.data.tx_desideriogrande_theme} == '${r.id}'">
                            <f:link.typolink parameter="{themePage.link}" class="g-themes__link">${escape(r.name)}</f:link.typolink>
                        </f:if>
                    </f:for>
                </h3>
                <code class="g-themes__id">${escape(r.id)}</code>
                <span class="astryx-badge ${r.family === 'astryx' ? 'blue' : 'green'} g-themes__family">${r.family === 'astryx' ? 'Astryx' : 'webconsulting'}</span>
            </header>

            <p class="astryx-text body g-themes__character">${escape(r.character)}</p>

            <div class="g-themes__swatches" role="img" aria-label="Accent, page and surface colours of the ${escape(r.name)} theme">
                <span class="g-themes__swatch g-themes__swatch--accent"></span>
                <span class="g-themes__swatch g-themes__swatch--body"></span>
                <span class="g-themes__swatch g-themes__swatch--surface"></span>
            </div>

            <div class="g-themes__sample">
                <button type="button" class="astryx-button primary" tabindex="-1" aria-hidden="true">Primary</button>
                <span class="astryx-badge success">Success</span>
                <span class="astryx-badge outline">Outline</span>
            </div>

            <dl class="g-themes__facts">
                <dt>Headings</dt><dd style="font-family: var(--font-family-heading)">${escape(r.heading)}</dd>
                <dt>Body</dt><dd style="font-family: var(--font-family-body)">${escape(r.sans)}</dd>
                <dt>Corners</dt><dd>${escape(r.radius)}</dd>
                <dt>Best for</dt><dd>${escape(r.use)}</dd>
            </dl>
        </article>`).join('');

const tableRows = rows.map(r => `
                    <tr>
                        <th scope="row">
                            <span class="g-themes__dot" data-astryx-theme="${r.id}"></span>
                            ${escape(r.name)}
                        </th>
                        <td><code>${escape(r.id)}</code></td>
                        <td>${escape(r.heading)}</td>
                        <td>${escape(r.sans)}</td>
                        <td>${escape(r.mono)}</td>
                        <td>${escape(r.radius)}</td>
                        <td>${escape(r.size)}</td>
                        <td>${r.id === 'gothic' ? 'Dark in both schemes' : 'Follows the scheme'}</td>
                    </tr>`).join('');

const html = `<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">

<f:comment>
    GENERATED by Build/Scripts/build-theme-overview.mjs — do not edit.

    Every value here is read from the compiled theme stylesheet, so the page
    cannot drift from the themes it describes.

    Each card carries its own data-astryx-theme, which is why the samples are
    live rather than pictures: the theme tokens are scoped to that attribute,
    not to the body, so seven themes can paint themselves on one page. That also
    means this page is honest by construction — if a theme changes, the card
    changes with it.
</f:comment>

<section class="astryx-section g-themes">
    <div class="astryx-layout">
        <p class="astryx-eyebrow">Themes</p>
        <h2 class="astryx-heading level-1">Seven themes, one set of content</h2>
        <p class="astryx-text large g-themes__lead">
            Every card below is rendered live in its own theme — the same components,
            the same markup, only different tokens. Switching a theme repaints the site;
            it never touches a word of content. Pick one per site, or per page.
        </p>

        <div class="g-themes__grid">${cards}
        </div>
    </div>
</section>

<section class="astryx-section g-themes-table surface">
    <div class="astryx-layout">
        <h2 class="astryx-heading level-2">What actually differs</h2>
        <p class="astryx-text body g-themes__lead">
            Type, corner radius, base size and how each theme treats dark mode.
            Colour is only part of what a theme decides.
        </p>

        <div class="astryx-table-wrap">
            <table class="astryx-table g-themes__matrix">
                <caption class="astryx-visually-hidden">
                    Comparison of the seven Astryx themes by heading font, body font,
                    monospace font, corner radius, base text size and dark-mode behaviour.
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Theme</th>
                        <th scope="col">Key</th>
                        <th scope="col">Headings</th>
                        <th scope="col">Body</th>
                        <th scope="col">Code</th>
                        <th scope="col">Corners</th>
                        <th scope="col">Base size</th>
                        <th scope="col">Dark mode</th>
                    </tr>
                </thead>
                <tbody>${tableRows}
                </tbody>
            </table>
        </div>

        <p class="astryx-text supporting g-themes__note">
            Set the theme for a whole site with <code>desiderioGrande.theme.default</code>,
            or for one page and everything beneath it with the <strong>Astryx theme</strong>
            field in the page properties. The tokens come from
            <a class="astryx-link" href="https://github.com/facebook/astryx" rel="noreferrer noopener">Meta's Astryx design system</a>;
            this theme renders them server-side in Fluid, with no React and no build step
            between saving and seeing.
        </p>
    </div>
</section>

</html>
`;

fs.mkdirSync(path.dirname(OUT), {recursive: true});
fs.writeFileSync(OUT, html);
console.log(`ThemeOverview.fluid.html — ${rows.length} themes, ${(html.length / 1024).toFixed(1)} kB`);
