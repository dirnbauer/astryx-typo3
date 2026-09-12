..  include:: /Includes.rst.txt

..  _developer-commands:

========
Commands
========

Every command a developer runs on this extension, what it does and when to
reach for it. The TYPO3 commands are run through the console binary of the
installation the extension is installed in; the examples below use DDEV because
that is where the catalogue is developed.

..  _developer-commands-assets:

Building the assets
===================

..  code-block:: bash
    :caption: The whole pipeline

    npm install
    npm run build

`npm run build` is a chain of six generators followed by the contrast gate, and
the order is the dependency order:

..  code-block:: json
    :caption: package.json

    "build": "npm run build:themes && npm run build:theme && npm run build:contrast && npm run build:css && npm run build:fonts && npm run build:overview && npm run audit:contrast",

`build:themes`
    :file:`Build/Scripts/build-astryx-themes.mjs`. Expands the eighteen theme
    seeds into Astryx-shaped payloads, verifies every theme's provenance claim
    against the vendored payload, and writes
    :file:`Build/Data/astryx-themes.generated.json` and
    :file:`Build/Data/theme-registry.json`.

`build:theme`
    :file:`Build/Scripts/build-astryx-theme.mjs`. Turns the vendored tokens plus
    the generated themes into
    :file:`Resources/Public/Css/astryx-theme.css`, re-scoping upstream's
    selectors to `[data-astryx-theme="…"]` and declaring the cascade layers.

`build:contrast`
    :file:`Build/Scripts/build-contrast-overrides.mjs`. Raises only the token
    values that miss WCAG 2.2 AA, by the smallest step that reaches the
    threshold on every surface the token is read on, into
    :file:`Resources/Private/Css/astryx/07-contrast-overrides.css`. It shares
    its colour parser with the audit, because the two disagreed once and the
    corrections that came out of that were nonsense.

`build:css`
    :file:`Build/Scripts/build-astryx-css.mjs`. Concatenates the
    manifest-ordered partials into :file:`astryx-components.css` and
    :file:`astryx.css`, minifies them conservatively and tree-shakes rules
    whose selectors name only `astryx-*` classes nothing renders. Pass
    `--no-tree-shake` to keep everything.

`build:fonts`
    :file:`Build/Scripts/sync-fonts.mjs`. Copies the woff2 subsets out of
    :file:`node_modules/` into :file:`Resources/Public/Css/files/` and writes
    the `@font-face` partial. Fonts are self-hosted, so no visitor's browser
    makes a third-party request.

`build:overview`
    :file:`Build/Scripts/build-theme-overview.mjs`. Regenerates the theme
    overview partial from the built stylesheet, so every fact on that page —
    accent colour, fonts, radius, base size — is read out of the themes rather
    than described by hand.

`audit:contrast`
    :file:`Build/Scripts/audit-contrast.mjs`. Measures 1,500 colour pairs and
    exits non-zero on any AA failure. `--all` also lists the passes.

The compiled CSS under :file:`Resources/Public/Css/` is generated output and is
never hand-edited. CI runs `npm run build` and then `git diff --exit-code`, so a
stale artefact fails the build.

..  note::
    :file:`Build/Scripts/generate-images.mjs` is not part of `npm run build` and
    is not run by CI. It generates the catalogue's demo imagery through paid
    image APIs, so without `--apply` it only prints what it would generate and
    what that would cost.

..  _developer-commands-upstream:

Refreshing the vendored Astryx data
===================================

..  code-block:: bash
    :caption: Harvest a release and report the difference

    node Build/Scripts/fetch-astryx-manifest.mjs --tag v0.6.0
    node Build/Scripts/diff-astryx-manifest.mjs --from HEAD~1 --to . \
         --out Build/Reports/astryx-0.3.0-to-0.6.0.md

Both need network access and npm; offline, the pinned files stay as they are.
`--keep-work` leaves the harvest workspace in :file:`var/astryx/` for
inspection. What the two scripts do, and what to do with the report, is
:ref:`developer-upstream-sync`.

