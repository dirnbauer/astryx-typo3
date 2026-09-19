# Changelog

All notable changes to `webconsulting/astryx-typo3` are documented here.

## [2.1.1] - 2026-09-19

### Fixed

- **One `h1` per page again.** Thirty content elements rendered their headline
  as an `h1`, so a start page emitted two: its own screen-reader `h1` with the
  page title, and the hero's. Every element now heads its band with an `h2`, as
  Desiderio's do and as `PageHeader`, `PageTitle` and `ErrorMessage` have each
  claimed in their own comments since 2.0.
- **`Atom/Heading`'s `type` argument does something.** It has promised since
  2.0 that `level` decides the outline and `type` decides only the size, and
  the stylesheet never implemented the second half — which is why making a
  heading *look* like an `h1` meant making it one. `.astryx-heading[data-type]`
  now carries `heading-1`…`heading-6` and `display-1`…`display-3`, after the
  `data-level` rules and at equal specificity, so a size wins where one is
  chosen and the level stays in charge where none is. The thirty elements are
  `level="2" type="heading-1"`: same pixels, honest outline.
- The themes page emitted two `h1`s for the same reason — its own
  screen-reader one and the overview partial's.

### Added

- `npm run check:upstream` (`Build/Scripts/check-astryx-upstream.mjs`) compares
  the pin in `THIRD_PARTY_NOTICES.md` with the newest upstream release tag and
  with npm's `latest` dist-tag, and exits non-zero when either is ahead.
  Deliberately not a test: it reaches the network. The pin stays at **v0.6.2**,
  which both sources still report as the newest release.
- Two rules in `AtomicDesignConformanceTest`: a page template renders exactly
  one `h1`, counted through the partials, layouts and components it pulls in;
  and no content element renders one at all. `RenderOneElementPerGroupTest`
  asserts the same of the *rendered* markup, so a heading that reaches `h1`
  through a component's own switch is caught too.
- `Documentation/Developer/UpstreamSync.rst` records that the pin follows
  released tags only, and the three commands that tell a contract change on
  upstream `main` apart from React behaviour.

## [2.1.0] - 2026-09-19

A behaviour-preserving release: the component library now covers upstream
Astryx, the vendored data moves to v0.6.2, and three defects the 2.0 migration
left behind are fixed. Nothing an editor does changes.

### Changed

- **Astryx v0.6.2 tokens, themes and inventory.** The vendored payload moves
  from v0.6.0 (commit `1e63a51`) to v0.6.2 (commit `bc93547`). Upstream
  hyphenated three class names in that release and this extension follows:
  `.astryx-statusdot` is `.astryx-status-dot`, `.astryx-progressbar*` is
  `.astryx-progress-bar*`, and `.astryx-textarea*` is `.astryx-text-area*`. **A
  site stylesheet naming any of the old spellings must be updated.** One
  component was added upstream, `ScrollableArea`. The comparison is in
  [Build/Reports/astryx-0.6.0-to-0.6.2.md](Build/Reports/astryx-0.6.0-to-0.6.2.md).
- **189 Fluid components, up from 91.** 159 of upstream's 164 components render
  through one. The five that do not — `Theme`, `MediaTheme`, `LinkProvider`,
  `InternationalizationProvider` and `AppShell` — are React context providers
  that render no DOM, and each is recorded with the reason in
  `Build/Data/component-map.json`. A sixth appearing unrecorded fails the sync.
- **`Build/Data/component-contract.json` and `Build/Data/component-map.json` are
  generated** by `Build/Scripts/sync-component-contract.mjs`, the first step of
  `npm run build`. Editing either by hand now only produces a diff CI rejects.
- `phpunit/phpunit` moves to `^12.4 || ^13.0`.

### Added

- 98 components: the whole form and choice-control family, the table parts,
  `TopNav`, `SideNav`, `MobileNav` and their rows, the dropdown-menu family,
  `AlertDialog`, `Lightbox`, `Toast` and its viewport, `BottomSheet`,
  `HoverCard`, `Typeahead`, the command palette, the chat kit, `TreeListItem`,
  `ScrollableArea`, `GridSpan` and `Markdown`. Where a behaviour cannot be
  server-rendered, the component renders the correct static markup and says so
  in its comment rather than shipping a control that does nothing.
- `Documentation/` gains Installation, Configuration, Usage and Changelog
  chapters.

### Fixed

- **The typeahead had been rendering unstyled since 2.0.** `astryx.js` built
  each suggestion row as `astryx-item compact interactive` and toggled
  `is-active`; the stylesheet has read `data-density`, `data-interactive` and
  `data-state` since the 2.0 migration, which only touched CSS and Fluid.
- **The pagination element ignored its compact setting**, passing `sm`/`md` as a
  class rather than as Button's `size`. Its two arrows are IconButtons now,
  which already spell their direction the way the stylesheet reads it.
