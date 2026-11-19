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
const LABELS = path.join(EXT_ROOT, 'Resources/Private/Language/themes.xlf');
const labels = new Map();

/** Keep generated copy in TYPO3's translation system, including accessible names. */
function translate(key, source, args = []) {
  if (labels.has(key) && labels.get(key) !== source) throw new Error(`Conflicting source for ${key}`);
  labels.set(key, source);
  const argumentValues = args.map((value, index) => {
    const literal = typeof value === 'number' ? value : `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
    return `${index}: ${literal}`;
  });
  const argumentsAttribute = args.length === 0 ? '' : `, arguments: {${argumentValues.join(', ')}}`;
  return `{f:translate(key: 'LLL:EXT:astryx_typo3/Resources/Private/Language/themes.xlf:${key}'${argumentsAttribute})}`;
}

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
const cardFor = r => `
        <article class="g-themes__card" data-astryx-theme="${r.id}">
            <header class="g-themes__card-head">
                <a:atom.heading level="3">
                    <f:for each="{themePages}" as="themePage">
                        <f:if condition="{themePage.data.tx_desiderioastryx_theme} == '${r.id}'">
                            <f:link.typolink parameter="{themePage.link}" class="g-themes__link">${escape(r.name)}</f:link.typolink>
                        </f:if>
                    </f:for>
                </a:atom.heading>
                <code class="g-themes__id">${escape(r.id)}</code>
                <a:atom.badge variant="${r.family === 'astryx' ? 'blue' : 'green'}" as="span" class="g-themes__family">${r.family === 'astryx' ? 'Astryx' : 'webconsulting'}</a:atom.badge>
            </header>

            <a:atom.text type="body" class="g-themes__character">${translate(`theme.${r.id}.character`, r.character)}</a:atom.text>

            <div class="g-themes__swatches" role="img" aria-label="${translate('swatches', 'Accent, page and surface colours of the %s theme', [r.name])}">
                <span class="g-themes__swatch g-themes__swatch--accent"></span>
                <span class="g-themes__swatch g-themes__swatch--body"></span>
                <span class="g-themes__swatch g-themes__swatch--surface"></span>
            </div>

            <div class="g-themes__sample">
                <a:atom.button variant="primary" tabindex="-1" ariaHidden="true">${translate('sample.primary', 'Primary')}</a:atom.button>
                <a:atom.badge variant="success" as="span">${translate('sample.success', 'Success')}</a:atom.badge>
                <a:atom.badge variant="outline" as="span">${translate('sample.outline', 'Outline')}</a:atom.badge>
            </div>

            <dl class="g-themes__facts">
                <dt>${translate('facts.headings', 'Headings')}</dt><dd style="font-family: var(--font-family-heading)">${escape(r.heading)}</dd>
                <dt>${translate('facts.body', 'Body')}</dt><dd style="font-family: var(--font-family-body)">${escape(r.sans)}</dd>
                <dt>${translate('facts.corners', 'Corners')}</dt><dd>${escape(r.radius)}</dd>
                <dt>${translate('facts.bestFor', 'Best for')}</dt><dd>${translate(`theme.${r.id}.use`, r.use)}</dd>
            </dl>
        </article>`;

const astryxRows = rows.filter(r => r.family === 'astryx');
const ourRows = rows.filter(r => r.family === 'webconsulting');
const astryxCards = astryxRows.map(cardFor).join('');
const ourCards = ourRows.map(cardFor).join('');

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
                        <td>${r.id === 'gothic' ? translate('scheme.alwaysDark', 'Dark in both schemes') : translate('scheme.follows', 'Follows the scheme')}</td>
                    </tr>`).join('');

const html = `<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" xmlns:a="http://typo3.org/ns/Webconsulting/AstryxTypo3/Components/ComponentCollection" data-namespace-typo3-fluid="true">

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

<a:layout.section class="g-themes">
    <a:layout.container>
        <a:atom.eyebrow>${translate('intro.eyebrow', 'Themes')}</a:atom.eyebrow>
        <a:atom.heading level="2" type="heading-1">${translate('intro.heading', '%d themes, one set of content', [rows.length])}</a:atom.heading>
        <a:atom.text type="large" class="g-themes__lead">
            ${translate('intro.lead', 'Every card below is rendered live in its own theme — the same components, the same markup, only different tokens. Switching a theme repaints the site; it never touches a word of content. Pick one per site, or per page.')}
        </a:atom.text>
    </a:layout.container>
</a:layout.section>

<a:layout.section class="g-themes">
    <a:layout.container>
        <a:atom.eyebrow>${translate('astryx.eyebrow', 'From Astryx')}</a:atom.eyebrow>
        <a:atom.heading level="2">${translate('astryx.heading', 'The %d Meta ships', [astryxRows.length])}</a:atom.heading>
        <a:atom.text type="body" class="g-themes__lead">
            ${translate('astryx.lead', "These are Astryx's own themes, token for token: the values come from upstream's theme compiler rather than from anyone's eye. Astryx itself ships %d themes.", [astryxRows.length])}
        </a:atom.text>

        <div class="g-themes__grid">${astryxCards}
        </div>
    </a:layout.container>
</a:layout.section>

<a:layout.section surface="surface" class="g-themes">
    <a:layout.container>
        <a:atom.eyebrow>${translate('webconsulting.eyebrow', 'From webconsulting')}</a:atom.eyebrow>
        <a:atom.heading level="2">${translate('webconsulting.heading', '%d more, built on the same contract', [ourRows.length])}</a:atom.heading>
        <a:atom.text type="body" class="g-themes__lead">
            ${translate('webconsulting.lead', "Ours, not Meta's. Each is expanded into exactly the structure Astryx's own generator emits and carries the same token names, so no component can tell the difference — and each passes the same WCAG 2.2 AA audit as the ones above. What they are not is Astryx's work, which is why they are on their own shelf. Five of them adapt palettes published by other open-source projects under the MIT licence — Nord, Catppuccin, Solarized, Gruvbox and Tokyo Night — and say so on their card.")}
        </a:atom.text>

        <div class="g-themes__grid">${ourCards}
        </div>
    </a:layout.container>
</a:layout.section>

<a:layout.section surface="surface" class="g-themes-table">
    <a:layout.container>
        <a:atom.heading level="2">${translate('comparison.heading', 'What actually differs')}</a:atom.heading>
        <a:atom.text type="body" class="g-themes__lead">
            ${translate('comparison.lead', 'Type, corner radius, base size and how each theme treats dark mode. Colour is only part of what a theme decides.')}
        </a:atom.text>

        <f:comment>
            The scroll box is the theme's own, not the design system's. A table
            of twenty-five rows by eight columns cannot be made to fit a phone,
            so Table takes the scroll with its own scroll argument. It renders a
            ScrollableArea, which is a tab stop with a name — a scroll container
            a keyboard cannot reach hides the columns it scrolls to.
        </f:comment>
            <a:molecule.table class="g-themes__matrix" scroll="{true}" scrollLabel="${translate('comparison.scrollLabel', 'Theme comparison table')}">
                <a:atom.visuallyHidden as="caption">
                    ${translate('comparison.caption', 'Comparison of all %d themes by heading font, body font, monospace font, corner radius, base text size and dark-mode behaviour.', [rows.length])}
                </a:atom.visuallyHidden>
                <thead>
                    <tr>
                        <th scope="col">${translate('facts.theme', 'Theme')}</th>
                        <th scope="col">${translate('facts.key', 'Key')}</th>
                        <th scope="col">${translate('facts.headings', 'Headings')}</th>
                        <th scope="col">${translate('facts.body', 'Body')}</th>
                        <th scope="col">${translate('facts.code', 'Code')}</th>
                        <th scope="col">${translate('facts.corners', 'Corners')}</th>
                        <th scope="col">${translate('facts.baseSize', 'Base size')}</th>
                        <th scope="col">${translate('facts.darkMode', 'Dark mode')}</th>
                    </tr>
                </thead>
                <tbody>${tableRows}
                </tbody>
            </a:molecule.table>

        <a:atom.text type="supporting" class="g-themes__note">
            ${translate('configuration.site', 'Set the theme for a whole site with')} <code>astryx.theme.default</code>.
            ${translate('configuration.page', 'For one page and everything beneath it, use the Astryx theme field in the page properties.')}
            ${translate('configuration.tokens', 'The tokens come from')}
            <a:atom.link href="https://github.com/facebook/astryx" rel="noreferrer noopener">${translate('configuration.upstream', "Meta's Astryx design system")}</a:atom.link>.
            ${translate('configuration.rendering', 'This theme renders them server-side in Fluid, with no React and no build step between saving and seeing.')}
        </a:atom.text>
    </a:layout.container>
</a:layout.section>

</html>
`;

fs.mkdirSync(path.dirname(OUT), {recursive: true});
fs.writeFileSync(OUT, html);
const units = [...labels].map(([key, source]) => `    <unit id="${key}">\n      <segment>\n        <source>${escape(source)}</source>\n      </segment>\n    </unit>`).join('\n');
fs.writeFileSync(LABELS, `<?xml version="1.0" encoding="utf-8"?>\n<!-- GENERATED by Build/Scripts/build-theme-overview.mjs. Translate in de.themes.xlf. -->\n<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en">\n  <file id="themes">\n${units}\n  </file>\n</xliff>\n`);
console.log(`ThemeOverview.fluid.html — ${rows.length} themes, ${(html.length / 1024).toFixed(1)} kB`);
console.log(`themes.xlf — ${labels.size} translatable labels`);
