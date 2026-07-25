# Desiderio Grande

Meta's [Astryx](https://github.com/facebook/astryx) design system, server-rendered for TYPO3 14.

Astryx ships as React components styled with StyleX. This extension ships the
same design system as **Fluid templates and plain CSS**: no React, no StyleX, no
build step between an editor pressing save and a visitor seeing the page. What
comes from upstream is the part that matters — the token vocabulary and the
seven themes, taken from Astryx's own theme compiler rather than transcribed by
hand.

It is a sibling of [Desiderio](https://github.com/dirnbauer/desiderio), not a
fork: Desiderio remains the engine underneath (page rendering, the element
library, the seeding services), and a site chooses one theme or the other.

## What it gives you

- **250 content elements** in the ten wizard groups Desiderio already uses, so
  editors read the same shelf labels across both themes.
- **Seven themes** — neutral, butter, chocolate, matcha, stone, gothic, y2k —
  switchable per site and per page. Switching is a repaint: every value is a CSS
  custom property, nothing is rebuilt and no content changes.
- **Light and dark from one set of values.** Every colour token is a
  `light-dark()` pair resolved against `color-scheme`, so the scheme switch and
  the theme switch are genuinely independent.
- **A page shell** — header with brand and navigation, footer with legal and
  language rows, breadcrumb, error pages — driven by site settings.
- **Almost no JavaScript.** One small bundle handles the four behaviours the
  platform has no element for; everything else is `<details>`, `<dialog>`, the
  popover attribute and CSS scroll-snap.
- **Self-hosted fonts.** All ten families the themes ask for, latin subsets, no
  third-party request from a visitor's browser.

## Requirements

TYPO3 14.3+, PHP 8.3+, `friendsoftypo3/content-blocks` 2.2+, and
`webconsulting/desiderio` 3.2+ (the element library's host registration and
per-site host filtering arrived in 3.2).

## Setting up a site

Add the two sets to `config/sites/<site>/config.yaml`:

```yaml
dependencies:
  - webconsulting/desiderio-grande
  - webconsulting/desiderio-grande-content-elements
```

Then in `settings.yaml`:

```yaml
desiderioGrande.theme.default: neutral        # or butter, chocolate, matcha, stone, gothic, y2k
desiderioGrande.theme.colorScheme: system     # system | light | dark
desiderioGrande.brand.wordmark: 'Your name'
desiderioGrande.footer.legalPageIds: '12,13,14'

# Offer only this theme's elements in the picker. Without it, a site with both
# themes installed lists both catalogs in one wizard.
elementLibrary.hosts: 'desiderio_grande,core'
```

A page can override the theme for itself and everything below it through the
**Astryx theme** field in its page properties.

### The showcase site

```bash
ddev exec vendor/bin/typo3 desiderio-grande:site:seed --dry-run
ddev exec vendor/bin/typo3 desiderio-grande:site:seed
```

Creates the site root, a `/components` hub, one chapter page per group and the
legal and error pages, then prints the uids to put into the site YAML. It is
idempotent — run it again after adding elements.

### The element library

```bash
ddev exec vendor/bin/typo3 desiderio:library:seed --parent=<root uid> --hosts=desiderio_grande,core
ddev exec vendor/bin/typo3 desiderio:library:warm
```

Seeds one demo record per element into the site's library folder, so the
plus-button picker shows a live preview, keyword chips and the "when to use"
description for every element.

## Working on the catalog

Everything about an element starts as a row in `Build/Data/matrix/<group>.json`.
The row carries its title, its description in both languages, its keywords, the
fields it uses and the Astryx components it composes. Nothing is invented in the
generated files.

```bash
# create the file set for rows that have no directory yet
php Build/Scripts/scaffold-content-elements.php --scaffold --group=hero

# regenerate everything derived from the matrix (wizard allow-list, keyword and
# short-description catalogs, record types, the seeder's group manifest)
php Build/Scripts/scaffold-content-elements.php --derive

# fail if the derived files no longer match the matrix
php Build/Scripts/scaffold-content-elements.php --check
```

Two files per element are authored by hand and never overwritten:
`templates/frontend.html` and `assets/frontend.css`.

### Gates

```bash
php scripts/audit-content-elements.php     # per-element rules
vendor/bin/phpunit -c phpunit.xml.dist     # matrix invariants
```

The audit is what keeps 250 elements consistent: file completeness, explicit
`typeName`, description shape, collection flags, the icon contract, and the
stylesheet rules — no raw colour, no `light-dark()`, no `prefers-color-scheme`,
no literal font family, one shared set of breakpoints. An element's CSS may only
speak in tokens, which is precisely why a theme switch reaches all 250 of them.

### Assets

```bash
npm install
npm run build            # theme tokens, component CSS, chrome CSS, fonts
```

- `Build/Scripts/build-astryx-theme.mjs` — `Build/astryx/tokens.json` →
  `Resources/Public/Css/astryx-theme.css`. The payload is what Astryx's own
  `generateThemeRulesSplit()` produced for the seven shipped themes (upstream
  commit recorded in the file); the script re-scopes the rules to plain
  attribute selectors and orders the cascade with layers.
- `Build/Scripts/build-grande-css.mjs` — concatenates the manifest-ordered
  partials into `grande-components.css` and `grande.css`.
- `Build/Scripts/sync-fonts.mjs` — copies the woff2 subsets and writes the
  `@font-face` partial.

## Licence

GPL-2.0-or-later, like TYPO3.

Astryx is MIT, © Meta Platforms, Inc. and affiliates. This extension vendors its
design tokens (`Build/astryx/tokens.json`) and its component documentation
(`Build/astryx/components.json`); no upstream source code is redistributed. The
bundled fonts are licensed under the SIL Open Font License.
