..  include:: /Includes.rst.txt

..  _developer-component-contract:

======================
The component contract
======================

Since 2.0.0 no template in this extension writes a CSS class that belongs to the
design system. Templates compose components, components render markup, and the
vocabulary both of them use is stated once in
:file:`Build/Data/component-contract.json`. The codemod, the CSS migration, the
CSS tree-shake and the conformance test all read that one file, so a modifier
cannot mean one thing in the markup and another in the stylesheet.

..  _developer-component-contract-layers:

Four layers, one direction
==========================

The 189 components live in four layers, in Brad Frost's order:

*   **Layout** — the page's skeleton: `Section`, `Container`, `Grid`, `Split`,
    `Stack`, `Center`, `ScrollableArea` and seven more. Imports nothing.
*   **Atom** — one indivisible thing: `Heading`, `Text`, `Button`, `Link`,
    `Badge`, `Icon`, `TextArea`, `Switch` and thirty-five more. Imports
    nothing.
*   **Molecule** — atoms in a named arrangement: `Card`, `Item`, `Table`,
    `Collapsible`, `Dialog`, `Field`, `DropdownMenu`, `Typeahead` and a
    hundred and nine more. May import Atom and Layout.
*   **Organism** — a whole region of a page: `SiteHeader`, `SiteFooter`,
    `TopNav`, `SideNav`, `CommandPalette`, `ChatComposer` and nine more. May
    import anything.

Which layer a component belongs in is a question about what it composes, not
about how big it is: a component that reaches for another `a:` component is at
least a Molecule, and one that composes Molecules into a page region is an
Organism.

The direction is one-way and is enforced rather than agreed:
:php:`AtomicDesignConformanceTest::theLayerGraphIsOneWay()` reads every `<a:…>`
tag out of every component file and compares its layer against a table. A layer
may use itself — that is how `Card` reaches `CardTitle` — but never a layer
above it.

..  _developer-component-contract-resolution:

How a component is resolved
===========================

:php:`Webconsulting\AstryxTypo3\Components\ComponentCollection` extends Fluid's
:php:`AbstractComponentCollection` and points at one directory:

..  code-block:: php
    :caption: Classes/Components/ComponentCollection.php

    $templatePaths->setTemplateRootPaths([
        GeneralUtility::getFileAbsFileName(
            'EXT:astryx_typo3/Resources/Private/Components/',
        ),
    ]);

Resolution is Fluid's own `resolveTemplateName()`: the dotted path is the
directory and the last fragment is both the directory and the file name, so
`a:atom.button` resolves to
:file:`Resources/Private/Components/Atom/Button/Button.fluid.html`.

The `a` namespace is registered globally in :file:`ext_localconf.php`, because a
component template has no `<html>` tag to hang an `xmlns` on and without the
global registration one component could not compose another. Element templates
still declare the namespace themselves so they read as self-contained, and
:php:`AtomicDesignConformanceTest::everyTemplateUsingComponentsDeclaresTheNamespace()`
insists on it — Fluid renders an unknown-namespace tag as literal text rather
than failing, so a missing declaration is invisible until somebody reads the
page source.

..  _developer-component-contract-root-class:

One root class, and modifiers as data attributes
================================================

Every component renders exactly one class of its own, `astryx-<name>`, and
expresses its modifiers as data attributes:

..  code-block:: html
    :caption: Resources/Private/Components/Atom/Button/Button.fluid.html (excerpt)

    <button type="{type}" class="astryx-button {class}" aria-current="{ariaCurrent}"
        data-variant="{variant}" data-size="{size}" data-width="{width}"
        title="{title}" aria-label="{ariaLabel}"><f:slot /></button>

The reason is upstream, not taste. Astryx removed bare prop and state classes at
v0.6.0: across the compiled theme rules, `.astryx-x.modifier` selectors went
from 423 to zero while `.astryx-x[data-…]` went from zero to 482
(:ref:`developer-upstream-sync-0-6-0`). Since the point of carrying upstream's
class names is that a theme's override rules land on this extension's markup,
the markup has to speak the shape those rules are written in.

