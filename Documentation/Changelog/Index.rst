..  include:: /Includes.rst.txt

..  _changelog:

=========
Changelog
=========

The full, per-release list is :file:`CHANGELOG.md` in the repository root, in
Keep a Changelog form. This page carries what an integrator has to act on.

..  _changelog-2-1-1:

2.1.1
=====

*   **One `h1` per page again.** Thirty content elements rendered their
    headline as an `h1`, so a start page emitted two: its own screen-reader
    `h1` with the page title, and the hero's. Every element now heads its band
    with an `h2`.
*   **`Atom/Heading`'s `type` argument does something.** `level` decides the
    outline, `type` decides only the size —
    `.astryx-heading[data-type="heading-1"]` and the eight others exist now, so
    a heading can look like an `h1` without claiming to be the page's subject.
    A site stylesheet that made an element's heading bigger by selecting
    `[data-level="1"]` should select `[data-type="heading-1"]`.
*   `npm run check:upstream` says whether the vendored Astryx pin is still the
    newest upstream release. It stays at v0.6.2.

..  _changelog-2-1-0:

2.1.0
=====

A behaviour-preserving release. Nothing an editor does changes; a site package
that reaches past the components into the CSS may have to.

*   **The vendored Astryx data moved to upstream v0.6.2.** Upstream hyphenated
    three class names in that release, and this extension follows:
    `.astryx-statusdot` is `.astryx-status-dot`, `.astryx-progressbar*` is
    `.astryx-progress-bar*`, and `.astryx-textarea*` is `.astryx-text-area*`. A
    site stylesheet naming any of the old spellings must be updated. The
    comparison is generated in
    :file:`Build/Reports/astryx-0.6.0-to-0.6.2.md`.
*   **The component library covers upstream.** Every Astryx component that
    renders DOM now has an `a:` Fluid component; the five that are React context
    providers and render nothing are recorded, with the reason, in
    :file:`Build/Data/component-map.json`, and the sync refuses to run if a
    sixth appears unrecorded.
*   **`Build/Data/component-contract.json` and
    :file:`Build/Data/component-map.json` are generated.** Run
    `npm run build:contract`, or `npm run build`, which starts with it. Editing
    either by hand no longer does anything but create a diff CI will reject.
*   **`.astryx-table-wrap` and `.astryx-codeblock-scroll` are gone.**
    `Molecule/Table` and `Molecule/CodeBlock` compose
    `Layout/ScrollableArea`, which is a tab stop with a name: a scroll
    container a keyboard cannot reach hides what it scrolls to. A site
    stylesheet naming either class must select `.astryx-scrollable-area`.
*   **Rich text has a rhythm the design system decided.** An editor's
    paragraph carried the browser's `margin: 1em` — 14px on a 14px body, and a
    value on no step of the spacing scale. Paragraphs, lists, quotes and
    figures are now `--spacing-3` in the reset layer; headings are
    `--spacing-8` above and `--spacing-3` below in the prose rules; a table
    pasted into rich text gets the theme's cell padding. Pages that carry a lot
    of editor prose move by a pixel or two.
*   **The two migration codemods are gone** —
    :file:`Build/Scripts/refactor-templates-to-components.php` and
    :file:`Build/Scripts/migrate-component-css.mjs`. The migration they existed
    for is complete, and the rules they enforced are enforced directly by
    :php:`Tests\Unit\AtomicDesignConformanceTest`. `npm run audit:css` went with
    them; the same check is a test.

..  _changelog-2-0-0:

2.0.0
=====

A breaking release, and all of it about how markup is written rather than about
what a visitor sees.

*   **Templates compose components.** All 250 element templates, the page
    templates, the partials and the Solr templates render `<a:layout.section>`,
    `<a:atom.button>` and the rest through the component collection registered
    under the `a` namespace. A site package overriding a template has to be
    rewritten against the components.
*   **Bare modifier classes are gone.** `class="astryx-button primary lg"` is
    `<a:atom.button variant="primary" size="lg">`, which renders
    `data-variant="primary" data-size="lg"`. A site stylesheet selecting
    `.astryx-card.flat` must now select `.astryx-card[data-variant="flat"]`.
    This follows upstream, which removed bare prop and state classes at v0.6.0.
*   **The vendored Astryx data moved to v0.6.0** from v0.3.0. See
    :ref:`developer-upstream-sync`.
*   **The rules became tests.** `scripts/audit-content-elements.php` is gone;
    its rules are :php:`Tests\Unit\ContentElementAuditTest`, and CI runs them.
*   **The engine requirement rose** to `webconsulting/desiderio` `^4.1` and
    `typo3/cms-*` `^14.3.7`.
