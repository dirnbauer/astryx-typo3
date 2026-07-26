<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\Data;

/**
 * The page tree of the Astryx showcase site, and the copy that fills it.
 *
 * Kept as data rather than as a fixture file because the seeder needs the
 * structure (parents, slugs, layouts, per-page theme) as much as the text, and
 * because the chapter list has to stay in step with the ten wizard groups the
 * content elements are sorted into.
 */
final class GrandeSiteDefinitions
{
    public const ROOT_TITLE = 'Astryx';
    public const ROOT_SLUG = '/';

    /** Backend layouts, mirroring the tsconfig identifiers. */
    public const LAYOUT_STARTPAGE = 'pagets__GrandeStartpage';
    public const LAYOUT_CONTENTPAGE = 'pagets__GrandeContentpage';
    public const LAYOUT_ERROR = 'pagets__GrandeError';
    public const LAYOUT_THEMES = 'pagets__GrandeThemes';

    /**
     * EXT:solr's results plugin. Not a Content Block, so the search page is
     * seeded with a plain tt_content row rather than through the fixture
     * resolver the catalog elements use.
     */
    public const SEARCH_PLUGIN_CTYPE = 'solr_pi_results';

    /**
     * The one line above the search field.
     *
     * It says what is searched and how the results are ordered, because a bare
     * field over an empty page tells a visitor neither.
     */
    public const SEARCH_LEAD = 'Everything on this site, in one field. '
        . 'Results are ranked by relevance and can be narrowed by content type.';

    /**
     * The ten component chapters, in wizard-group order.
     *
     * Each chapter pins a different theme so that walking the hub is also a
     * walk through the seven themes — the fastest way to see that switching one
     * is a repaint and never a content change.
     *
     * @return list<array{group: string, title: string, slug: string, theme: string, abstract: string}>
     */
    public static function chapters(): array
    {
        return [
            [
                'group' => 'hero',
                'title' => 'Hero & Landing Intros',
                'slug' => 'hero',
                'theme' => 'neutral',
                'abstract' => 'The first band of a page: what the product is, who it is for, and the one action that follows.',
            ],
            [
                'group' => 'features',
                'title' => 'Features & Benefits',
                'slug' => 'features',
                'theme' => 'butter',
                'abstract' => 'Capability grids, benefit lists and comparison bands — the middle of a landing page, where a claim turns into specifics.',
            ],
            [
                'group' => 'content',
                'title' => 'Content & Editorial',
                'slug' => 'content',
                'theme' => 'chocolate',
                'abstract' => 'Long-form building blocks: rich text, quotes, media, accordions and everything an article is assembled from.',
            ],
            [
                'group' => 'pricing',
                'title' => 'Plans & Pricing',
                'slug' => 'pricing',
                'theme' => 'matcha',
                'abstract' => 'Plan tables, tier cards and the small print around them, for the page where a visitor decides what something costs.',
            ],
            [
                'group' => 'social-proof',
                'title' => 'Trust & Social Proof',
                'slug' => 'social-proof',
                'theme' => 'stone',
                'abstract' => 'Testimonials, logos, ratings and case-study teasers: the evidence behind a claim.',
            ],
            [
                'group' => 'team',
                'title' => 'People & Team',
                'slug' => 'team',
                'theme' => 'gothic',
                'abstract' => 'Portraits, role cards and org bands for the pages that introduce the people behind the work.',
            ],
            [
                'group' => 'data',
                'title' => 'Data & Dashboards',
                'slug' => 'data',
                'theme' => 'y2k',
                'abstract' => 'Metrics, progress and comparison — rendered in CSS, so a number never waits for a chart library to load.',
            ],
            [
                'group' => 'conversion',
                'title' => 'Leads & Conversion',
                'slug' => 'conversion',
                'theme' => 'neutral',
                'abstract' => 'Calls to action, forms, newsletter bands and everything between a visitor and a reply.',
            ],
            [
                'group' => 'navigation',
                'title' => 'Navigation & Wayfinding',
                'slug' => 'navigation',
                'theme' => 'butter',
                'abstract' => 'Menus, tabs, breadcrumbs, tables of contents: how a reader knows where they are and what else is there.',
            ],
            [
                'group' => 'footer',
                'title' => 'Footers & Utility Areas',
                'slug' => 'footer',
                'theme' => 'matcha',
                'abstract' => 'Site footers, legal rows, contact blocks and the quiet end of a page.',
            ],
        ];
    }