The one-root-class rule has a second consequence worth knowing about. Before
2.0.0 an icon button was written `class="astryx-button astryx-icon-button"` and
inherited its shape, hover, press and focus ring from the button beside it. A
component that renders one class inherits nothing, so `.astryx-icon-button` now
declares all of it. The design review found this the moment the harness rendered
one on its own.

`Build/Data/component-contract.json` names the attribute vocabulary —
`align`, `cols`, `columns`, `level`, `size`, `spacing`, `surface`, `tone`,
`variant` and the rest — and records, per component, which 1.x modifier token
maps onto which attribute and value. Eight of the names are upstream's own
(`data-variant`, `data-size`, `data-type`, `data-color`, `data-status`,
`data-level`, `data-weight`, `data-selected`), taken from the compiled theme
rules; the others are this extension's, for modifiers upstream has no equivalent
for.

..  _developer-component-contract-arguments:

Every attribute is declared
===========================

A component declares each attribute it accepts with `<f:argument>`:

..  code-block:: html
    :caption: Resources/Private/Components/Atom/Heading/Heading.fluid.html (excerpt)

    <f:argument name="level" type="string" optional="{true}" default="2" />
    <f:argument name="type" type="string" optional="{true}" default="default" />
    <f:argument name="weight" type="string" optional="{true}" default="default" />
    <f:argument name="color" type="string" optional="{true}" default="default" />

This is not documentation, it is the interface. Fluid 5 validates component
arguments at parse time: an attribute a component never declares does not reach
the component, so the markup would be right in the template and wrong on the
page. :php:`AtomicDesignConformanceTest::everyComponentDeclaresTheArgumentsItsCallSitesPass()`
scans every call site in every template and component and reports any attribute
that has no matching `<f:argument>`, and the functional test
:php:`ComponentRenderingTest` renders all 189 components with their required
arguments filled, so a renamed or newly required argument fails in CI rather
than on a live page.

..  _developer-component-contract-slots:

Slots
=====

Content reaches a component through `<f:slot />`. Where a component has more
than one hole to fill it declares named slots and renders each only when it has
something in it:

..  code-block:: html
    :caption: Resources/Private/Components/Molecule/Figure/Figure.fluid.html (excerpt)

    <figcaption><f:slot name="caption" /></figcaption>

A caller fills a named slot with `<f:fragment name="caption">…</f:fragment>`.
Table is the fullest example, with `caption`, `head`, `body` and `foot`; Card
has `media`, Dialog has `footer`, Item has `start` and `end`, and Pagination has
`info`.

..  note::
    At the time of writing no template in the repository fills a named slot —
    every `<f:fragment>` search comes back empty. The named slots are declared
    and rendered by the components, and the default slot is what the 250 element
    templates use.

..  _developer-component-contract-adding:

Adding a component
==================

#.  Create
    :file:`Resources/Private/Components/<Layer>/<Name>/<Name>.fluid.html`.
    Declare every attribute with `<f:argument>`, render one root class —
    `astryx-` plus the kebab-case of the component's name — and put the
    modifiers on data attributes.
#.  Write a `<f:comment>` at the top saying *why* the component is shaped the
    way it is. Every component in the repository does, and it is where the
    accessibility decisions live.
#.  Add the component's CSS to the right partial under
    :file:`Resources/Private/Css/components/` and rebuild
    (:ref:`developer-commands`). The tree-shake in
    :file:`Build/Scripts/build-astryx-css.mjs` drops rules whose selectors name
    only classes nothing renders, so a partial written before its call site will
    not survive the build.
#.  Run `npm run build:contract`. It writes the component's row into
    :file:`Build/Data/component-contract.json` and, if upstream Astryx has a
    component of the same name, its entry in
    :file:`Build/Data/component-map.json`. Neither file is edited by hand. If
    the root class is deliberately not the kebab-case of the name, or the
    upstream component is called something else here, the script refuses and
    tells you which of its two tables to add the reason to.
