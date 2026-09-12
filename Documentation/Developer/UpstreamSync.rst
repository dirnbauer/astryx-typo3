..  include:: /Includes.rst.txt

..  _developer-upstream-sync:

=======================
Upstream Astryx, synced
=======================

Two files in this repository come from upstream Astryx:
:file:`Build/astryx/components.json`, the component inventory, and
:file:`Build/astryx/tokens.json`, the base token defaults together with each
official theme's compiled rule set. Everything else that looks like Astryx —
the themes on disk, the component CSS, the class names in the markup — is
derived from those two files or written against the contract they describe.

Neither file is edited by hand, and neither follows a branch. They are harvested
from a named release tag, they record the commit that tag points at, and the
next harvest is a deliberate act with a report attached.

..  _developer-upstream-sync-harvest:

Harvesting a release
====================

..  code-block:: bash
    :caption: Refresh the vendored manifest from an upstream release

    node Build/Scripts/fetch-astryx-manifest.mjs --tag v0.6.0

The tag is validated against `/^v\d+\.\d+\.\d+$/` before anything else happens,
so a branch name or a moving reference cannot be harvested by accident. The
script then does four things.

It **resolves the tag to a commit** through the GitHub API, at
`/repos/facebook/astryx/git/ref/tags/<tag>`, dereferencing an annotated tag
object where necessary so that what is recorded is the commit the tag points
at rather than a branch head. `GITHUB_TOKEN` is used when it is set, which only
affects rate limiting.

It **downloads the tag's source tarball** into :file:`var/astryx/` — git-ignored
— so the tree of that exact tag can be inspected and so the fallback described
below has something to read.

It **installs the release's own packages** into a throwaway workspace under
:file:`var/astryx/harvest-<version>/`: `@astryxdesign/core`,
`@astryxdesign/cli` and the seven theme packages, all pinned to the same
version. The published packages are used rather than the source tree on purpose:
the token defaults and the theme rule compiler are built artefacts, and reading
the TypeScript would mean re-implementing upstream's build — which is precisely
what a vendored manifest exists to avoid.

Finally it **writes the two payloads**, each carrying `release`, `commit` and
`harvestedBy` so a reader can tell exactly which upstream artefact produced it.

..  _developer-upstream-sync-inventory:

The component inventory, and its fallback
-----------------------------------------

The inventory is upstream's own, taken from the CLI:

..  code-block:: bash
    :caption: What harvestComponents() runs, in the throwaway workspace

    astryx component --list --json

The CLI groups components by category, and the category becomes the row's
`group`. This is an inventory upstream publishes about itself, not a reading of
its source tree.

If the CLI is unavailable — it is absent, or it errors, or it returns a payload
without `data.components` — the script falls back to parsing the
`export * from './X'` lines of :file:`packages/core/src/index.ts` in the
downloaded tarball. That fallback is deterministic but coarser: a directory that
exports several components yields one row, because `Avatar` exports
`AvatarGroup` and `AvatarStatusDot` from the same file. The difference is never
silent — the payload records `harvestedBy: "source-exports"` instead of the CLI
invocation, and the diff report prints that field for both sides, so a harvest
that quietly degraded shows up the next time anyone compares two releases.

..  _developer-upstream-sync-tokens:

The tokens, and where a theme comes from
----------------------------------------

The base block is the `*Defaults` maps exported by `@astryxdesign/core/theme`,
merged into thirteen named groups — `color`, `spacing`, `size`, `radius`,
`shadow`, `border`, `focus`, `duration`, `ease`, `typography`, `textSize`,
`fontWeight` and `typeScale`. The `color` group deliberately merges
`colorDefaults` with `domainTokenDefaults`, because the data-visualisation ramps
and the syntax palette are `--color-*` like everything else and a consumer has
no reason to see them as a second namespace. If upstream stops exporting one of
those maps the harvest throws rather than writing a short payload.

Each official theme is compiled by upstream's own function:

..  code-block:: javascript
    :caption: Build/Scripts/fetch-astryx-manifest.mjs — inside the harvest script

    const {component, prose} = theme.generateThemeRulesSplit(pkg[key]);
    themes[slug] = {component, prose};

That is exactly what Astryx emits. The only thing this extension does to the
result afterwards is re-scope the selectors, in
:file:`Build/Scripts/build-astryx-theme.mjs`: upstream's `:scope` and bare
component selectors become explicit `[data-astryx-theme="<name>"]` prefixes
rather than an `@scope` wrapper, because a TYPO3 page has no nested theme
regions and plain attribute selectors work everywhere. The cascade is then
ordered with layers — `astryx-reset`, `astryx-base`, `astryx-components`,
`astryx-chrome`, `astryx-theme` — so a theme override beats a component base
style regardless of how the selectors compare.

The seven theme slugs are a literal in the harvest script rather than a glob,
and the comment beside them says why: the list is a claim about upstream that
must fail loudly when it stops being true, instead of quietly harvesting a
shorter set.