..  _developer-commands-catalog:

Working on the catalogue
========================

..  code-block:: bash
    :caption: Build/Scripts/scaffold-content-elements.php

    php Build/Scripts/scaffold-content-elements.php --scaffold --group=hero
    php Build/Scripts/scaffold-content-elements.php --configs --only=hero-split-media
    php Build/Scripts/scaffold-content-elements.php --derive
    php Build/Scripts/scaffold-content-elements.php --check

`--scaffold`
    Creates the ten-file set for matrix rows that have no directory yet.
    Existing files are left alone, so re-running after adding rows is safe.
    `--group=<id>` and `--only=<element-id>` narrow it.

`--configs`
    Rewrites :file:`config.yaml` for every element from the matrix and the field
    library, leaving the authored template, stylesheet and demo content
    untouched. This is what to reach for after changing a field type — deleting
    the directories and re-scaffolding would take a day's writing with them.

`--derive`
    Regenerates everything that is a projection of the matrix: the wizard
    allow-list set, the keyword and short-description catalogs in both
    languages, the record types, and the group manifest the site seeder reads.
    Idempotent, and the only way those files should ever change.

`--check`
    Exits non-zero if `--derive` would change anything, and names the stale
    files. This is the CI job that catches a matrix edit which never made it
    into the generated files.

..  _developer-commands-schema:

Getting the columns into the database
=====================================

..  code-block:: bash
    :caption: Build/Scripts/apply-schema.php

    ddev exec php packages/astryx_typo3/Build/Scripts/apply-schema.php
    ddev exec php packages/astryx_typo3/Build/Scripts/apply-schema.php --apply

Without `--apply` it prints the pending statements by type and the first five
additive ones, and changes nothing.

This exists because `extension:setup` reports success and applies nothing once
`tt_content` is large. InnoDB checks at `ALTER` time whether a row could exceed
half a page — 8126 bytes — counting every variable-length column's worst case
towards the total. A `tt_content` carrying several hundred columns is past that
line, and MariaDB then refuses to add *any* column at all, including a two-byte
integer.

With `ROW_FORMAT=DYNAMIC`, which this table already uses, that check is
conservative: variable-length columns overflow to off-page storage at runtime,
so the real rows fit comfortably. The script runs TYPO3's own
:php:`SchemaMigrator` with `innodb_strict_mode` off for the duration of the
migration, which is MariaDB's documented behaviour for exactly this case, and
restores the previous value in a `finally` block. Only additive statements are
applied — `create_table` and `add`, never `change` — so nothing existing is
rewritten and nothing is dropped.

The trade-off is stated rather than hidden: a single record that filled several
hundred long text columns at once could still fail to write. A content element
uses the handful of fields its own type declares and leaves the rest empty, so
this does not happen in practice — which is why the setting is turned off for
the migration rather than permanently.

..  _developer-commands-seeding:

Seeding a site and the element library
======================================

..  code-block:: bash
    :caption: The showcase site

    ddev exec vendor/bin/typo3 astryx-typo3:site:seed --dry-run
    ddev exec vendor/bin/typo3 astryx-typo3:site:seed --content

:php:`SeedAstryxSiteCommand` creates or updates the page tree of the showcase
site: the site root, the components hub, one chapter page per matrix group, and
the legal and error pages. It is idempotent — pages are matched by parent plus
title or slug — so running it again after adding a chapter adds that chapter and
leaves everything else, including anything an editor changed in the backend,
where it is. The site root is deliberately matched by title alone, because every
site root in an installation has the slug `/` at page-tree level and matching by
slug would adopt, and then overwrite, whatever other site happens to sit beside
this one.

`--parent=<uid>`
    Page uid the site root is created below. `0`, the default, is the page-tree
    root.

