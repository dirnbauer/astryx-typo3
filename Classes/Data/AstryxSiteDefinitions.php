<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Data;

/**
 * The page tree of the Astryx showcase site, and the copy that fills it.
 *
 * Kept as data rather than as a fixture file because the seeder needs the
 * structure (parents, slugs, layouts, per-page theme) as much as the text, and
 * because the chapter list has to stay in step with the ten wizard groups the
 * content elements are sorted into.
 */
final class AstryxSiteDefinitions
{
    public const ROOT_TITLE = 'Astryx';
    public const ROOT_SLUG = '/';

    /** Backend layouts, mirroring the tsconfig identifiers. */
    public const LAYOUT_STARTPAGE = 'pagets__AstryxStartpage';
    public const LAYOUT_CONTENTPAGE = 'pagets__AstryxContentpage';
    public const LAYOUT_ERROR = 'pagets__AstryxError';
    public const LAYOUT_THEMES = 'pagets__AstryxThemes';

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
    public const SEARCH_LEAD = 'Search every page on this site. '
        . 'Results are sorted by relevance, and you can filter them by content type.';

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
                'abstract' => 'The opening section of a page. It says what the product is, who it is for and what to do next.',
            ],
            [
                'group' => 'features',
                'title' => 'Features & Benefits',
                'slug' => 'features',
                'theme' => 'butter',
                'abstract' => 'Feature grids, benefit lists and comparisons for the middle of a landing page, where claims get specific.',
            ],
            [
                'group' => 'content',
                'title' => 'Content & Editorial',
                'slug' => 'content',
                'theme' => 'chocolate',
                'abstract' => 'Elements for long articles: rich text, quotes, media, accordions and everything else an article needs.',
            ],
            [
                'group' => 'pricing',
                'title' => 'Plans & Pricing',
                'slug' => 'pricing',
                'theme' => 'matcha',
                'abstract' => 'Plan tables, tier cards and the small print, for the page where visitors compare prices.',
            ],
            [
                'group' => 'social-proof',
                'title' => 'Trust & Social Proof',
                'slug' => 'social-proof',
                'theme' => 'stone',
                'abstract' => 'Testimonials, logos, ratings and case study teasers that back up a claim.',
            ],
            [
                'group' => 'team',
                'title' => 'People & Team',
                'slug' => 'team',
                'theme' => 'gothic',
                'abstract' => 'Portraits, role cards and team overviews for pages that introduce the people behind the work.',
            ],
            [
                'group' => 'data',
                'title' => 'Data & Dashboards',
                'slug' => 'data',
                'theme' => 'y2k',
                'abstract' => 'Metrics, progress bars and comparisons, drawn in CSS, so no number waits for a chart library to load.',
            ],
            [
                'group' => 'conversion',
                'title' => 'Leads & Conversion',
                'slug' => 'conversion',
                'theme' => 'neutral',
                'abstract' => 'Calls to action, forms and newsletter sign-ups, so visitors can get in touch.',
            ],
            [
                'group' => 'navigation',
                'title' => 'Navigation & Wayfinding',
                'slug' => 'navigation',
                'theme' => 'butter',
                'abstract' => 'Menus, tabs, breadcrumbs and tables of contents that show readers where they are and what else there is.',
            ],
            [
                'group' => 'footer',
                'title' => 'Footers & Utility Areas',
                'slug' => 'footer',
                'theme' => 'matcha',
                'abstract' => 'Site footers, legal links and contact details for the end of a page.',
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
     * Build/Data/theme-registry.json is written by build-astryx-themes.mjs and
     * is the one place that knows which themes exist — the build scripts, the
     * contrast audit, the TCA field and the site settings all read it, so a new
     * theme cannot be half-added.
     *
     * @return list<array{id: string, name: string, character: string, use: string, family: string}>
     */
    public static function themes(): array
    {
        $path = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName(
            'EXT:astryx_typo3/Build/Data/theme-registry.json'
        );
        if ($path === '' || !is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        $rows = is_array($decoded) ? ($decoded['themes'] ?? []) : [];
        if (!is_array($rows)) {
            return [];
        }

        // The registry carries more per theme than this (package, provenance,
        // upstream description); the five keys below are the ones the site
        // seeder needs, and projecting them here is what makes the promise in
        // the return type above true rather than hopeful.
        $themes = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $themes[] = [
                'id' => is_string($row['id'] ?? null) ? $row['id'] : '',
                'name' => is_string($row['name'] ?? null) ? $row['name'] : '',
                'character' => is_string($row['character'] ?? null) ? $row['character'] : '',
                'use' => is_string($row['use'] ?? null) ? $row['use'] : '',
                'family' => is_string($row['family'] ?? null) ? $row['family'] : '',
            ];
        }

        return $themes;
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
            'astryx_typo3_herosplitmedia',
            'astryx_typo3_statementband',
            'astryx_typo3_featuregrid',
            'astryx_typo3_featuresteps',
            'astryx_typo3_richtext',
            'astryx_typo3_calloutnote',
            'astryx_typo3_pullquote',
            'astryx_typo3_codeblock',
            'astryx_typo3_datakpirow',
            'astryx_typo3_datakeyfigures',
            'astryx_typo3_imagegallery',
            'astryx_typo3_pricinglicencetiers',
            'astryx_typo3_pricingplancard',
            'astryx_typo3_testimonialportrait',
            'astryx_typo3_logowallclaim',
            'astryx_typo3_teamgrid',
            'astryx_typo3_accordionfaq',
            'astryx_typo3_menucardgrid',
            'astryx_typo3_conversioncontactband',
            'astryx_typo3_conversionctainline',
            'astryx_typo3_footercontactblock',
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
                'abstract' => 'Every content element of this theme, grouped as in the content wizard.',
                'role' => 'hub',
            ],
            [
                'title' => 'Themes',
                'slug' => 'themes',
                'layout' => self::LAYOUT_THEMES,
                'navHide' => false,
                'noIndex' => false,
                'abstract' => 'All 25 themes side by side, each card shown in its own theme.',
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
                'abstract' => 'How this theme supports keyboards, screen readers and reduced motion.',
                'role' => 'legal',
            ],
            [
                'title' => 'Page not found',
                'slug' => 'not-found',
                'layout' => self::LAYOUT_ERROR,
                'navHide' => true,
                'noIndex' => true,
                'abstract' => 'This page does not exist or has moved. Use the navigation above to continue.',
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
                'ctype' => 'astryx_typo3_herosplitmedia',
                'fixture' => [
                    'eyebrow' => 'Astryx for TYPO3',
                    'header' => 'Meta\'s design system, rendered by TYPO3',
                    'lead' => '250 content elements built on Astryx, the open-source design system from Meta. 25 themes in light and dark mode, and no React on the page.',
                    'cta_label' => 'See all elements',
                    'cta_link' => 't3://page?uid=__HUB__',
                    'secondary_label' => 'Compare the themes',
                    'secondary_link' => 't3://page?uid=__THEMES__',
                    'image' => [['file' => 'EXT:astryx_typo3/Resources/Public/Images/scene/office-standup.jpg', 'alternative' => 'An editorial team reviewing a page layout together.', 'title' => '']],
                    'caption' => 'Editors work in the page module as usual. The theme does not change that.',
                    'tone' => 'body',
                    'width' => 'lg',
                ],
            ],
            [
                'ctype' => 'astryx_typo3_datakpirow',
                'fixture' => [
                    'eyebrow' => 'What you get',
                    'header' => 'The numbers, counted in the code',
                    'lead' => 'Counted from the repository, not rounded for a slide.',
                    'columns' => '4',
                    'tone' => 'surface',
                    'align' => 'center',
                    'width' => 'lg',
                    'note' => 'Counted from the repository: 250 element folders, 25 themes and 1,600 colour pairs checked by the contrast audit.',
                    'metrics' => [
                        ['value' => '250', 'unit' => 'elements', 'title' => 'In ten editor groups', 'text' => 'Hero, features, content, pricing, social proof, team, data, conversion, navigation and footer.'],
                        ['value' => '25', 'unit' => 'themes', 'title' => 'Per site or per page', 'text' => 'A new theme changes the look and never touches the content.'],
                        ['value' => '0', 'unit' => 'frameworks', 'title' => 'No React, no build step', 'text' => 'Fluid templates and plain CSS. Almost every element works without JavaScript.'],
                        ['value' => '1,600', 'unit' => 'colour pairs', 'title' => 'Checked against WCAG 2.2 AA', 'text' => 'The build checks every text and background pair in all 25 themes, in light and dark mode.'],
                    ],
                ],
            ],
            [
                'ctype' => 'astryx_typo3_featuregrid',
                'fixture' => [
                    'eyebrow' => 'Why Astryx',
                    'header' => 'A design system that works as designed',
                    'lead' => 'The design tokens come from Astryx itself. We ran its own theme compiler and scoped the result, so any page can use any theme.',
                    'columns' => '3',
                    'tone' => 'body',
                    'width' => 'lg',
                    'items' => [
                        ['title' => 'Original tokens, not a copy', 'text' => 'Colours, spacing, radii and type come from the Astryx compiler. The upstream commit is recorded in the repository.'],
                        ['title' => 'One switch for the whole site', 'text' => 'Elements use only tokens, and an audit stops the build on any fixed colour. So one theme change reaches all 250 elements.'],
                        ['title' => 'Light and dark from one palette', 'text' => 'Every colour is a light and dark pair. Colour scheme and theme switch independently.'],
                        ['title' => 'Measured accessibility', 'text' => 'The build checks 1,600 colour pairs across 25 themes against WCAG 2.2 AA. It stops if one pair fails.'],
                        ['title' => 'Editors keep the page module', 'text' => 'Every element has a backend preview, keyword search and a live preview in the element picker.'],
                        ['title' => 'No third-party requests', 'text' => 'Fonts are served from your own domain. Your visitors\' browsers contact no one else.'],
                    ],
                ],
            ],
            [
                'ctype' => 'astryx_typo3_featuresteps',
                'fixture' => [
                    'eyebrow' => 'Getting started',
                    'header' => 'Three steps to a themed site',
                    'lead' => 'There is no build step and nothing to compile before editors can start.',
                    'columns' => '3',
                    'tone' => 'surface',
                    'width' => 'lg',
                    'steps' => [
                        ['title' => 'Install the extension', 'text' => 'Require webconsulting/astryx-typo3. It works next to Desiderio, so each site can use either.'],
                        ['title' => 'Add two site sets', 'text' => 'Add the base set and the content element set. The wizard then shows only the Astryx elements.'],
                        ['title' => 'Pick a theme', 'text' => 'Set the theme for the site, and override it on any page below. No rebuild is needed.'],
                    ],
                ],
            ],
            [
                'ctype' => 'astryx_typo3_pricinglicencetiers',
                'fixture' => [
                    'eyebrow' => 'Pricing',
                    'header' => 'Free to use. Paid plans add support.',
                    'lead' => 'Astryx for TYPO3 is free under GPL-2.0-or-later, with all 250 elements and 25 themes. Paid plans add support and guarantees, not features.',
                    'note' => 'Prices exclude VAT. Plans are billed monthly or yearly and can be cancelled at the end of the term. Astryx is MIT-licensed, © Meta Platforms, Inc. and affiliates.',
                    'cta_label' => 'See all elements',
                    'cta_link' => 't3://page?uid=__HUB__',
                    'tone' => 'surface',
                    'width' => 'lg',
                    'tiers' => [
                        [
                            'title' => 'Community',
                            'value' => '€0',
                            'text' => 'The complete extension under GPL-2.0-or-later: every element, theme and tool. Unlimited sites and no registration. Questions go to the public issue tracker.',
                        ],
                        [
                            'title' => 'Pro',
                            'value' => '€49',
                            'text' => 'Per month, or €490 per year. Email support within two working days, LTS compatibility updates and early access to new elements.',
                        ],
                        [
                            'title' => 'Agency',
                            'value' => '€149',
                            'text' => 'Per month, or €1,490 per year. Everything in Pro for unlimited client projects, answers within four business hours (CET) and a yearly review of your theme overrides.',
                        ],
                        [
                            'title' => 'Installation',
                            'value' => '€890',
                            'text' => 'One-off. We install and set up the extension, choose the theme with you and seed a demo page tree. A theme in your brand colours starts at €1,990.',
                        ],
                    ],
                ],
            ],
            [
                'ctype' => 'astryx_typo3_accordionfaq',
                'fixture' => [
                    'eyebrow' => 'Questions',
                    'header' => 'Common questions, answered honestly',
                    'lead' => 'Including the ones with an awkward answer.',
                    'width' => 'md',
                    'tone' => 'body',
                    'questions' => [
                        ['title' => 'Is this an official Meta product?', 'bodytext' => '<p>No. Meta publishes Astryx under the MIT licence, and this extension builds on it. Meta is not involved and does not endorse it. Only the design tokens and the component documentation are used.</p>'],
                        ['title' => 'Does it run React in the frontend?', 'bodytext' => '<p>No. Astryx itself uses React and StyleX. This extension uses Fluid and CSS: the same look with a different runtime. One small script handles the few behaviours that need JavaScript.</p>'],
                        ['title' => 'Can I use it next to Desiderio?', 'bodytext' => '<p>Yes. Both run in the same TYPO3, and each site chooses its theme. The element picker on each site shows only that theme\'s elements.</p>'],
                        ['title' => 'Does every theme meet WCAG 2.2 AA?', 'bodytext' => '<p>Yes. On every build, a script checks all 1,600 colour pairs in 25 themes, in light and dark mode. The build fails if one pair misses the threshold.</p><p>Some original Astryx colours fall short, for example secondary text in a few themes. A generated file darkens only the failing colour, by the smallest step that reaches 4.5:1, so each colour keeps its hue.</p>'],
                        ['title' => 'What happens when Astryx changes?', 'bodytext' => '<p>The design tokens are stored with the upstream commit they came from. One command regenerates them, and nothing changes until you run it.</p>'],
                    ],
                ],
            ],
            [
                'ctype' => 'astryx_typo3_conversionclosingcta',
                'fixture' => [
                    'eyebrow' => 'Try it first',
                    'header' => 'See every element on a live page',
                    'subheader' => 'The running site, not screenshots',
                    'lead' => 'All elements are on this site, one chapter per editor group, each chapter in a different theme.',
                    'cta_label' => 'Browse the catalogue',
                    'cta_link' => 't3://page?uid=__HUB__',
                    'secondary_label' => 'Astryx on GitHub',
                    'secondary_link' => 'https://github.com/facebook/astryx',
                    'note' => 'Astryx is MIT-licensed, © Meta Platforms, Inc. and affiliates. This extension is GPL-2.0-or-later, like TYPO3.',
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
                'bodytext' => '<p>This is a demo site for the Astryx design system on TYPO3. It is not a business. This page is a placeholder for a real imprint.</p>'
                    . '<p>A live site replaces this text with the details its jurisdiction requires: operator, address, contact, registration and supervisory authority.</p>',
            ],
            'privacy' => [
                'header' => 'What this site stores and loads',
                'bodytext' => '<p>This demo site sets no tracking cookies of its own. Fonts come from this domain, so a normal page sends no requests to anyone else.</p>'
                    . '<p>Video is the exception. The video chapters embed a YouTube recording through the privacy-enhanced <em>youtube-nocookie.com</em> address. It sets no tracking cookies until the video plays. Two video elements load the player only when you press play, and a closed dialog never loads its iframe. The plain embed loads the player with the page.</p>'
                    . '<p>Your browser stores only one thing: the light or dark mode you choose in the header. It never leaves your device, and clearing your site data removes it.</p>'
                    . '<p>A live site replaces this page with a privacy notice about the data it actually processes.</p>',
            ],
            'accessibility' => [
                'header' => 'How this theme is built',
                'bodytext' => '<p>The theme works without a mouse and without seeing the layout. Every page has one h1 that names the page. Each content element starts its section with an h2, so the headings read like a table of contents.</p>'
                    . '<p>Focus is always visible. The focus ring uses the theme\'s accent colour token, so it stays visible in all 25 themes and in both colour schemes.</p>'
                    . '<p>Contrast is measured, not assumed. A script checks all 1,600 colour pairs in the stylesheets: 25 themes in light and dark mode. The thresholds are those of WCAG 2.2 AA: 4.5:1 for body text, and 3:1 for borders, focus rings and meaningful graphics. The build fails if a single pair misses. Where original Astryx colours fall short, a generated file adjusts only the failing colour, and only as far as the threshold.</p>'
                    . '<p>Colour is never the only signal. The current navigation item also has a heavier font weight, and a status also has a text label.</p>'
                    . '<p>Animation follows your system setting. Everything that moves is wrapped in a reduced-motion query. If you ask your system for less motion, you get a still page, not a slower one.</p>'
                    . '<p>Menus use native disclosure elements, dialogs use the native dialog element, and the colour scheme switch is a real button. So keyboard behaviour comes from the browser and is not rebuilt in scripts.</p>',
            ],
            'not-found' => [
                'header' => 'No page at this address',
                'bodytext' => '<p>Nothing on this site matches the address you followed. The page may have been renamed, or the link may be out of date.</p>',
            ],
        ];
    }
}
