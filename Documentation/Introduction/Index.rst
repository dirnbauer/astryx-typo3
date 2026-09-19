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

Underneath the catalogue sit 189 Fluid components arranged in four layers, and
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

..  _introduction-next:

Where to go next
================

:ref:`installation` for the requirements, the Composer command and the three
site sets. :ref:`configuration` for what a site chooses. :ref:`usage` for
editors and integrators. :ref:`developer` for the component contract, the
upstream sync and the design review. :ref:`changelog` for what changed and what
it means for a site package.
