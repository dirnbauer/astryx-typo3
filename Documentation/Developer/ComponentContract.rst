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

The 74 components live in four layers, in Brad Frost's order:

*   **Layout** — the page's skeleton: `Section`, `Container`, `Grid`, `Split`,
    `Stack`, `Center`. Imports nothing.
*   **Atom** — one indivisible thing: `Heading`, `Text`, `Button`, `Link`,
    `Badge`, `Icon` and fourteen more. Imports nothing.
*   **Molecule** — atoms in a named arrangement: `Card`, `Item`, `Table`,
    `Collapsible`, `Dialog`, `Field` and thirty-six more. May import Atom and
    Layout.
*   **Organism** — a whole region of a page: `SiteHeader`, `SiteFooter`,
    `Breadcrumb`, `PageHeader`, `SearchForm`, `ThemeSwitcher`. May import
    anything.

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
:php:`ComponentRenderingTest` renders all 74 components with their required
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

#.  Add a row to :file:`Build/Data/component-contract.json` with its `layer`,
    its `name`, its `rootClass` and its `modifiers`. The row is what the tests
    check against, so it comes first.
#.  Create
    :file:`Resources/Private/Components/<Layer>/<Name>/<Name>.fluid.html`.
    Declare every attribute with `<f:argument>`, render exactly the root class
    from the contract, and put the modifiers on data attributes.
#.  Write a `<f:comment>` at the top saying *why* the component is shaped the
    way it is. Every component in the repository does, and it is where the
    accessibility decisions live.
#.  Add the component's CSS to the right partial under
    :file:`Resources/Private/Css/components/` and rebuild
    (:ref:`developer-commands`). The tree-shake in
    :file:`Build/Scripts/build-astryx-css.mjs` drops rules whose selectors name
    only classes nothing renders, so a partial written before its call site will
    not survive the build.
#.  If the component stands in for an upstream Astryx component that the element
    matrix names, add the mapping to :file:`Build/Data/component-map.json`.
#.  Run `composer test:unit` and `composer test:functional`.

..  _developer-component-contract-tests:

What the conformance test checks
================================

:php:`Tests\Unit\AtomicDesignConformanceTest` is the gate. Its failure messages
are written to tell you what to do, so read them rather than the test.

*   **No template applies an Astryx class directly.** Reported per file and
    line, with the codemod to run. Templates here means all 250 element
    templates, the page templates, partials and layouts, and every Solr
    template.
*   **No template carries a bare modifier token**, where "modifier" is any token
    the contract knows. This is the v0.6.0 rule.
*   **Every content element roots in** `a:layout.section`. Comments are stripped
    before the scan, because three elements explain in prose why they do *not*
    use a `<time>`, an `<address>` or an `<article>`, and a scan that reads the
    comment finds the tag name in the explanation.
*   **The layer graph is one-way**, as above.
*   **Every component named in a template exists on disk**, and every component
    the contract promises exists on disk, and the contract and the disk agree
    about which components are in each layer — that last one per layer, so a
    failure names the layer and lists both directions of the difference.
*   **Every component renders its own root class**, checked against the
    contract.
*   **Every matrix reference is mapped.** A matrix row may not name an Astryx
    component that :file:`Build/Data/component-map.json` does not map, nor one
    that is absent from the vendored inventory for the pinned release. The
    second half is what makes an upstream refresh visible in the catalogue
    rather than only in the token file.
*   **The component map points at real components**, with matching root classes.
*   **No stylesheet still selects a modifier as a class**, checked over the
    component partials, the page chrome and all 250 element stylesheets.

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

The entry is mirrored in
:file:`Build/Scripts/refactor-templates-to-components.php`, which must not
rewrite what the test permits.

The allowlist has one rule of its own, and it is the unusual one: **an entry
that stops matching must fail.** The test counts hits per entry and asserts that
each count is greater than zero, with a message telling you to delete the entry.
An allowlist nobody has read in a year is an allowlist that will eventually
excuse something it was never meant to, so the test treats a stale exception as
a defect rather than as harmless.

..  _developer-component-contract-codemod:

The codemod
===========

The migration from classes to components was done by
:file:`Build/Scripts/refactor-templates-to-components.php`, and it is still the
tool the conformance test's failure message points at.

..  code-block:: bash
    :caption: Rewriting templates

    php Build/Scripts/refactor-templates-to-components.php --dry-run
    php Build/Scripts/refactor-templates-to-components.php --only=accordion-faq
    php Build/Scripts/refactor-templates-to-components.php --group=hero
    php Build/Scripts/refactor-templates-to-components.php --pages --solr

It is deliberately not a parser. A Fluid template is not XML and does not
survive being treated as XML: `<f:if>` around half an opening tag, a condition
containing `||` inside an attribute, an `<f:for>` wrapping an `<li>` that closes
in a different branch. So the rewrite matches one opening tag at a time by its
class attribute and finds that tag's own closing tag by counting depth over the
same tag name, leaves alone anything it is not certain about, and reports what
it left. Passes run outer-first — section and container, then the arrangement
components, then blocks, then typography, then the inline atoms — because
rewriting an inner tag first would leave the outer tag's depth count looking at
tags that no longer exist. Organisms are never rewritten automatically: their
markup carries JavaScript hooks and site settings that no regular expression
should be trusted with.

The matching CSS migration is
:file:`Build/Scripts/migrate-component-css.mjs`, which turns
`.astryx-button.primary` into `.astryx-button[data-variant="primary"]` using the
same contract. Specificity is unchanged by the move — a class and an attribute
selector weigh the same — so nothing in the cascade shifts. A handful of state
classes stay classes on purpose, listed in `RUNTIME_STATE`: they are toggled by
:file:`Resources/Public/Js/astryx.js` at runtime and have no authoring surface
to drift from. Selectors whose modifier is not in the contract are left alone
and listed, so a gap is visible rather than silently rewritten into something
plausible.
