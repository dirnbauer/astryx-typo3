..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _introduction-what-it-is:

What this extension is
======================

Astryx for TYPO3 is a theme extension: a page shell, a component library and a
catalogue of 250 Content Blocks, all of them drawn with the Astryx design
language. A site that installs it gets ten wizard groups of twenty-five content
elements, twenty-five themes that a visitor's browser repaints without a build
step, and a header, footer, breadcrumb and error page driven by site settings.

Underneath the catalogue sit 74 Fluid components arranged in four layers, and
every template — element, page and search — is written in terms of those
components rather than in CSS classes:

..  code-block:: html
    :caption: ContentBlocks/ContentElements/hero-split-media/templates/frontend.html (excerpt)

    <a:layout.section surface="{data.tone}" class="g-hero-split-media">
        <a:layout.container size="{data.width}">
            <a:atom.heading level="1">{data -> f:render.text(field: 'header')}</a:atom.heading>

That is the whole authoring surface. What a component renders, which class it
carries and which data attributes express its modifiers are decided once, in
:ref:`the component contract <developer-component-contract>`, and enforced by
the test suite.

..  _introduction-what-it-is-not:

What it is not
==============

It is not a port of Astryx's React code, and it redistributes none of it.

Upstream Astryx is React components styled with StyleX, and StyleX compiles
styles into atomic class names at build time — `.x1a2b3c` — which are generated
per build and are not addressable from outside. There is no `astryx.css` to
link and no stable atomic class to target. What upstream does publish, and what
this extension is built on, is two things: the token definitions together with
the compiler that turns them into CSS custom properties per theme, and a set of
stable, human-readable class names such as `.astryx-button` and `.astryx-card`
that each theme's own override rules are written against. Markup carrying those
names receives a theme's component rules whether or not it came from React,
which is what makes a non-React Astryx honest rather than a lookalike.

Only two files are vendored from upstream — :file:`Build/astryx/components.json`
and :file:`Build/astryx/tokens.json` — and both are produced by upstream's own
CLI and theme compiler rather than transcribed. No React, no StyleX and no other
upstream runtime is shipped. Astryx is MIT, © Meta Platforms, Inc.; this
extension is GPL-2.0-or-later, like TYPO3, and there is no affiliation with or
endorsement by Meta. The exact notice is in :file:`THIRD_PARTY_NOTICES.md`.

..  _introduction-desiderio:

The relationship to Desiderio
=============================

`Desiderio <https://github.com/dirnbauer/desiderio>`__ is a dependency of this
extension — `webconsulting/desiderio` at `^4.1` — and it is an **engine**
dependency, not a design one. None of the design comes from it. What comes from
it is the machinery every theme would otherwise have to build for itself:

*   Page rendering. The base site set depends on `webconsulting/desiderio` and
    inherits its `PAGE` object and `PAGEVIEW` data processors; this extension
    replaces the visible layer — assets, page templates, theme attributes — and
    leaves that machinery alone.
*   The element library. :file:`ext_localconf.php` appends `astryx_typo3` to
    Desiderio's `libraryHostExtensions`, so the plus-button picker lists this
    extension's blocks with the same previews, keyword chips and "when to use"
    descriptions as Desiderio's own.
*   The seeders. :php:`SeedAstryxSiteCommand` is written against Desiderio's
    seeding services, and the library itself is filled by Desiderio's
    `desiderio:library:seed`, `desiderio:library:warm` and
    `desiderio:library:urls` commands. See :ref:`developer-commands`.
*   The icon vocabulary. The `icon` select fields are filled by Desiderio's icon
    processor, so :php:`IconViewHelper` resolves the same semantic keys through
    Desiderio's `IconRegistry` — although the markup is this extension's own,
    because Desiderio's own ViewHelper emits one copy of the glyph per supported
    library and relies on a stylesheet this theme does not load.
*   The shared record types. The eight repeatable child types under
    :file:`ContentBlocks/RecordTypes/` are registered with Desiderio's
    `recordTypePaths` so its seeders can resolve a collection's `foreign_table`.

A site therefore installs both, and chooses one theme or the other. Desiderio's
own elements are kept out of this theme's picker with the `elementLibrary.hosts`
site setting.

..  _introduction-requirements:

Requirements
============

From :file:`composer.json`:

*   TYPO3 `^14.3.7` (`typo3/cms-core` and `typo3/cms-fluid`).
*   PHP `^8.4`.
*   `friendsoftypo3/content-blocks` `^2.2`.
*   `praetorius/vite-asset-collector` `^1.18`.
*   `webconsulting/desiderio` `^4.1`.

`friendsoftypo3/visual-editor` is suggested rather than required: it adds inline
frontend editing and the element-library plus-button, and everything renders
without it.

Three site sets ship with the extension, all of them hidden so that a site
includes them deliberately:

*   `webconsulting/astryx-typo3` — the base set: assets, page templates, theme
    attributes and settings.
*   `webconsulting/astryx-typo3-content-elements` — generated by the scaffolder,
    and the file that puts the New Content Element wizard into allow-list mode.
    An element missing from it renders fine but never appears in the wizard.
*   `webconsulting/astryx-typo3-search` — the Astryx skin for EXT:solr. It is
    separate because it pulls in `webconsulting/solr-defaults`, the whole Solr
    stack, which most sites do not want until they have a results page.

..  _introduction-changes-2-0:

What 2.0.0 changed at a glance
==============================

Version 2.0.0 is a breaking release, and all of it is about how markup is
written rather than about what a visitor sees.

*   **Templates compose components.** All 250 element templates, the 7 page
    templates, the 6 page partials and the 16 Solr templates render
    `<a:layout.section>`, `<a:atom.button>` and the rest through the component
    collection registered under the `a` namespace. A template that applied an
    `astryx-*` class directly no longer does, and the conformance test fails if
    one starts again. A site package overriding a template has to be rewritten
    against the components.
*   **Bare modifier classes are gone.** `class="astryx-button primary lg"` is
    now `<a:atom.button variant="primary" size="lg">`, which renders
    `data-variant="primary" data-size="lg"`. This follows upstream: Astryx
    itself removed bare prop and state classes at v0.6.0. A site stylesheet
    selecting `.astryx-card.flat` must now select
    `.astryx-card[data-variant="flat"]`.
*   **The vendored Astryx data moved to v0.6.0**, with the token and component
    differences reported in :file:`Build/Reports/astryx-0.3.0-to-0.6.0.md`. See
    :ref:`developer-upstream-sync`.
*   **The rules became tests.** The standalone
    `scripts/audit-content-elements.php` is gone; its rules are
    :php:`Tests\Unit\ContentElementAuditTest`, and CI runs them alongside
    the new :php:`AtomicDesignConformanceTest` and two functional
    tests. A gate nobody runs is not a gate.
*   **The engine requirement rose** to `webconsulting/desiderio` `^4.1` and
    `typo3/cms-*` `^14.3.7`.

The full list, including what was fixed along the way, is in
:file:`CHANGELOG.md`.