    /**
     * German page titles, keyed by the English one.
     *
     * The page tree itself stays English — the German language is a fallback
     * that translates the chrome and the demo content, not a second tree an
     * editor has to maintain. But a language switch that offers German while
     * every page in the menu is still called "Features & Benefits" looks
     * broken, so the titles are translated even though the content is not.
     *
     * Theme names are proper nouns and deliberately absent: Harbour stays
     * Harbour, the way Matcha does.
     *
     * @return array<string, string>
     */
    public static function germanPageTitles(): array
    {
        return [
            'Components' => 'Komponenten',
            'Themes' => 'Themes',
            'Search' => 'Suche',
            'Imprint' => 'Impressum',
            'Privacy' => 'Datenschutz',
            'Accessibility' => 'Barrierefreiheit',
            'Page not found' => 'Seite nicht gefunden',
            'Element Library' => 'Elementbibliothek',
            'Hero & Landing Intros' => 'Hero & Einstiegsbereiche',
            'Features & Benefits' => 'Funktionen & Nutzen',
            'Content & Editorial' => 'Inhalt & Redaktion',
            'Plans & Pricing' => 'Tarife & Preise',
            'Trust & Social Proof' => 'Vertrauen & Referenzen',
            'People & Team' => 'Menschen & Team',
            'Data & Dashboards' => 'Daten & Dashboards',
            'Leads & Conversion' => 'Leads & Conversion',
            'Navigation & Wayfinding' => 'Navigation & Orientierung',
            'Footers & Utility Areas' => 'Footer & Servicebereiche',
        ];
    }

