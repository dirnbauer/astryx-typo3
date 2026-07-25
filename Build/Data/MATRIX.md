# The element matrix

`Build/Data/matrix/<group>.json` is the single source of truth for what this
extension ships. Every content element exists first as a row here; the
scaffolder turns rows into Content Blocks, labels, keywords, demo content and
the wizard allow-list. Nothing about an element is invented in the generated
files — if it is not in the matrix, it does not exist.

Ten groups, twenty-five elements each. The group ids are the wizard groups
Desiderio already uses, so an editor working across both themes reads the same
shelf labels: `hero`, `features`, `content`, `pricing`, `social-proof`, `team`,
`data`, `conversion`, `navigation`, `footer`.

## Row schema

```json
{
  "id": "hero-split-media",
  "title": "Split Media Hero",
  "titleDe": "Hero mit Medienspalte",
  "description": "Renders … Use it for: … Prefer 'X' for …",
  "descriptionDe": "…",
  "short": "A **two-column** opening band …",
  "shortDe": "…",
  "keywords": ["hero", "split", "…"],
  "synonyms": ["…"],
  "keywordsDe": ["…"],
  "synonymsDe": ["…"],
  "astryx": ["Button", "Badge", "Text"],
  "fields": ["header", "lead", "cta_label", "cta_link", "image", "tone"],
  "variants": ["media-right", "media-left"],
  "collection": {"recordType": "icon-lead", "identifier": "items", "labelEn": "Features", "labelDe": "Funktionen", "min": 1, "max": 8},
  "js": null,
  "notes": "Media keeps its aspect ratio; text column caps at 60ch."
}
```

| Key | Rule |
|---|---|
| `id` | kebab-case, unique across all ten groups. Becomes the directory name, and `desiderio_grande_<id without hyphens>` becomes the cType. Keep it short enough to stay readable there. |
| `title` / `titleDe` | Title case. What an editor scans in the wizard. Unique across the catalog. |
| `description` / `descriptionDe` | The "when to use" text, 100–650 characters, in three parts: one sentence on what it renders, then `Use it for:` with a comma list of real situations, then `Prefer 'Other Element' for …` naming at least one sibling it is confusable with. This is the single most valuable field in the row — it is what the editor reads in the picker flyout and what the search ranks on. |
| `short` / `shortDe` | One line for the picker card, under ~90 characters. `**bold**` and `*italic*` are rendered. |
| `keywords` / `keywordsDe` | Up to 10, ranked. Shown as chips. Do not repeat the title verbatim — it is filtered out. |
| `synonyms` / `synonymsDe` | 10–30 terms an editor might search for instead: other design systems' names for the same thing, plain-language descriptions, common misspellings of the domain word. Not shown, only searched. |
| `astryx` | Which upstream Astryx components this composes. Must exist in `Build/astryx/components.json`. |
| `fields` | Identifiers from `Build/Data/field-library.json` only. Adding a field to the library is a deliberate act, not a side effect of one element. |
| `variants` | Values for the element's own `variant` select, or `[]`. Include only when they change layout meaningfully — a variant that changes one colour is a theme's job, not a field's. |
| `collection` | One repeatable child list, using a record type from `Build/Data/record-types.json`, or `null`. |
| `js` | `null` for CSS-only (the target for at least nine in ten), else the `data-g-*` behaviour it needs: `carousel`, `tabs`, `dialog`, `dismiss`. |
| `notes` | One line to whoever implements the template. |

## What makes a good set of twenty-five

Twenty-five siblings must not be twenty-five settings of one element. Each row
earns its place by being the *right shape for a different job* — a different
information structure, not a different colour or column count. If two rows
differ only by a value an editor could set on either, delete one.

Equally, resist inventing exotica to reach the number. The catalog is a
vocabulary for real pages: if you cannot name the page that would use an
element, it does not belong.