..  _developer-upstream-sync-diff:

Reporting what changed
======================

..  code-block:: bash
    :caption: Compare two vendored manifests

    node Build/Scripts/diff-astryx-manifest.mjs --from HEAD~1 --to .
    node Build/Scripts/diff-astryx-manifest.mjs --from v1.0.2 --to . \
         --out Build/Reports/astryx-0.3.0-to-0.6.0.md

`--from` and `--to` each name either a git ref, in which case the manifests are
read out of that commit with `git show`, or a directory containing
:file:`Build/astryx/`. A dot means the working tree. Without `--out` the report
goes to standard output.

The report answers the three questions an upgrade actually raises. Which
components disappeared, because every matrix row naming one has to be remapped
before the catalogue regenerates. Which arrived, and which of those look like
renames — a rename is only ever guessed when exactly one component left and
exactly one arrived within the same group, and it is offered as a candidate,
never applied. And which tokens changed name, category or value, where a token
that moved category is not a break but a token that vanished is.

It also counts selector shapes straight out of the compiled theme rules, which
is how the v0.6.0 change to data attributes was measured rather than assumed.

..  _developer-upstream-sync-provenance:

Theme provenance
================

Twenty-five themes ship. Seven are Astryx's own and eighteen are this
extension's, and the difference is recorded rather than remembered.

:file:`Build/Data/upstream-theme-meta.json` carries one entry per official
theme with its package name, upstream's own description and a `provenance`
string. :file:`Build/Data/astryx-themes.json` carries the eighteen seeds, each
of which must declare `"provenance": "webconsulting"`.
:file:`Build/Scripts/build-astryx-themes.mjs` expands the seeds, then writes
both sets into :file:`Build/Data/theme-registry.json` — the single source of
truth for which themes exist, read by the build scripts, the contrast audit, the
TCA field, the site settings, the overview partial and the seeder. Before that
registry existed the list was repeated in nine files.

Provenance is a claim, and the builder checks it:

..  code-block:: javascript
    :caption: Build/Scripts/build-astryx-themes.mjs

    const UPSTREAM_PROVENANCE = /^upstream@v\d+\.\d+\.\d+( \(retired upstream, kept\))?$/;

A theme claiming `upstream@<tag>` must be present in the vendored payload, and
its tag must equal the payload's own release, or the build exits non-zero. The
consequence is the point: if upstream ever drops a theme, the next harvest
removes it from :file:`tokens.json` and this check fails, forcing a decision
between re-harvesting and marking the theme `(retired upstream, kept)` so a live
site does not lose its paint.

The eighteen own themes are not a second mechanism. Each is expanded from a
small seed — four core colours, three dark surfaces, two font families and a
radius — into exactly the structure `generateThemeRulesSplit()` emits, using the
neutral theme's rule set as the structural template and inheriting everything
that is a semantic category rather than a brand decision: the nine categorical
hue ramps, the status colours and the syntax palette. Inheriting them keeps the
contrast behaviour the audit has already verified instead of offering eighteen
fresh chances to make a badge unreadable.

..  _developer-upstream-sync-0-6-0:

What the v0.3.0 to v0.6.0 refresh changed
=========================================

The report in :file:`Build/Reports/astryx-0.3.0-to-0.6.0.md` is generated, and
these are its findings.

The pin moved from commit `82d4dab3d05b9314a76ab0bda296491a65f69c88` to
`1e63a5144bdf4777081edee472089277aceb5d75`. The inventory grew from 155
components to 163, the base token set from 255 to 258, and the number of
official themes stayed at seven.

**No component was removed**, so no matrix row needed remapping. Eight arrived:
`BottomSheet` and `BottomSheetSwitcher`, `DropdownMenuDivider`, `Step` and
`Stepper`, and `TableBody`, `TableFooter` and `TableHeader`. Nothing was
regrouped and no rename was proposed.

Two tokens were removed, `--transition-fast` and `--transition-normal`. Five
were added, and four of them describe the focus ring —
`--focus-outline-color`, `--focus-outline-offset`, `--focus-outline-style` and
`--focus-outline-width` — alongside `--border-width`. One value changed:
`--color-syntax-punctuation` now resolves to `--color-text-secondary` rather
than to `--color-text-disabled`.

Every theme gained component rules, between four and nineteen of them each.

The change that mattered most to this extension is the last section of the
report, which is why the report counts it:

..  code-block:: text
    :caption: Build/Reports/astryx-0.3.0-to-0.6.0.md — selector shape

    | selector shape        | v0.3.0 | v0.6.0 |
    | --------------------- | ------ | ------ |
    | `.astryx-x.modifier`  |    423 |      0 |
    | `.astryx-x[data-…]`   |      0 |    482 |

Upstream replaced every bare prop and state class with a data attribute. Since
the whole point of carrying upstream's class names is that a theme's override
rules land on this extension's markup, the markup had to move with it — which is
what :ref:`developer-component-contract` describes, and why 2.0.0 is a breaking
release.
