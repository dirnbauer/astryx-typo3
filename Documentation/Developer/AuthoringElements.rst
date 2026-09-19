..  include:: /Includes.rst.txt

..  _developer-authoring-elements:

===========================
Authoring a content element
===========================

The matrix decides *what* an element is; this page describes *how* it is built.
Everything an element is begins as a row in
:file:`Build/Data/matrix/<group>.json` — its title, its descriptions in both
languages, its keywords, the fields it uses and the Astryx components it
composes — and the row schema is documented in :file:`Build/Data/MATRIX.md`.
Nothing about an element is invented in the generated files: if it is not in the
matrix, it does not exist.

The scaffolder writes ten files per element
(:ref:`developer-commands-catalog`). Four of them are yours:

..  code-block:: text
    :caption: What a human authors

    templates/frontend.html          the markup
    assets/frontend.css              layout, and nothing but layout
    library.json + library.de.json   what an editor sees in the picker
    fixture.json                     what the showcase page shows

The other six — :file:`config.yaml`, the backend preview,
:file:`assets/icon.svg` and the two label files — are derived from the matrix
and are overwritten. :file:`ContentBlocks/ContentElements/hero-split-media/` is
the reference element; read it before writing anything.

..  _developer-authoring-elements-markup:

Markup
======

Since 2.0.0 an element template **composes components and may not write an
`astryx-*` class**. That is the rule the whole design system rests on, and
:php:`AtomicDesignConformanceTest` fails on any template that breaks it. If you
find yourself reaching for a class, the component you want either exists or
needs adding (:ref:`developer-component-contract-adding`).

*   Declare the three namespaces the scaffold puts at the top, `f:`, `cb:` and
    `a:`, and keep the generated
    `<f:asset.css identifier="g-<id>" href="{cb:assetPath()}/frontend.css"/>`
    line.
*   The element roots in `<a:layout.section>` — this is checked — and the
    section owns two things and nothing else: the vertical rhythm between one
    element and the next, and the surface it sits on. An editor's `frame_class`
    choice wins over the template's own `surface` default.
*   Inside it, `<a:layout.container>` sets the measure, and the arrangement
    components — `grid`, `split`, `stack`, `center` — place things.
*   Delete the scaffolded `TODO(astryx)` comment. A comment that stays must
    explain *why* something is done, never what the next line does.
*   Text fields render through `{data -> f:render.text(field: 'x')}`, which is
    what makes them editable in place in the visual editor. Rich text goes
    through `<f:format.html>{data.bodytext}</f:format.html>`.
*   The band's own heading is `<a:atom.heading level="2">`; items inside a grid
    or list head at `level="3"`. An element that opens a page — a hero, an
    article header, a pricing page header — is the exception and carries the
    page's `h1` at `level="1"`. See
    :ref:`developer-accessibility-headings` for what that implies.
*   An action is
    `<a:atom.button parameter="{data.cta_link}" variant="primary">`. The
    component resolves a TYPO3 link field itself, so a template never wraps
    a button in a link ViewHelper and never hands it classes.
*   Images loop over the file reference and take their alternative text from
    it: an `<f:for each="{data.image}" as="image">` around an
    `<f:image image="{image}" alt="{image.alternative}" …/>`.
*   Collections are `<f:for each="{data.<identifier>}" as="item">`; take the
    identifier from the element's own :file:`config.yaml` and never guess it.
*   Every field the element declares must actually change the output. A `tone`,
    `width` or `reverse` the template ignores is a field an editor will set and
    then wonder about.
*   No `<script>`. No `<d:` — those are Desiderio's components, styled by a
    stylesheet this theme does not load. The only permitted inline `style` is a
    `--custom-property` hand-off, which is how an editor-typed number reaches
    CSS.
*   Icons are drawn by `<g:icon name="{item.icon}"/>`, which resolves one of
    Desiderio's semantic keys through this extension's own ViewHelper. The `g:`
    namespace is registered globally, so no template declares it.

..  _developer-authoring-elements-semantics:

Semantics are not decoration
----------------------------

A quotation is `<blockquote>` inside `<figure>` with its attribution in
`<figcaption>`. A definition list is `<dl>`, `<dt>` and `<dd>`. A table is a
`<table>` with a caption and `<th scope>`, wrapped so it can scroll in a narrow
column. Footnotes and steps are ordered lists. Code is `<pre><code>`.

Anything a sighted reader gets from position, colour or an icon has to reach a
screen-reader user as text: a star rating states its value, an
included-or-excluded row says which it is, and a bar is `aria-hidden` decoration
over a number that is already written out. The components carry most of this
already — see :ref:`developer-accessibility`.

..  _developer-authoring-elements-css:

Stylesheet
==========

Layout only. Every colour, size, radius, shadow and font comes from a token,
which is exactly why one theme switch reaches all 250 elements at once. Reuse
the components rather than restyling them: if a component already exists, style
around it.

:php:`ContentElementAuditTest::everyElementStylesheetSpeaksOnlyInTokens()`
enforces the rules, and each of them has a reason:

