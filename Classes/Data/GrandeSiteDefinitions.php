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
                'header' => 'Imprint',
                'bodytext' => '<p>This is a demonstration site for the Astryx design system running on TYPO3. It is not a business, and this page is a placeholder where a real imprint would go.</p>'
                    . '<p>A production site replaces this text with the disclosures its jurisdiction requires — operator, address, contact, registration and supervisory details.</p>',
            ],
            'privacy' => [
                'header' => 'Privacy',
                'bodytext' => '<p>This demonstration site sets no tracking cookies and embeds no third-party services. Fonts are served from this domain, so visiting a page makes no request to anyone else.</p>'
                    . '<p>The one thing stored in your browser is the light/dark preference you pick with the toggle in the header. It never leaves your device, and clearing your site data removes it.</p>'
                    . '<p>A production site replaces this page with a privacy notice describing what it actually processes.</p>',
            ],
            'accessibility' => [
                'header' => 'Accessibility',
                'bodytext' => '<p>The theme is built to work without a mouse and without sight of the layout. Every page has one h1 that says what the page is; content elements head their own band with an h2, so the outline reads as a table of contents.</p>'
                    . '<p>Focus is always visible, and the accent-coloured ring is drawn from the same token as the rest of the theme, so it stays visible in every one of the seven themes and in both colour schemes.</p>'
                    . '<p>State is never carried by colour alone: a current navigation item is also heavier, a status also carries text.</p>'
                    . '<p>Animation is opt-out at the source. Anything that moves is wrapped in a reduced-motion query, so a visitor who asks their system for less motion gets a still page rather than a slower one.</p>'
                    . '<p>Menus are native disclosures, dialogs are the native dialog element and the colour-scheme switch is a real button, so keyboard behaviour comes from the browser instead of being reimplemented.</p>',
            ],
            'not-found' => [
                'header' => 'Page not found',
                'bodytext' => '<p>The address you followed does not lead anywhere on this site. It may have been renamed, or the link that brought you here may be out of date.</p>',
            ],
        ];
    }
}
