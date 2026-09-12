# Changelog

All notable changes to `webconsulting/astryx-typo3` are documented here.

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

- **74 Fluid components** in four layers — 6 Layout, 20 Atom, 42 Molecule,
  6 Organism — each declaring every attribute it accepts with `<f:argument>`
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
  which had been forgotten.
- `astryx-icon-button` carries its own shape, hover, press and focus ring. It
  used to borrow them from `astryx-button` on the same element, which a
  one-root-class component cannot do.
- `astryx-components.css` is tree-shaken against what is actually rendered:
  115.6 kB to 79.6 kB, 255 selectors removed.

### Fixed

- `.astryx-link` and `.astryx-clickable-card` gained an `:active` state. On a
  touch screen the hover state never happens, so a card-sized tap target gave no
  feedback at all.
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