`--content`
    Also fills each chapter page with its group's elements, from their own
    :file:`fixture.json`. It replaces the content this command seeded before;
    anything an editor added by hand stays.

`--include-video`
    Include video and demo elements in the seeded content. Off by default.

`--dry-run`
    Print what would be created or updated and change nothing.

`--allow-production`
    Run even when the application context is Production. The command refuses
    otherwise, and refuses outright to run inside a workspace, because seeding
    writes live records and a workspace context would silently produce rows
    nobody can publish.

..  code-block:: bash
    :caption: The element library, through Desiderio

    ddev exec vendor/bin/typo3 desiderio:library:seed --parent=<root uid> --hosts=astryx_typo3,core
    ddev exec vendor/bin/typo3 desiderio:library:warm
    ddev exec vendor/bin/typo3 desiderio:library:urls --site=<site identifier> --json

These three are Desiderio's, not this extension's. `desiderio:library:seed`
writes one demo record per element into the site's element-library folder, so
the plus-button picker can show a live preview, keyword chips and the "when to
use" description for all 250; `--hosts` scopes the folder to one theme's
elements, and records belonging to other hosts already in the folder are
removed. `--no-warm` skips the cache warming that otherwise follows.
`desiderio:library:warm` prerenders those records into the page cache.
`desiderio:library:urls` prints the isolated preview URL of every seeded record,
which is what feeds the design review's live mode.

..  _developer-commands-tests:

Tests and static analysis
=========================

..  code-block:: bash
    :caption: composer.json scripts

    composer lint          # php -l over Classes/ and Tests/
    composer cgl:check     # coding standards, reporting only
    composer cgl           # coding standards, applied
    composer phpstan       # level 8, no baseline
    composer test:unit
    composer test:functional
    composer test          # both suites
    composer audit-elements

`composer audit-elements` is the catalogue audit on its own — it runs the unit
suite filtered to :php:`ContentElementAuditTest`, which is where the rules that
used to live in `scripts/audit-content-elements.php` went in 2.0.0. The full
unit suite adds :php:`AtomicDesignConformanceTest`, :php:`ElementMatrixTest`,
:php:`ThemeSelectorConsistencyTest` and
:php:`EmbedUrlViewHelperTest`.

The functional suite has two tests. :php:`ComponentRenderingTest` renders every
component on disk with its required arguments filled and asserts it emits its
own root class; the component list is globbed at runtime rather than written
down, so a new component is covered the moment it lands.
:php:`RenderOneElementPerGroupTest` renders the first element of each of the ten
matrix groups against its own :file:`library.json` through a real `tt_content`
row and the Record API — two hundred and fifty elements is too many to render on
every push, and one element is too few to mean anything.

The suites are configured by :file:`Build/phpunit/UnitTests.xml` and
:file:`Build/phpunit/FunctionalTests.xml`; the functional one defaults to
`pdo_sqlite` and its environment entries carry no `force="true"`, so exporting
`typo3DatabaseDriver` and friends is all it takes to move it onto MariaDB —
which is what the CI job does.

..  _developer-commands-review:

Looking at the result
=====================

..  code-block:: bash
    :caption: Build/Scripts/design-review.mjs

    node Build/Scripts/design-review.mjs --harness
    node Build/Scripts/design-review.mjs --base-url=https://lab.ddev.site --urls=Build/Data/design-review-urls.json

See :ref:`developer-design-review` for the modes, the probes and the per-family
checklist a human follows afterwards.

..  _developer-commands-ci:

What CI runs
============

:file:`.github/workflows/ci.yml` runs seven jobs on every pull request and on
pushes to `main` and `v*` tags: `composer validate --strict` plus `composer
lint` and `composer audit`; `composer cgl:check`; `composer phpstan`; the unit
suite on PHP 8.4 and, as a non-blocking look-ahead leg, on 8.5; the functional
suite against MariaDB 10.11; `scaffold-content-elements.php --check`; and
`npm ci && npm run build && git diff --exit-code` on Node 24.