*   **No raw colour** — no hex, `rgb()`, `hsl()` or `oklch()` outside a `var()`.
    A raw colour cannot answer to a theme switch.
*   **No `light-dark()`** — the tokens already carry both schemes; an element
    reaching for it is deciding something the theme decides.
*   **No `prefers-color-scheme`** — the scheme is carried by `color-scheme` on
    `:root`.
*   **No `!important`** — the cascade layers make it unnecessary.
*   **No literal `font-family`** — use `var(--font-family-*)`, or `inherit`.
*   **No `em` margin, padding, gap or inset** — spacing is a scale and `em` is
    not on it. An `0.2em` nudge is right about what it wants and wrong about
    where the number comes from: it resolved to 2.8px on 118 nodes in a design
    review. `em` stays legal for a glyph's own size, for
    `text-underline-offset`, and inside a `calc()` that reads a
    `--text-*-leading` token — centring a mark against a line box is measuring
    the text on purpose.
*   **One shared set of breakpoints**: 480, 640, 768 and 1024, plus their
    one-pixel neighbours. Elements that invent their own stop lining up with the
    ones beside them on a page.

Anything that moves stops under `prefers-reduced-motion`, and since 2.0.0 that
is answered globally rather than per partial
(:ref:`developer-accessibility-motion`).

Element stylesheets are also migrated with the components: a selector such as
`.astryx-card.flat` is now `.astryx-card[data-variant="flat"]`, and a stylesheet
that still names a modifier as a class fails the conformance test with the
migration command to run.

..  _developer-authoring-elements-fields:

Fields and collections
======================

Field identifiers come from :file:`Build/Data/field-library.json`, which holds
thirty-two of them, and adding one is a deliberate act rather than a side effect
of a single element. The constraint is not tidiness: Content Blocks with
`prefixFields: false` makes every field identifier an installation-wide
`tt_content` column, and `tt_content` has a hard structural limit
(:ref:`developer-commands-schema`). Two hundred and fifty elements each
inventing their own fields would not fit in the table.

The same limit is why every single-line text field here is a `Textarea` with one
row rather than a `Text`: `TEXT` is stored off-page for about twenty bytes,
while a `VARCHAR(255)` in `utf8mb4` costs over a kilobyte of the row budget.

A repeatable child list uses one of the eight shared record types in
:file:`ContentBlocks/RecordTypes/` and has three requirements, all checked by
:php:`ContentElementAuditTest::everyCollectionFieldIsSafeToShare()`:

..  code-block:: yaml
    :caption: ContentBlocks/ContentElements/feature-grid/config.yaml (excerpt)

    identifier: items
    type: Collection
    foreign_table: astryx_typo3_icon_lead
    shareAcrossTables: true

`prefixField: true`, so a collection does not collide with the next one on the
same element; `shareAcrossTables` and `shareAcrossFields` together, because the
foreign table is shared; and never the identifier `label`, which Content Blocks
reserves and which breaks the generated table.

The audit also checks the element's identity — `typeName: astryx_typo3_<id
without hyphens>`, `name: astryx-typo3/<id>` and `prefixFields: false`, all
three following from the directory name — and that every :file:`config.yaml`
title is unique across the catalogue.

..  _developer-authoring-elements-demo:

Demo content
============

:file:`library.json` is the strongest argument an element makes for itself: an
editor choosing between twenty-five siblings reads the copy as much as the
layout. Invent one plausible, ordinary business and keep it consistent across
the whole group — real sentences, real numbers, and German that reads as German
rather than as translated English. Never use a real company, product or person.

:file:`fixture.json` is different: it describes *the element itself*, because
that is what the showcase page is for.

The description in the matrix row is the other half of the same job, and the
audit has a shape for it: between 100 and 650 characters, containing a
`Use it for:` clause and a `Prefer …` clause naming at least one sibling the
element is confusable with. That text is what an editor reads in the picker
flyout and what the search ranks on.

..  _developer-authoring-elements-done:

Before you call it done
=======================

..  code-block:: bash
    :caption: The catalogue gates

    composer audit-elements
    composer test:unit
    php Build/Scripts/scaffold-content-elements.php --check

None of your ids may appear in the audit's findings, and the derived files must
be in step with the matrix.

A 200 from the showcase page proves nothing while that page has no records on
it. Prove the templates parse against real data instead:

..  code-block:: bash
    :caption: Rendering the catalogue in the lab

    ddev exec vendor/bin/typo3 cache:flush
    ddev exec vendor/bin/typo3 desiderio:library:seed --parent=1290 --hosts=astryx_typo3,core --no-warm
    ddev exec vendor/bin/typo3 desiderio:library:urls --site=astryx-typo3 --json

Then fetch a dozen of the URLs for your group and confirm that none contains
`Fluid parse error`, `Uncaught TYPO3 Exception` or `Allowed memory size`. The
same URL list is what the design review reads in its live mode
(:ref:`developer-design-review-live`).