#.  Run `composer test:unit` and `composer test:functional`.

..  _developer-component-contract-tests:

What the conformance test checks
================================

:php:`Tests\Unit\AtomicDesignConformanceTest` is the gate. Its failure messages
are written to tell you what to do, so read them rather than the test.

*   **No template applies an Astryx class directly.** Reported per file and
    line. Templates here means all 250 element templates, the page templates,
    partials and layouts, and every Solr template. `f:variable` bodies whose
    name ends in `Class` count as class attributes: assembling
    `astryx-grid cols-3` into a variable and interpolating it is the same thing
    written somewhere a naive scan does not look, and two elements did exactly
    that for a year.
*   **No template writes a class with no owner.** A class is `astryx-` (the
    design system), `g-` (an element's own namespace), or one of nine EXT:solr
    names that are that extension's API and are listed with the reason. A bare
    word is a class nobody can name the owner of.
*   **Every content element roots in** `a:layout.section`. Comments are stripped
    before the scan, because three elements explain in prose why they do *not*
    use a `<time>`, an `<address>` or an `<article>`, and a scan that reads the
    comment finds the tag name in the explanation.
*   **The layer graph is one-way**, as above.
*   **Every component named in a template exists on disk.**
*   **Every matrix reference is mapped.** A matrix row may not name an Astryx
    component that :file:`Build/Data/component-map.json` does not map, nor one
    that is absent from the vendored inventory for the pinned release. The
    second half is what makes an upstream refresh visible in the catalogue
    rather than only in the token file.
*   **No stylesheet selects a modifier as a class**, checked over the component
    partials, the page chrome and all 250 element stylesheets. It matches the
    shape `.astryx-x.foo`, not a list of known modifiers: the list is what let a
    hundred and fifty 1.x selectors survive in components that had CSS but no
    Fluid component yet.

..  _developer-component-contract-allowlist:

The class allowlist
-------------------

One class is still allowed in a template, with its reason attached:

..  code-block:: php
    :caption: Tests/Unit/AtomicDesignConformanceTest.php

    private const CLASS_ALLOWLIST = [
        'astryx-prose' => 'Styles the descendants of editor rich text — markup no template ever sees, '
            . 'so it is a stylesheet hook rather than a component with a call site.',
    ];

The allowlist has one rule of its own, and it is the unusual one: **an entry
that stops matching must fail.** The test counts hits per entry and asserts that
each count is greater than zero, with a message telling you to delete the entry.
An allowlist nobody has read in a year is an allowlist that will eventually
excuse something it was never meant to, so the test treats a stale exception as
a defect rather than as harmless.

..  _developer-component-contract-generated:

What is generated
=================

:file:`Build/Data/component-contract.json` and
:file:`Build/Data/component-map.json` are both written by
:file:`Build/Scripts/sync-component-contract.mjs` from
:file:`Resources/Private/Components/`, as the first step of `npm run build`. CI
runs the build and then `git diff --exit-code`, so a component added without
regenerating them fails there.

Almost nothing in either file is a decision. The layer is the directory, the
name is the directory below it, and the root class is `astryx-` plus the
kebab-case of the name — checked against the component's own markup, so a
component that renders something else fails the sync rather than being recorded
as rendering it. Two small tables in the script hold what cannot be derived:
`ROOT_CLASS`, for eight page-chrome classes that predate the Astryx vocabulary
and would break a site's own stylesheet if renamed, and `ALIASES` /
`NOT_RENDERED`, for the handful of upstream components this library renders
under another name or cannot render at all.

`NOT_RENDERED` is the honest half of the coverage claim. Five upstream
components are React context providers that render no DOM of their own —
`Theme`, `MediaTheme`, `LinkProvider`, `InternationalizationProvider` and
`AppShell` — and a server-rendered library cannot have them. Each is recorded
with the reason, and any upstream component that is neither rendered nor listed
fails the sync, so the gap cannot grow quietly.

..  code-block:: bash
    :caption: Regenerating

    npm run build:contract