    /**
     * Every theme, from the generated registry.
     *
     * Build/Data/theme-registry.json is written by build-grande-themes.mjs and
     * is the one place that knows which themes exist — the build scripts, the
     * contrast audit, the TCA field and the site settings all read it, so a new
     * theme cannot be half-added.
     *
     * @return list<array{id: string, name: string, character: string, use: string, family: string}>
     */
    public static function themes(): array
    {
        $path = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(
            'EXT:desiderio_grande/Build/Data/theme-registry.json'
        );
        if ($path === '' || !is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        $themes = is_array($decoded) ? ($decoded['themes'] ?? []) : [];

        return is_array($themes) ? array_values(array_filter($themes, 'is_array')) : [];
    }

    /**
     * The elements every theme detail page is built from.
     *
     * The SAME cross-section on all twenty pages, deliberately: a theme page is
     * only useful if it can be compared with another one, and that stops being
     * possible the moment each page picks the elements that flatter it. It runs
     * top to bottom the way a real page does — hero, argument, evidence, price,
     * people, close — and covers the parts a theme actually changes: headings
     * and body type, cards and their radius, form controls, badges and status
     * colours, tables, quotes and an accent band.
     *
     * @return list<string>
     */
    public static function themeShowcaseElements(): array
    {
        return [
            'desiderio_grande_herosplitmedia',
            'desiderio_grande_statementband',
            'desiderio_grande_featuregrid',
            'desiderio_grande_featuresteps',
            'desiderio_grande_richtext',
            'desiderio_grande_calloutnote',
            'desiderio_grande_pullquote',
            'desiderio_grande_codeblock',
            'desiderio_grande_datakpirow',
            'desiderio_grande_datakeyfigures',
            'desiderio_grande_imagegallery',
            'desiderio_grande_pricinglicencetiers',
            'desiderio_grande_pricingplancard',
            'desiderio_grande_testimonialportrait',
            'desiderio_grande_logowallclaim',
            'desiderio_grande_teamgrid',
            'desiderio_grande_accordionfaq',
            'desiderio_grande_menucardgrid',
            'desiderio_grande_conversioncontactband',
            'desiderio_grande_conversionctainline',
            'desiderio_grande_footercontactblock',
        ];
    }

    /**
     * Pages that are not chapters: the hub, the legal set, the error page.
     *
     * @return list<array{title: string, slug: string, layout: string, navHide: bool, noIndex: bool, abstract: string, role: string}>
     */
    public static function supportPages(): array
    {
        return [
            [
                'title' => 'Components',
                'slug' => 'components',
                'layout' => self::LAYOUT_CONTENTPAGE,
                'navHide' => false,
                'noIndex' => false,
                'abstract' => 'Every content element this theme ships, grouped the way the editor wizard groups them.',
                'role' => 'hub',
            ],
            [
                'title' => 'Themes',
                'slug' => 'themes',
                'layout' => self::LAYOUT_THEMES,
                'navHide' => false,
                'noIndex' => false,
                'abstract' => 'All seven Astryx themes side by side, each card rendered live in the theme it names.',
                'role' => 'themes',
            ],
            [
                // Hidden from the navigation because the loupe in the header is
                // how a visitor gets here, and no-indexed because a search
                // results page in a search index is a page about nothing.
                'title' => 'Search',
                'slug' => 'search',
                'layout' => self::LAYOUT_CONTENTPAGE,
                'navHide' => true,
                'noIndex' => true,
                'abstract' => 'Full-text search across this site.',
                'role' => 'search',
            ],
            [
                'title' => 'Imprint',
                'slug' => 'imprint',
                'layout' => self::LAYOUT_CONTENTPAGE,
                'navHide' => true,
                'noIndex' => false,
                'abstract' => 'Who runs this site.',
                'role' => 'legal',
            ],
            [
                'title' => 'Privacy',
                'slug' => 'privacy',
                'layout' => self::LAYOUT_CONTENTPAGE,
                'navHide' => true,
                'noIndex' => false,
                'abstract' => 'What this site stores, and what it does not.',
                'role' => 'legal',
            ],
            [
                'title' => 'Accessibility',
                'slug' => 'accessibility',
                'layout' => self::LAYOUT_CONTENTPAGE,
                'navHide' => true,
                'noIndex' => false,
                'abstract' => 'How this theme is built for keyboard, screen reader and reduced motion.',
                'role' => 'legal',
            ],
            [
                'title' => 'Page not found',
                'slug' => 'not-found',
                'layout' => self::LAYOUT_ERROR,
                'navHide' => true,
                'noIndex' => true,
                'abstract' => 'That page does not exist — or it moved. The navigation above will get you back.',
                'role' => 'error',
            ],
        ];
    }


    /**
     * The home page, assembled from the catalog's own elements.
     *
     * Every claim here is checkable against the repository — the counts come
     * from the matrix, the theme list from the compiled stylesheet — because a
     * page selling a design system is itself the first thing a buyer inspects.
     * Nothing is attributed to a customer who does not exist: this page argues
     * from what the extension is, not from invented praise.
     *
     * @return list<array{ctype: string, fixture: array<string, mixed>}>
     */
    public static function homeContent(): array
    {
        return [
            [
                'ctype' => 'desiderio_grande_herosplitmedia',
                'fixture' => [
                    'eyebrow' => 'Astryx for TYPO3',
                    'header' => 'Meta\'s design system, rendered by TYPO3',
                    'lead' => 'Two hundred and fifty content elements built on Astryx — the open-source design system Meta released under MIT. Twenty themes, light and dark, and not one line of React on the page your visitor loads.',
                    'cta_label' => 'See all 250 elements',
                    'cta_link' => 't3://page?uid=__HUB__',
                    'secondary_label' => 'Compare all twenty themes',
                    'secondary_link' => 't3://page?uid=__THEMES__',
                    'image' => [['file' => 'EXT:desiderio_grande/Resources/Public/Images/scene/office-standup.jpg', 'alternative' => 'An editorial team reviewing a page layout together', 'title' => '']],
                    'caption' => 'Editors work in the page module; nothing about the theme changes that.',
                    'tone' => 'body',
                    'width' => 'lg',
                ],
            ],
            [
                'ctype' => 'desiderio_grande_datakpirow',
                'fixture' => [
                    'eyebrow' => 'What you get',
                    'header' => 'The numbers, and they are checkable',
                    'lead' => 'Counted from the repository on the day this page was written, not rounded for a slide.',
                    'columns' => '4',
                    'tone' => 'surface',
                    'align' => 'center',
                    'width' => 'lg',
                    'note' => 'Counted from the repository, not rounded for a slide: 250 element directories, 7 theme blocks in the compiled stylesheet, 13 columns added to tt_content, and one JavaScript file of roughly 9 kB.',
                    'metrics' => [
                        ['value' => '250', 'unit' => 'elements', 'title' => 'Across ten editor categories', 'text' => 'Hero, features, content, pricing, social proof, team, data, conversion, navigation and footer.'],
                        ['value' => '7', 'unit' => 'themes', 'title' => 'Switchable per site or per page', 'text' => 'Changing one repaints the site and never touches a word of content.'],
                        ['value' => '0', 'unit' => 'frameworks', 'title' => 'No React, no build step', 'text' => 'Fluid templates and plain CSS. 243 of the 250 elements need no JavaScript at all.'],
                        ['value' => '13', 'unit' => 'new columns', 'title' => 'For all 250 elements', 'text' => 'A shared field vocabulary, so the elements do not each invent their own database columns.'],
                    ],
                ],
            ],
            [
                'ctype' => 'desiderio_grande_featuregrid',
                'fixture' => [
                    'eyebrow' => 'Why this one',
                    'header' => 'Built the way a design system is supposed to work',
                    'lead' => 'The tokens are not an interpretation of Astryx. They are Astryx: produced by running its own theme compiler over the seven shipped themes, then scoped so a page can carry any of them.',
                    'columns' => '3',
                    'tone' => 'body',
                    'width' => 'lg',
                    'items' => [
                        ['title' => 'Upstream tokens, not a lookalike', 'text' => 'Colours, spacing, radii and the type scale come from Astryx\'s own compiler output, with the upstream commit recorded in the repository.'],
                        ['title' => 'One switch, whole site', 'text' => 'Every element speaks only in tokens — an audit fails the build on a raw colour — which is why one theme change reaches all 250 at once.'],
                        ['title' => 'Light and dark from one palette', 'text' => 'Each colour is a light-dark() pair resolved against the colour scheme, so the scheme switch and the theme switch stay independent.'],
                        ['title' => 'Accessibility measured, not asserted', 'text' => 'The build measures all 406 colour pairs the stylesheets declare, across fourteen palettes, against WCAG 2.2 AA — and refuses to finish if one of them fails.'],
                        ['title' => 'Editors keep the page module', 'text' => 'Content Blocks elements with real backend previews, keyword search and a live picker preview for every one of the 250.'],
                        ['title' => 'Self-hosted, no third parties', 'text' => 'Ten font families served from your own domain. A visitor\'s browser makes no request to anyone else.'],
                    ],
                ],
            ],
            [
                'ctype' => 'desiderio_grande_featuresteps',
                'fixture' => [
                    'eyebrow' => 'Getting started',
                    'header' => 'Three steps to a themed site',
                    'lead' => 'No build step, no asset pipeline to learn, and nothing to compile before an editor can start writing.',
                    'columns' => '3',
                    'tone' => 'surface',
                    'width' => 'lg',
                    'steps' => [
                        ['title' => 'Install the extension', 'text' => 'Require webconsulting/desiderio-grande. It sits alongside Desiderio rather than replacing it, so a site can run either.'],
                        ['title' => 'Add two site sets', 'text' => 'The base set and the content-element set. The wizard then offers exactly this catalog and nothing from the other theme.'],
                        ['title' => 'Pick a theme', 'text' => 'One setting for the site, one page field to override it anywhere below. There is no rebuild — the tokens are already on the page.'],
                    ],
                ],
            ],
            [
                'ctype' => 'desiderio_grande_pricinglicencetiers',
                'fixture' => [
                    'eyebrow' => 'What it costs',
                    'header' => 'The package is free. The guarantees are what you pay for.',
                    'lead' => 'Desiderio Grande is GPL-2.0-or-later, like TYPO3 itself: all 250 elements, all twenty themes, the seeding commands and the audit scripts, complete and at no cost. The paid tiers buy response times and the people who built it — never a feature that was withheld.',
                    'note' => 'Prices are net, excluding VAT. Support tiers are billed yearly or monthly and can be cancelled to the end of the term. Astryx itself is MIT-licensed and © Meta Platforms, Inc. and affiliates.',
                    'cta_label' => 'See what you would be installing',
                    'cta_link' => 't3://page?uid=__HUB__',
                    'tone' => 'surface',
                    'width' => 'lg',
                    'tiers' => [
                        [
                            'title' => 'Community',
                            'value' => '€0',
                            'text' => 'The complete extension under GPL-2.0-or-later — every element, every theme, the contrast audit and the demo seeding. Unlimited sites, no registration, no feature held back. Questions go to the public issue tracker.',
                        ],
                        [
                            'title' => 'Pro',
                            'value' => '€49',
                            'text' => 'Per month, or €490 a year. Priority email support answered within two working days, guaranteed compatibility updates for TYPO3 LTS releases, and early access to new element drops.',
                        ],
                        [
                            'title' => 'Agency',
                            'value' => '€149',
                            'text' => 'Per month, or €1,490 a year. Everything in Pro across unlimited client projects, answers within four business hours (CET), and a yearly review of your own theme overrides by the people who wrote the token pipeline.',
                        ],
                        [
                            'title' => 'Installation',
                            'value' => '€890',
                            'text' => 'One-off. We install and configure the extension on your TYPO3, wire the site sets, pick the theme with you and seed a working demo tree. Brand adaptation — your palette rendered as an eighth theme — from €1,990.',
                        ],
                    ],
                ],
            ],
            [
                'ctype' => 'desiderio_grande_accordionfaq',
                'fixture' => [
                    'eyebrow' => 'Before you ask',
                    'header' => 'The questions worth answering honestly',
                    'lead' => 'Including the ones with an awkward answer.',
                    'width' => 'md',
                    'tone' => 'body',
                    'questions' => [
                        ['title' => 'Is this an official Meta product?', 'bodytext' => '<p>No. Astryx is released by Meta under the MIT licence and this extension builds on it. There is no affiliation with or endorsement by Meta, and no Astryx source code is redistributed — only its design tokens and component documentation.</p>'],
                        ['title' => 'Does it run React on the frontend?', 'bodytext' => '<p>No. Astryx upstream is React and StyleX; this is Fluid and CSS. The visual language is the same, the runtime is not. One small vanilla script handles the four behaviours the platform has no element for.</p>'],
                        ['title' => 'Can I use it alongside Desiderio?', 'bodytext' => '<p>Yes, and that is the design. Both are installed in the same TYPO3, and each site declares which theme it uses. The element picker on each site offers only that theme\'s elements.</p>'],
                        ['title' => 'Is every theme WCAG 2.2 AA clean?', 'bodytext' => '<p>Yes, and the proof runs on every build. A script measures all 1,200 colour pairs the stylesheets actually declare — twenty themes in light and dark — and exits non-zero if any of them misses the threshold, so a palette change cannot quietly regress.</p><p>Astryx\'s own values do fall short in a few places: secondary text in five themes, badge hues in chocolate, matcha and gothic, and the error-toast label in all seven. A generated corrections file moves only the failing foreground token, and only by the smallest step that reaches 4.5:1, so the hue survives the fix — matcha\'s red goes from #cc0000 to #b10000, not to black. Twenty-three token declarations are corrected in all; everything already passing is left exactly as Meta shipped it.</p>'],
                        ['title' => 'What happens when Astryx changes?', 'bodytext' => '<p>The token payload is vendored with the upstream commit recorded next to it. Regenerating is one command; nothing silently follows upstream behind your back.</p>'],
                    ],
                ],
            ],
            [
                'ctype' => 'desiderio_grande_conversionclosingcta',
                'fixture' => [
                    'eyebrow' => 'Have a look first',
                    'header' => 'Every element, on a page you can read',
                    'subheader' => 'Not a gallery of screenshots — the running site',
                    'lead' => 'The whole catalog is seeded on this site, one chapter per editor category, each chapter wearing a different theme. Nothing is a screenshot.',
                    'cta_label' => 'Browse the catalog',
                    'cta_link' => 't3://page?uid=__HUB__',
                    'secondary_label' => 'Astryx on GitHub',
                    'secondary_link' => 'https://github.com/facebook/astryx',
                    'note' => 'Astryx is MIT-licensed and © Meta Platforms, Inc. and affiliates. This extension is GPL-2.0-or-later, like TYPO3.',
                    'align' => 'center',
                    'tone' => 'accent',
                    'width' => 'md',
                ],
            ],
        ];
    }

    /**
     * Copy for the legal and error pages, keyed by slug.
     *
     * These are demo pages on a demo site: the text says what the page is for
     * and states plainly that it is not a legal document, rather than
     * impersonating one.
     *
     * @return array<string, array{header: string, bodytext: string}>
     */
    public static function supportContent(): array
    {
        return [
            'imprint' => [
                'header' => 'A placeholder, not a legal notice',
                'bodytext' => '<p>This is a demonstration site for the Astryx design system running on TYPO3. It is not a business, and this page is a placeholder where a real imprint would go.</p>'
                    . '<p>A production site replaces this text with the disclosures its jurisdiction requires — operator, address, contact, registration and supervisory details.</p>',
            ],
            'privacy' => [
                'header' => 'What this site stores, and what it does not',
                'bodytext' => '<p>This demonstration site sets no tracking cookies of its own, and fonts are served from this domain, so an ordinary page makes no request to anyone else.</p>'
                    . '<p>The exception is video. The chapters that demonstrate the video elements embed a recording from YouTube through its privacy-enhanced <em>youtube-nocookie.com</em> address, which sets no tracking cookies until the film is actually played. Two of those elements only fetch the player when you press play — a closed dialog never loads its iframe — while the plain embed loads it with the page.</p>'
                    . '<p>The one thing stored in your browser is the light/dark preference you pick with the toggle in the header. It never leaves your device, and clearing your site data removes it.</p>'
                    . '<p>A production site replaces this page with a privacy notice describing what it actually processes.</p>',
            ],
            'accessibility' => [
                'header' => 'How this theme is built',
                'bodytext' => '<p>The theme is built to work without a mouse and without sight of the layout. Every page has one h1 that says what the page is; content elements head their own band with an h2, so the outline reads as a table of contents.</p>'
                    . '<p>Focus is always visible, and the accent-coloured ring is drawn from the same token as the rest of the theme, so it stays visible in every one of the twenty themes and in both colour schemes.</p>'
                    . '<p>Contrast is measured rather than assumed. A script checks all 1,200 colour pairs the stylesheets declare — twenty themes in light and dark — against the WCAG 2.2 AA thresholds of 4.5:1 for body text and 3:1 for boundaries, focus rings and meaningful graphics, and the build fails if a single pair misses. Where Astryx\'s own palette values fall short, a generated corrections file lifts only the failing colour, and only as far as the threshold.</p>'
                    . '<p>State is never carried by colour alone: a current navigation item is also heavier, a status also carries text.</p>'
                    . '<p>Animation is opt-out at the source. Anything that moves is wrapped in a reduced-motion query, so a visitor who asks their system for less motion gets a still page rather than a slower one.</p>'
                    . '<p>Menus are native disclosures, dialogs are the native dialog element and the colour-scheme switch is a real button, so keyboard behaviour comes from the browser instead of being reimplemented.</p>',
            ],
            'not-found' => [
                'header' => 'The address did not lead anywhere',
                'bodytext' => '<p>The address you followed does not lead anywhere on this site. It may have been renamed, or the link that brought you here may be out of date.</p>',
            ],
        ];
    }
}