- **`conversion-offer-dismissible`'s corner card** keyed on `.surface`,
  `.muted` and `.accent`, which Section renders as `data-surface`.
- 150 selectors across the component stylesheets still used the 1.x bare
  modifier form, all of them in components that had CSS but no Fluid component,
  so nothing rendered them and nothing noticed.
- An attached `FieldStatus` pulled `--spacing-1-5` to close a seam the field
  opens with `--spacing-1`, leaving a 14px padding on no step of the scale.
- **Accessibility, from a design review of all 271 element previews** at 390,
  768 and 1440 in light and dark with axe on every page. It reported 426 axe
  nodes and 490 computed-style findings across this extension's elements; it
  now reports one of each, both explained below.
  - 83 orphaned `<dt>`/`<dd>` and 83 orphaned `<li>`: seven metric bands and
    thirteen card grids composed `Layout/Grid`, which renders a `<div>`, so the
    `<dl>` or `<ul>` around them had quietly disappeared. Grid gained an
    `as="dl"` case beside `as="ul"` and `as="ol"`, and every caller passes one.
  - Fifteen heading-order skips, a logo whose `alt` repeated the caption beside
    it, a struck-through time at 2.79:1, an empty `<th>`, and a row of 8px
    carousel dots under the 24px target size.
  - `Molecule/Table` and `Molecule/CodeBlock` wrapped their overflow in a
    `<div>` of their own. Both compose `Layout/ScrollableArea` now, which is a
    tab stop with a name — a scroll container a keyboard cannot reach hides the
    columns and the long lines it scrolls to. `.astryx-table-wrap` and
    `.astryx-codeblock-scroll` are removed; a site stylesheet naming either must
    select `.astryx-scrollable-area` instead.
- **Rich-text rhythm is the design system's decision now.** A paragraph an
  editor writes carried the browser's `margin: 1em`, which is 14px on a 14px
  body — a value on no step of the spacing scale, and 458 of the findings
  above. Paragraphs, lists, quotes and figures are `--spacing-3` in the reset
  layer; headings are `--spacing-8` above and `--spacing-3` below in the prose
  rules. A table pasted into rich text gets the theme's cell padding rather
  than the user agent's 1px.
- The element library's demo records are reachable again: the seeded
  collections were empty, so every element built from a collection rendered its
  heading and an empty band. Re-run
  `desiderio:library:seed --parent=<root uid> --hosts=astryx_typo3,core`.

### Removed

- `Build/Scripts/refactor-templates-to-components.php` and
  `Build/Scripts/migrate-component-css.mjs`, the two 2.0 migration codemods —
  921 lines with nothing left to rewrite. What they enforced is enforced
  directly: no stylesheet may contain `.astryx-x.foo`, and no template may write
  a class outside `astryx-` and `g-`. `npm run audit:css` went with them.
- Dead CSS for a syntax highlighter nobody ships, a drawn slider that needs a
  drag script, a character counter that is wrong the moment someone types, and
  pre-component spellings of rows `Molecule/Item` now renders. The build's
  tree-shake dropped 254 selectors on every run before this release and now
  drops none.

## [2.0.0] - 2026-09-13

A breaking release. Templates compose Fluid components instead of applying CSS
classes, the modifier vocabulary moves from bare classes to data attributes, the
vendored Astryx data is refreshed to upstream v0.6.0, and the rendering engine
requirement rises to Desiderio 4.1.

### Breaking

- **Templates compose components.** All 250 element templates, the 7 page
  templates, the 6 partials and the 16 Solr templates now render
  `<a:layout.section>`, `<a:atom.button>` and so on, through the component
  collection registered under the `a` namespace. A template that applied an
  `astryx-*` class directly no longer does, and the test suite fails if one
  starts again. Anything overriding a template from a site package has to be
  rewritten against the components.
- **Bare modifier classes are gone.** `class="astryx-button primary lg"` is
  `<a:atom.button variant="primary" size="lg">`, which renders
  `data-variant="primary" data-size="lg"`. Every component stylesheet, page
  stylesheet and element stylesheet was migrated with it — 363 selectors in 123
  files. This follows Astryx itself, which removed bare prop and state classes
  at v0.6.0. A site stylesheet selecting `.astryx-card.flat` must now select
  `.astryx-card[data-variant="flat"]`.
- **Astryx v0.6.0 tokens and themes.** The vendored payload moves from v0.3.0
  (commit `82d4dab`) to v0.6.0 (commit `1e63a51`). `--transition-fast` and
  `--transition-normal` are removed — they were never upstream — and
  `--border-width` and the four `--focus-outline-*` tokens are added.
  `--color-syntax-punctuation` now resolves to `--color-text-secondary`. The
  full comparison is in
  [Build/Reports/astryx-0.3.0-to-0.6.0.md](Build/Reports/astryx-0.3.0-to-0.6.0.md).
