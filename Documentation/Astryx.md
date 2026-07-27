# Astryx

This chapter answers three questions: what Astryx is, how you use it as an
editor or integrator, and — the part that is specific to this extension — how
we use it internally to produce 250 content elements that all look like one
design system.

Every number here was measured from the repository on the day it was written.
Where a figure can drift, the command that produces it is given, so you can
check rather than trust.

---

## 1. What Astryx is

Astryx is an open-source design system published by Meta at
[github.com/facebook/astryx](https://github.com/facebook/astryx) under the MIT
licence. It provides:

- **A token system.** Colour, spacing, radius, shadow and typography as named
  values — `--color-text-secondary`, `--spacing-4`, `--radius-element` — rather
  than as literals scattered through a stylesheet.
- **Seven themes.** `neutral`, `butter`, `chocolate`, `matcha`, `stone`,
  `gothic`, `y2k`. A theme is a complete set of token values, not a colour
  swap: type scale, radii and shadows change with it.
- **Around a hundred React components** built with StyleX, each documented with
  a category, keywords, usage guidance and the class names it exposes for
  theming.

### What it does *not* provide, and why that shapes everything here

**Astryx ships no distributable stylesheet.** StyleX compiles styles into atomic
classes at build time (`.x1a2b3c { color: red }`), and those class names are
generated per build. There is no `astryx.css` you can link, and there is no
stable atomic class you could target.

What upstream *does* publish is:

1. the token definitions, and a compiler that turns them into CSS custom
   properties per theme, and
2. a set of **stable, human-readable theming target class names** —
   `.astryx-button`, `.astryx-card`, `.astryx-heading` — that each theme's own
   override rules are written against.

That second point is the hinge. Those class names are a public contract
upstream maintains so that a theme can restyle a component. Markup that carries
them receives a theme's component overrides whether or not the markup came from
React. That is what makes a non-React port of Astryx honest rather than a
lookalike.

### Licensing

Astryx is MIT, © Meta Platforms, Inc. and affiliates. This extension is
GPL-2.0-or-later, like TYPO3. **No Astryx source code is redistributed here** —
only its design tokens (facts about colour and spacing) and its documented
class-name contract. There is no affiliation with or endorsement by Meta.

---

## 2. How to use it

Nothing in this section requires knowing anything about Astryx. If you are an
editor, this is the whole chapter.

### Choosing a theme

The theme is a setting, and changing it repaints the site. No rebuild, no
re-save of content — the tokens for all twenty-five themes are already on the page,
and the site just declares which one applies.

- **Per site:** `desiderioGrande.theme.default` in the site's `settings.yaml`,
  one of the twenty-five names below.
- **Per page and everything below it:** the *Astryx theme* field in the page
  properties (Appearance tab). It inherits down the page tree, so one setting on
  a campaign folder themes the whole campaign.

The showcase site uses this deliberately: each of the ten chapter pages under
*Components* wears a different theme, so the catalog demonstrates the theming
mechanism while it demonstrates the elements.

### Light and dark

Every colour token is a `light-dark()` pair, so the light and dark appearances
are one set of values resolved against the page's colour scheme. **The theme
switch and the scheme switch are independent**: choosing `matcha` does not
choose light or dark, and switching to dark does not change which theme you are
in.

The visitor's choice is remembered in `localStorage` and applied by a small
inline script before first paint, so someone who chose dark never sees a white
flash on load.

### Writing content

Use the element picker as you would with any Content Blocks extension. Each of
the 250 elements carries a description that says what it renders and when to
prefer a neighbour, keyword chips for searching, and a live preview.

The one rule worth internalising: **elements never carry colour.** There is no
"make this heading blue" field, by design. An element chooses a *tone* — page
background, raised surface, muted, accent — and the theme decides what those
mean. That is what allows one theme change to reach all 250 elements at once,
and it is enforced by an audit, not by discipline (see §3.4).

---

## 3. How we use it

This is the part that is specific to `desiderio_grande`.

The goal was a TYPO3 theme that is genuinely Astryx — not "inspired by" it —
while shipping no React, no StyleX and no build step on the rendered page. Four
decisions get us there.

### 3.1 The tokens are upstream's own output, not a transcription

We do not read Astryx's palettes and retype them. We run **Astryx's own theme
compiler** (`generateThemeRulesSplit()`) over the seven shipped themes and
vendor the result as `Build/astryx/tokens.json`, with the upstream commit
recorded beside it — currently `dd421ea4`.

`Build/Scripts/build-astryx-theme.mjs` turns that payload into
`Resources/Public/Css/astryx-theme.css`. A transcription would drift the first
time upstream nudged a value; generated output cannot, and regenerating is one
command.

Themes are scoped to `[data-astryx-theme="<name>"]` rather than to `<body>`.
That is why the *Themes* overview page can render all twenty-five side by side on one
page, each card genuinely wearing its own theme rather than showing a
screenshot.

### 3.1a Seven themes are Astryx's. Eighteen are ours.

Astryx ships **seven** themes and no more — `neutral`, `butter`, `chocolate`,
`matcha`, `stone`, `gothic`, `y2k` — which you can verify yourself:

```bash
gh api repos/facebook/astryx/contents/packages/themes --jq '.[].name'
```

This extension adds **eighteen**: `harbour`, `ember`, `linen`, `orchid`,
`cobalt`, `moss`, `clay`, `plum`, `sand`, `ink`, `lagoon`, `rose`, `graphite`,
`frost`, `latte`, `solar`, `retro`, `midnight`. The last five adapt palettes
published by other open-source projects under the MIT licence — Nord,
Catppuccin, Solarized, Gruvbox and Tokyo Night — and each says so on its card.
They are ours, not Meta's, and the theme overview badges every card so nobody
has to guess which is which.

They are not a second mechanism. Each is expanded by
`Build/Scripts/build-grande-themes.mjs` from a small seed in
`Build/Data/grande-themes.json` into exactly the structure Astryx's own
generator emits, with the same token names — no component can tell them apart.
A seed names only what is genuinely a brand decision:

```json
{
  "core": {"ink": "…", "mid": "…", "soft": "…", "paper": "…"},
  "dark": {"body": "…", "surface": "…", "card": "…"},
  "fonts": {"heading": "Figtree", "body": "DM Sans"},
  "radius": "0.5rem"
}
```

Everything else is **inherited from neutral**: the nine categorical hue ramps,
the status colours and the syntax palette are semantic categories rather than
brand decisions, and inheriting them means they keep the contrast behaviour the
audit already verified instead of giving us eighteen fresh chances to make a
badge unreadable.

Fonts must come from the ten families `sync-fonts.mjs` already self-hosts. A
theme wanting an eleventh would add a download to every page on the site.

`Build/Data/theme-registry.json` is generated from both sets and is the single
source of truth for which themes exist — the build scripts, the contrast audit,
the TCA field, the site settings, the overview partial and the seeder all read
it. That list used to be repeated in nine files.

### 3.2 The markup speaks Astryx's class-name contract

Our Fluid templates carry the same stable class names the upstream themes
target. Measured across all 250 elements:

| | uses | distinct |
|---|---:|---:|
| `.astryx-*` — upstream's contract | 2,390 | 90 |
| `g-*` — our own BEM layout classes | 6,153 | 2,413 |

```bash
grep -rho 'class="[^"]*"' ContentBlocks/*/*/templates/*.html
```

The ratio is the point, and it is worth reading correctly. The 90 distinct
`.astryx-*` names are the **shared vocabulary**: every button on the site is an
`.astryx-button`, every card an `.astryx-card`. The 2,413 `g-*` names are
almost all per-element BEM parts — `g-pricing-licence-tiers__amount` — that
exist once, in one element, to place things in a grid.

So: **appearance comes from Astryx, arrangement comes from us.** A theme's
component override rules land on our buttons and cards because they carry the
names those rules are written against. What a theme has no opinion about — that
this particular element puts its price at the end edge of the row — is ours.

`Build/astryx/components.json` holds the 94 upstream components we harvested,
with their categories, keywords, usage notes and theming targets. It is the
source for the class names above and for the element descriptions.

The vocabulary is not confined to content elements. The page shell and the
optional Solr search templates are written against the same contract — the
results list is an Astryx `Item`, the suggest dropdown an Astryx `Typeahead`,
the filters Astryx fields — which is why a search results page needs no styling
of its own to match the theme it lands in:

```bash
grep -rho 'astryx-[a-z0-9-]*' Resources/Private/Solr/ | wc -l   # 89
```

### 3.3 Elements speak only in tokens

An element's CSS may use `var(--color-*)` and never a literal colour. It may not
declare `light-dark()`, a `prefers-color-scheme` query, or a literal
`font-family`. All four rules are enforced mechanically:

```bash
php scripts/audit-content-elements.php     # 250 elements, 0 findings
```

This is what makes the theme switch total rather than approximate. An element
that hardcoded `#333` would survive every review and then quietly stay dark grey
in all twenty-five themes.

### 3.4 Accessibility is measured, and the build refuses to finish without it

`Build/Scripts/audit-contrast.mjs` measures **1,500 colour pairs** — twenty-five themes
× light and dark — against WCAG 2.2 AA: 4.5:1 for body text, 3:1 for
boundaries, focus rings and meaningful graphics.

Two things make the number trustworthy rather than decorative:

- **Every pair is one a stylesheet actually declares.** The audit checks
  `--color-text-yellow` on `--color-background-yellow` because that is the pair
  `04-badge-banner.css` writes. An audit that measures plausible-looking pairs
  reports failures nobody can see and misses the ones they can.
- **Translucent tints are composited onto the page canvas first.** Several hue
  backgrounds are 20% alpha (`#0171E333`). Measured raw they look like saturated
  blue and produce nonsense; measured as the reader receives them they are pale
  tints.

Astryx's own values fall short in a few places — secondary text in five themes,
badge hues in chocolate, matcha and gothic, and the error-toast label in all
seven.
`Build/Scripts/build-contrast-overrides.mjs` corrects them, and its restraint is
the design: it moves **only the failing foreground token**, and only by the
**smallest step toward black or white that reaches 4.5:1** on every surface that
token is read on. Matcha's red becomes `#b10000`, not black. Twenty-three token
declarations are corrected — twenty-eight values, counting a token's light and
dark sides separately; everything already passing is left exactly as Meta
shipped it.

Both scripts share one colour parser (`Build/Scripts/lib/theme-tokens.mjs`).
They did not always, and the two disagreed: one truncated `#0171E333` to an
opaque `#0171E3` and "found" badge failures at 1.3:1 that no reader had ever
seen. The corrections computed from that would have turned the pink badge label
black to fix a problem that did not exist. One parser, imported twice, is the
fix.

The corrections file is emitted into the `astryx-theme` cascade layer, which is
declared last and therefore wins. In any earlier layer the theme block it
corrects would simply override it — which it did, silently, until the layer was
made explicit in `build-grande-css.mjs`.

`npm run build` runs the generator and then the audit, and exits non-zero on any
failure. A palette change cannot quietly regress the site.

### 3.5 JavaScript only where the platform has no element

Disclosures are `<details>`, modals are `<dialog>`, menus and tooltips use the
popover attribute, carousels are CSS scroll-snap. **243 of the 250 elements
carry no JavaScript at all.** The seven that do are the tabs, carousels, the
dismissible offer, the video hero and the demo — behaviours HTML genuinely does
not have.

They are wired declaratively through `data-g-*` attributes, and the whole
runtime is one 24 kB vanilla file (`Resources/Public/Js/grande.js`) that
initialises idempotently, so the Visual Editor can swap content in without
double-binding.

The same file carries the few chrome behaviours: the colour-scheme toggle, the
mobile navigation, and — when the search set is installed — the header field's
collapse and its suggest dropdown. Each degrades to working markup without it:
the search field is simply visible rather than an icon that does nothing.

```bash
grep -rl 'data-g-' ContentBlocks/ContentElements/*/templates/frontend.html | wc -l
```

### 3.6 250 elements, 32 shared fields

Ten editor categories × 25 elements. They are generated from a matrix
(`Build/Data/matrix/<group>.json`) against a deliberately small field vocabulary
(`Build/Data/field-library.json`, 32 fields) plus 8 shared child record types.

The constraint is not tidiness. Content Blocks with `prefixFields: false` makes
every field identifier an installation-wide `tt_content` column, and `tt_content`
has a hard structural limit. Two hundred and fifty elements each inventing their
own fields would not fit in the table — we hit that wall and had to widen the
vocabulary instead of the schema.

See [AuthoringElements.md](AuthoringElements.md) for the per-element contract.

---

## 4. Commands

```bash
npm run build            # tokens -> corrections -> CSS -> fonts -> contrast audit
npm run audit:contrast   # 406 pairs, exits non-zero on any AA failure
composer audit-elements  # 250 elements against the structural and CSS rules

php Build/Scripts/scaffold-content-elements.php --check   # derived files vs the matrix
../../vendor/bin/phpunit -c phpunit.xml.dist              # matrix and structure tests
```

Regenerate after changing anything under `Build/`; the compiled CSS in
`Resources/Public/Css/` is generated output and should never be hand-edited.

---

## 5. When Astryx changes

The token payload is vendored with its upstream commit recorded, so nothing
follows upstream behind your back. To take a new version:

1. Re-run Astryx's theme compiler and replace `Build/astryx/tokens.json`,
   updating the recorded commit.
2. `npm run build`. The corrections regenerate from the *new* palette — the
   generator deliberately never reads its own previous output, so it is
   idempotent — and the audit then either passes or fails the build.
3. If the audit fails, a pair exists that no single foreground value can fix.
   That is a design conflict, not a token problem, and it needs a decision.

That last case is real. Field status messages originally sat on
`--color-*-muted` while badges sat on the hue tints. In butter's dark palette the
red tint is light and error-muted is dark; in stone's dark palette it is the
other way round. No single value of `--color-text-red` could stay legible on
both, so the fix was to change *our* CSS — field messages now use the same hue
tint their badge uses — rather than to bend a token until the arithmetic passed.
