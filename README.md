# Astryx for TYPO3

Meta's [Astryx](https://github.com/facebook/astryx) design system, server-rendered
for TYPO3 14.

Astryx ships as React components styled with StyleX. This extension ships the
same design system as **Fluid components and plain CSS**: no React, no StyleX,
no build step between an editor pressing save and a visitor seeing the page.
What comes from upstream is the part that matters — the token vocabulary and the
seven official themes, pinned to release `v0.6.2` rather than to a moving branch.

## What it is

- **250 content elements** in the ten wizard groups Desiderio already uses, so
  editors read the same shelf labels across both themes.
- **189 Fluid components** in four layers — Layout, Atom, Molecule, Organism —
  reached through one namespace: `<a:atom.button variant="primary">`. 159 of
  upstream's 164 components render through one; the five that do not are React
  context providers that render no DOM at all, and each is recorded with the
  reason. No template applies a CSS class.
- **Twenty-five themes**, switchable per site and per page. Switching is a
  repaint: every value is a custom property, nothing is rebuilt, no content
  changes. Every colour token is a `light-dark()` pair resolved against
  `color-scheme`, so light/dark and the theme are genuinely independent.
- **A page shell** — header, footer, breadcrumb, error pages — driven by site
  settings, with almost no JavaScript and self-hosted fonts.

[Desiderio](https://github.com/dirnbauer/desiderio) is the **rendering engine**
underneath, not the design system: page rendering, the element library and the
seeding services. This extension supplies all of its own design.

## Requirements

| | |
| --- | --- |
| TYPO3 | 14.3.7+ |
| PHP | 8.4+ |
| Content Blocks | `friendsoftypo3/content-blocks` 2.2+ |
| Vite assets | `praetorius/vite-asset-collector` 1.18+ |
| Engine | `webconsulting/desiderio` 4.1+ |

## Install

```bash
composer require webconsulting/astryx-typo3
ddev exec php packages/astryx_typo3/Build/Scripts/apply-schema.php --apply
```

The migrator is not optional: `extension:setup` reports success and applies
nothing once `tt_content` is large.

## Configure

Add the sets to `config/sites/<site>/config.yaml`:

```yaml
dependencies:
  - webconsulting/astryx-typo3
  - webconsulting/astryx-typo3-content-elements
```

Then in `settings.yaml`:

```yaml
astryx.theme.default: neutral
astryx.theme.colorScheme: system
astryx.brand.wordmark: 'Your name'
astryx.footer.legalPageIds: '12,13,14'
elementLibrary.hosts: 'astryx_typo3,core'
```

`elementLibrary.hosts` offers only this theme's elements in the picker; without
it a site with both themes installed lists both catalogs in one wizard. A page
overrides the theme for itself and everything below it through the **Astryx
theme** field in its page properties. Search is a third set,
`webconsulting/astryx-typo3-search`, kept separate because it needs Solr.

## Use

```bash
ddev exec vendor/bin/typo3 astryx-typo3:site:seed --content
ddev exec vendor/bin/typo3 desiderio:library:seed --parent=<root uid> --hosts=astryx_typo3,core
```

The first creates the site root, a `/components` hub, one chapter page per group
and the legal and error pages, then places every element on its chapter page
from its own fixture. The second seeds one demo record per element, so the
plus-button picker shows a live preview for all 250.

## Develop

Everything about an element starts as a row in `Build/Data/matrix/<group>.json`.
Two files per element are authored by hand and never overwritten:
`templates/frontend.html` and `assets/frontend.css`. The template composes
components and may not write an `astryx-*` class; the stylesheet may only speak
in tokens. Both rules are tests rather than review notes.

```bash
php Build/Scripts/scaffold-content-elements.php --scaffold --group=hero
php Build/Scripts/scaffold-content-elements.php --check
npm run build && git diff --exit-code
composer lint && composer cgl:check && composer phpstan && composer test
node Build/Scripts/design-review.mjs --harness
```

## Docs

[Documentation/Index.rst](Documentation/Index.rst) — installation, configuration,
usage, the component contract, the upstream sync, accessibility, the commands
and the design review.

## Licence

GPL-2.0-or-later, like TYPO3.

Astryx is MIT, © Meta Platforms, Inc. This extension vendors its design tokens
and component inventory from official release `v0.6.2`; no React or StyleX
runtime is redistributed. The notice is in
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). The bundled fonts are licensed
under the SIL Open Font License.
