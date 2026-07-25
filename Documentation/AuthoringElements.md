# Authoring a content element

The matrix decides *what* an element is; this describes *how* it is built. Four
files per element are written by hand — everything else is generated from the
matrix and must not be edited:

```
templates/frontend.html      the markup
assets/frontend.css          layout, and nothing but layout
library.json + library.de.json   what an editor sees in the picker
fixture.json                 what the showcase page shows
```

`ContentBlocks/ContentElements/hero-split-media/` is the reference. Read it
before writing anything.

## Markup

- Keep the generated `<f:asset.css identifier="g-<id>" href="{cb:assetPath()}/frontend.css"/>` line.
- Delete the scaffolded `TODO(grande)` comment. A comment that stays must
  explain *why* something is done — never what the next line does.
- Text fields render through `{data -> f:render.text(field: 'x')}`, which is
  what makes them editable in place in the visual editor. Rich text goes
  through `<f:format.html>{data.bodytext}</f:format.html>`.
- The element's own heading is an `<h2>`: the page owns the single `<h1>`.
  Items inside a grid or list head at `<h3>`.
- Links are `<f:link.typolink parameter="{data.cta_link}" class="astryx-button primary">`.
- Images are `<f:for each="{data.image}" as="image"><f:image image="{image}" alt="{image.alternative}" …/></f:for>`.
- Collections are `<f:for each="{data.<identifier>}" as="item">`; take the
  identifier from the element's own `config.yaml`, never guess it.
- Every field the element declares must actually change the output. A `tone`,
  `width` or `reverse` that the template ignores is a field an editor will set
  and then wonder about.
- No `<script>`. No `<d:` — those are Desiderio's components, styled by a
  stylesheet this theme does not load. The only permitted inline `style` is a
  `--custom-property` hand-off, which is how an editor-typed number reaches CSS.

### Semantics are not decoration

A quotation is `<blockquote>` inside `<figure>` with its attribution in
`<figcaption>`. A definition list is `<dl>/<dt>/<dd>`. A table is a `<table>`
with a `<caption>` and `<th scope>`, wrapped in `.astryx-table-wrap` so it can
scroll in a narrow column. Footnotes and steps are ordered lists. Code is
`<pre><code>`.

Anything a sighted reader gets from position, colour or an icon has to reach a
screen-reader user as text: a star rating states its value, an included/excluded
row says which it is, a bar is `aria-hidden` decoration over a number that is
already written out.

## Stylesheet

Layout only. Every colour, size, radius, shadow and font comes from a token —
this is exactly why one theme switch reaches all 250 elements at once. Reuse the
`.astryx-*` classes in `Resources/Private/Css/components/`; if a component
already exists, style around it rather than restyling it.

The audit enforces: no raw colour, no `light-dark()`, no `prefers-color-scheme`,
no literal `font-family`, no `!important`, breakpoints only at 480, 640, 768 and
1024. Anything that moves stops under `prefers-reduced-motion`.

## Demo content

`library.json` is the strongest argument an element makes for itself: an editor
choosing between twenty-five siblings reads the copy as much as the layout.
Invent one plausible, ordinary business and keep it consistent across the whole
group — real sentences, real numbers, and German that reads as German rather
than as translated English. Never use a real company, product or person.

`fixture.json` is different: it describes *the element itself*, because that is
what the showcase page is for.

## Before you call it done

```bash
php scripts/audit-content-elements.php          # none of your ids may appear
```

A 200 from the showcase page proves nothing while that page has no records on
it. Prove the templates parse against real data instead:

```bash
ddev exec vendor/bin/typo3 cache:flush
ddev exec vendor/bin/typo3 desiderio:library:seed --parent=1290 --hosts=desiderio_grande,core --no-warm
ddev exec vendor/bin/typo3 desiderio:library:urls --site=desiderio-grande --json
```

Curl a dozen of the URLs for your group and confirm none contains
`Fluid parse error`, `Uncaught TYPO3 Exception` or `Allowed memory size`.

## Known gaps

The `icon` Select stores a semantic key resolved by Desiderio's icon registry.
The options are selectable, but this theme ships no renderer for them yet, so an
element that wants a glyph draws its own inline SVG or uses a tinted tile
carrying `data-icon="<key>"`. No template will need changing when a renderer
lands.

Prefixed Content Blocks columns are `desideriogrande_<elementwithoutdashes>_<field>`
in the database, while Fluid still addresses them by the bare identifier.