- **Requires `webconsulting/desiderio` ^4.1** and `typo3/cms-*` ^14.3.7.
- `scripts/audit-content-elements.php` is gone. Its rules are
  `Tests/Unit/ContentElementAuditTest.php`, and CI runs them.
- The root `phpunit.xml.dist` is replaced by `Build/phpunit/UnitTests.xml` and
  `Build/phpunit/FunctionalTests.xml`.

### Added

- **89 Fluid components** in four layers — 6 Layout, 22 Atom, 53 Molecule,
  8 Organism — each declaring every attribute it accepts with `<f:argument>`
  and rendering exactly one root class.
- `Build/Data/component-contract.json`, the modifier vocabulary, read by the
  codemod, the CSS migration, the tree-shake and the conformance test, so the
  mapping is stated once. `Build/Data/component-map.json` maps all 50 Astryx
  components the element matrix names onto the Fluid components.
- `Build/Scripts/fetch-astryx-manifest.mjs --tag vX.Y.Z` and
  `diff-astryx-manifest.mjs`, which harvest the upstream manifest from a release
  and report what changed between two of them.
- `Build/Scripts/refactor-templates-to-components.php`, the tag-aware codemod
  that performed the template migration, with `--dry-run`, `--only`, `--group`
  and per-pass control.
- `Build/Scripts/design-review.mjs`, a Playwright review over 390/768/1440 in
  light and dark: screenshots, axe, and computed-style probes for the type
  scale, spacing, radii, focus, hover and active affordances, reduced motion and
  RTL. Runs against a live site with `--base-url`, or against a static component
  harness with `--harness`.
- `Build/Scripts/migrate-component-css.mjs`, which performed the class-to-
  attribute CSS migration and gates against a regression with `--check`.
- `Tests/Unit/AtomicDesignConformanceTest.php` and the functional tests
  `ComponentRenderingTest` and `RenderOneElementPerGroupTest`.
- PHPStan at level 8 with no baseline, php-cs-fixer, and a CI workflow running
  lint, cgl, phpstan, unit (8.4 and 8.5), functional (MariaDB 10.11), the
  frontend build and the catalog check.
- An RST documentation set under `Documentation/`, replacing the two Markdown
  files.

### Changed

- Theme provenance is recorded and verified. All seven official themes still
  ship upstream at v0.6.0 and are stamped `upstream@v0.6.0` with their package
  name; the eighteen seeds are stamped `webconsulting`. The theme builder fails
  if a theme claims upstream provenance the vendored payload does not support.
- One global answer to `prefers-reduced-motion`, emitted outside every cascade
  layer so it needs no `!important`. It replaces nine per-partial guards, one of
  which had been forgotten — 704 elements kept their transition.
- The contrast audit gained the link-on-card and link-on-muted pairs: 1,600
  pairs across 25 themes and both colour schemes, with no failure.
- `astryx-icon-button` carries its own shape, hover, press and focus ring. It
  used to borrow them from `astryx-button` on the same element, which a
  one-root-class component cannot do.
- `astryx-components.css` is tree-shaken against what is actually rendered:
  115.6 kB to 79.6 kB, 255 selectors removed.

### Fixed

- `.astryx-link` and `.astryx-clickable-card` gained an `:active` state. On a
  touch screen the hover state never happens, so a card-sized tap target gave no
  feedback at all.
- A link inside any of the fifteen tinted card variants, inside a banner, or
  inside a tooltip kept `--color-text-accent` — blue type on a yellow callout,
  and near-black on black in a tooltip, at 1.21:1. Those surfaces decide the
  colour now.
- Both arrow carousels rendered empty buttons with no
  `data-g-carousel-prev`/`-next`, so the arrows did nothing. Three accordions
  rendered an empty `<summary>`. `MetadataListItem` nested a `<dt>`/`<dd>` pair
  inside another on all ten call sites. `Dialog` and `Item` each wrote one ARIA
  attribute twice, so a titled dialog had no accessible name.
- 866 selectors across 223 element stylesheets stopped matching when the section
  stopped wearing the editor's tone as a class. They select `[data-surface="…"]`
  now.
- `.astryx-link`'s colour transition survived `prefers-reduced-motion: reduce`,
  along with 703 other elements.
- README comment lines inside fenced code blocks no longer read as headings in
  renderers that do not track fences.

## [1.0.2] - 2026-08-28

### Fixed

- Keep structural page fields, including `is_siteroot`, synchronized on German
  page overlays so EXT:solr can resolve multilingual rootlines.
- Make translation seeding idempotent after concurrent runs by retaining the
  oldest managed overlay and soft-deleting duplicate overlay records.

## [1.0.1] - 2026-08-06

### Fixed

- Added the official `Configuration/ViteEntrypoints.json` declaration required
  by `vite-plugin-typo3`, so project builds discover both Astryx entries.

## [1.0.0] - 2026-08-06

### Added

- Initial independent Astryx for TYPO3 release with 250 server-rendered
  Content Blocks, 25 themes and the pinned upstream Astryx v0.3.0 data.
