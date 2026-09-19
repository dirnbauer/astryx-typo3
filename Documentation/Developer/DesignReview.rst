..  include:: /Includes.rst.txt

..  _developer-design-review:

=================
The design review
=================

:file:`Build/Scripts/design-review.mjs` looks at every component the way a
reviewer would and writes down what is wrong. It drives Chromium through
Playwright at three viewports in light and dark, runs axe, measures computed
styles against the active theme's own tokens, and saves a screenshot of every
combination.

It is not a substitute for looking. The second half of this page is the
checklist a human works through afterwards, because most of what makes a design
system feel like one is not measurable.

..  _developer-design-review-modes:

Two modes, because they answer different questions
==================================================

..  code-block:: bash
    :caption: Build/Scripts/design-review.mjs

    node Build/Scripts/design-review.mjs --harness
    node Build/Scripts/design-review.mjs --base-url=https://lab.ddev.site --urls=Build/Data/design-review-urls.json
    node Build/Scripts/design-review.mjs --base-url=… --only=hero-split-media --viewports=390,1440

The script refuses to start without one of `--harness` or `--base-url`. The
probes are identical in both modes, so a finding means the same thing either
way.

..  _developer-design-review-harness:

Harness mode
------------

`--harness` builds a static page from
:file:`Build/Data/component-contract.json` and the built CSS: every component,
in every state the stylesheet defines for it, each one wrapped around the same
sample paragraph, inline link and button. It serves that page and the three
built stylesheets from an ephemeral local HTTP server, so the browser sees real
`@font-face` rules and real cascade layers.

The cases come from the CSS, by scanning it for `.astryx-x[data-y="z"]`. The
stylesheet is the authority: a case it does not define paints nothing, and a
case it defines that nobody wrote down is exactly the one that would go
unreviewed. It also means a component added tomorrow is probed in all of its
states without a second list of them to maintain.

The markup is generated rather than rendered through Fluid on purpose. This page
exists to measure what the *stylesheet* does with a root class and a data
attribute, so a probe failure points at the CSS rather than at a disagreement
between two generators.

It needs no database and no site, which is what makes it runnable in CI — and it
is also how the icon button was caught rendering with no shape, no hover, no
press and no focus ring once it stopped borrowing them from `.astryx-button`.

The harness is loaded twice: once as it is, and once with `dir="rtl"` and
`lang="ar"` set on the document element.

..  _developer-design-review-live:

Live mode
---------

`--base-url` with `--urls` reviews real pages: element-library preview URLs
from a running TYPO3, which means real content in real markup, so axe sees the
actual heading order, the actual alt text and the actual labels.

`--urls` names a JSON file of entries carrying an `id` and either a `url` or a
`path` resolved against the base URL. The preview URLs themselves come from
Desiderio:

..  code-block:: bash
    :caption: Producing the URL list

    ddev exec vendor/bin/typo3 desiderio:library:urls --site=<site identifier> --json

..  note::
    That command prints one object per seeded record with `cType`, `uid`,
    `group`, `site`, `storagePid` and `url` — it has no `id` key, and its
    options are `--folder`, `--site` and `--json`. The list therefore has to be
    mapped before the review reads it: `cType` becomes `id`, `url` stays as it
    is. The usage message inside `design-review.mjs` suggests a `--host` option
    that the command does not have.

`--only=<id,…>` filters that list, `--limit=<n>` truncates it, and both are
worth using: 250 elements times three viewports times two schemes is a long run.

..  _developer-design-review-probes:

What it measures
================

Three sets of findings end up in the report.

..  _developer-design-review-probes-computed:

Computed-style probes
---------------------

These run inside the page, as one function, so the document is walked once per
viewport and scheme. They read their allowed values **out of the page** rather
than out of :file:`tokens.json`, by setting each token as a width on a hidden
element and measuring it — a theme overrides `--radius-element` and
`--font-size-base`, so measuring against the base defaults would call every
themed value a violation. The first version of the probe did exactly that and
produced 6,424 radius findings for a radius that was correct.

`type-scale`
    An element that decides its own font size — one that has its own text, and
    whose font size or line height differs from its parent's — carries a size
    that is not in the theme's scale. Only elements that decide are measured: a
    `<span>` inside a display heading inherits its size and is not making a
    decision.

`line-height`
    A line height under 1.1 times the font size.

`spacing`
    A padding or margin under 40px that is neither a spacing token nor a
    multiple of four. Above 40px the value is almost always a fluid `clamp()`
    that is meant to land on a fraction, and `auto` margins are a centring
    instruction rather than a measurement, so both are skipped.

`radius`
    A corner radius that is not a `--radius-*` token. Percentages and values
    over 999px are pills and are skipped.

`focus-visible`
    Focusing the element changes neither its outline nor its box-shadow. Run
    over the first sixty focusable elements on the page.

`reduced-motion`
    More than 0.05s of animation or transition survives
    `prefers-reduced-motion: reduce`. This one runs only in the reduced-motion
    pass, which is why that pass exists.

`rtl-overflow`
    In RTL the page scrolls sideways at all.

`rtl-direction`
    One of the five components built on a horizontal axis — `carousel`,
    `breadcrumbs`, `stepper`, `split`, `toolbar` — still has its first child on
    the left. The match is on the exact root class, because
    `astryx-carousel-track` is a different component with a different layout and
    a loose test caught it too. Note what this probe deliberately does *not*
    ask: in RTL the document's own origin moves, so absolute positions are
    negative for perfectly correct layouts, and the first version of the probe
    reported 694 of them.

..  _developer-design-review-probes-affordance:

Affordance probes
-----------------

`hover-state` and `active-state` ask whether an affordance is *declared* for
every button, link, icon button, clickable card and interactive item on the
page. They do it by walking every same-origin stylesheet for selectors carrying
`:hover` or `:active` and matching elements against them, rather than by moving
a real mouse: hovering is asynchronous, a headless page can report the move
before the style recalculates, and the sample would have to be capped or the run
takes an hour — all of which made the first version report "no hover state" for
links that plainly had one.

..  _developer-design-review-probes-axe:

Accessibility
-------------

axe-core runs on every page that is not a reduced-motion pass, with the tags
`wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa` and `best-practice`. If
`@axe-core/playwright` is not installed the script says so and carries on
without it.

..  _developer-design-review-output:

Output
======

Everything lands in :file:`Build/Reports/design-review/<date>/`, which is
git-ignored: one full-page PNG per page, viewport and scheme, plus
:file:`report.json` with every raw result and :file:`report.md` grouping
findings by rule, listing the twenty most frequent examples of each, and
tabulating axe violations by impact and node count. Pages that would not load
get their own section. The process exits non-zero if there is any finding or any
violation at all.

Defaults are viewports 390, 768 and 1440, schemes light and dark, and the
`neutral` theme; `--viewports`, `--schemes` and `--theme` change them. Each
viewport and scheme is visited twice, once normally and once with reduced motion
— except that reduced motion only runs at the first viewport, since it is a
stylesheet decision and not a layout one, and running it three times says the
same thing three times.

..  _developer-design-review-blind-spots:

What the harness cannot answer
==============================

The harness has no content. It therefore cannot see:

*   **Heading order.** Every probe group in the harness is an `h2`, so the
    document outline it produces is the harness's, not an element's.
*   **Alt text.** There are no images.
*   **Form labels.** There are no real forms, so the `Field` and `TextInput`
    wiring — `for`, `aria-describedby`, `aria-invalid` — is present in the
    components but not exercised.
*   **Contrast in situ.** axe measures the colours it finds, and what it finds
    is the harness's sample paragraph on the harness's background. The
    arithmetic guarantee for the themes comes from
    :ref:`developer-accessibility-contrast` instead; what the harness cannot
    tell you is whether a particular element put secondary text on an accent
    band.
*   **Real text lengths.** Nothing wraps to three lines, no word is long enough
    to overflow, and no list is long enough to scroll.

All five need the lab and live mode, and the last one needs a human.

..  _developer-design-review-checklist:

The per-family checklist
========================

Run this against the screenshots, at 390, 768 and 1440, in light and dark. Look
at every item in both schemes: a border that is one step too light in dark mode
is invisible in a screenshot you only took in light.

..  _developer-design-review-checklist-layout:

Layout
------

`Section`, `Container`, `Grid`, `Split`, `Stack`, `Center`.

*   The seam between two sections. Put `default` next to `surface`, `muted`,
    `inverted` and `accent` in turn: the boundary should be visible without a
    border, and two sections of the *same* surface should read as one band
    rather than as a doubled gap. That is what `surface="same-tone"` is for.
*   Vertical rhythm at 390 versus 1440. `spacing="tight"`, `"roomy"` and
    `"flush"` must stay ordered at every width — a tight section that is roomier
    than a default one at 390 means a clamp is inverted.
*   `Container` sizes `sm` to `full`. At 1440 the gutters should be equal on
    both sides and `prose` should cap the measure; at 390 every size should
    collapse to the same gutter, and `full` must not produce a horizontal
    scrollbar.
*   `Grid`: `cols-2` to `cols-6` collapse as the viewport narrows, but
    `fixed-2` to `fixed-6` deliberately do not. Check the fixed ones at 390 for
    overflow and for text that has become unreadably narrow.
*   `Split` at 768. `ratio="wide-start"` and `"wide-end"` should still be
    distinguishable, and `variant="media-first"` must change the visual order
    without changing the source order.
*   `Stack` gaps 0 to 12 should be visibly ordered, and
    `orientation="horizontal"` should wrap rather than overflow at 390.

..  _developer-design-review-checklist-typography:

Typography
----------

`Heading`, `Text`, `Eyebrow`, `Blockquote`, `Citation`, `CodeBlock`, `List`,
`SectionIntro`, `Kbd`, `Divider`.

*   `Heading` `level` versus `type`. An `h3` with `type="display-2"` must look
    like a display heading and still be an `h3` in the document outline — check
    both the screenshot and the accessibility tree.
*   Display sizes at 390. `display-1` to `display-3` are the first thing to
    break: look for a heading that has grown taller than the viewport, or a long
    word that overflows rather than wrapping.
*   `Text` colours `secondary`, `disabled` and `placeholder` on each surface,
    in dark mode especially. The audit guarantees the token pairs it lists;
    what it cannot check is a `disabled` label used where `secondary` was meant.
*   `Blockquote` at `size="lg"` and `CodeBlock` with `wrap="wrapped"` versus
    without: an unwrapped code block must scroll inside its own box and never
    push the page sideways.
*   `List` markers `dot`, `decimal` and `circle` with `dividers`, and a nested
    list — the marker alignment at 390 is where it usually fails.
*   `Divider` with `variant="with-label"`: the label should sit on the rule, not
    break it, and `orientation="vertical"` needs a height from its parent.

..  _developer-design-review-checklist-actions:

Actions
-------

`Button`, `IconButton`, `Link`, `ButtonGroup`, `Toolbar`.

*   All five button variants on all five section surfaces. `ghost` and `outline`
    on `inverted` and `accent` are the pairs that disappear.
*   Sizes `sm`, `md` and `lg` side by side: the label should stay vertically
    centred and the icon should scale with the text rather than with the box.
*   `width="full"` at 390 and at 1440 — a full-width button in a `lg` container
    is usually a mistake, and it shows up immediately at the wide viewport.
*   Focus rings on every variant, including `destructive`, which swaps the ring
    to the error colour. Tab through them rather than trusting the probe.
*   The press state. On a touch-sized viewport there is no hover, so check the
    `:active` overlay and the press transform — and check that the transform is
    gone under reduced motion.
*   `ButtonGroup` with `variant="attached"`: the inner radii should meet
    exactly, in every theme, since the radius is a token and `y2k` sets it to
    zero.
*   `Toolbar` at 390 with `dividers="divided"`: it must wrap or scroll, and its
    dividers must not end up dangling at the end of a row.

..  _developer-design-review-checklist-cards:

Cards and items
---------------

`Card`, `CardTitle`, `CardBody`, `CardFooter`, `ClickableCard`, `Item`,
`Feature`, `Figure`, `Thumbnail`, `AspectRatio`, `Avatar`.

*   `Card` variants `flat`, `raised` and `muted` on each surface. A `muted` card
    on a `muted` section is the case that vanishes; a `raised` card in dark mode
    is the case whose shadow does nothing.
*   Densities `tight` and `roomy`, with and without a footer, and with a title
    that wraps to two lines: the footer should stay on the baseline of the row
    when cards sit in a grid.
*   `media="top"`, `"start"` and `"end"` at 390 — a start-aligned media card
    normally has to stack, and the image must keep its ratio when it does.
*   `ClickableCard` is the important one for accessibility: the whole card is
    the target, so check that exactly one link inside it is the accessible name,
    that the hover and the press states are both present, and that the focus
    ring is drawn around the card rather than around the link inside it.
*   `Item` with `interactive`, `selected` and `disabled`, at both densities:
    `selected` must be distinguishable from `interactive`-plus-hover by
    something other than colour alone.
*   `Avatar` sizes `xsm` to `xl` beside text, and `Thumbnail` elevations `low`
    to `high` in dark mode.
*   `AspectRatio` `square`, `portrait` and `wide` with a real photograph and
    with a missing one — the empty case must not collapse to zero height.

..  _developer-design-review-checklist-data:

Data display
------------

`Table`, `MetadataList`, `ProgressBar`, `StatusDot`, `Badge`, `Timestamp`,
`Stepper`, `TreeList`, `OverflowList`, `EmptyState`, `Skeleton`, `Spinner`.

*   `Table` at 390. It must scroll inside its own container and never widen the
    page. Check `layout="fixed"` with a long cell, `striped` in dark mode, and
    `hoverable` on a touch viewport where the hover never arrives.
*   The table caption. Every data table needs one, and it may be visually
    hidden; check it is present rather than that it is visible.
*   `Badge`: all seventeen variants at once, in every theme, light and dark.
    This is the single most likely place for a palette to slip, and it is the
    reason nine hue pairs per theme are in the contrast audit.
*   `ProgressBar` `state="indeterminate"` under reduced motion — it may keep
    indicating progress, but it must say so deliberately in its own rule.
*   `StatusDot` never carries meaning alone: there must be a word beside it.
*   `MetadataList` `orientation="horizontal"` with `columns="3"` at 390, where
    it has to become stacked, and with a term long enough to wrap.
*   `Stepper` with `orientation="vertical"` and a current step: the current step
    carries `aria-current="step"` as well as its tint, and the step indicator is
    `aria-hidden`, so the status word has to be in the text.
*   `Skeleton` and `Spinner` under reduced motion, and `Spinner`
    `color="on-media"` over a real image.
*   `EmptyState` with the longest message the element allows.

..  _developer-design-review-checklist-navigation:

Navigation
----------

`Breadcrumb`, `Breadcrumbs`, `Pagination`, `Tabs`, `TabList`,
`SegmentedControl`, `Outline`, `Carousel`, `SiteHeader`, `SiteFooter`.

*   The header at 768, the width where a desktop navigation usually has to
    become a menu button but often does not.
*   `aria-current="page"` on the active item in the header, the footer and the
    breadcrumb, and the last crumb rendered as text rather than as a link to
    itself.
*   `Breadcrumbs` with a deep rootline at 390: it should truncate or scroll, and
    the current page must stay visible when it does.
*   `TabList` `variant="divided"` and `"fill"`, at all three sizes and in
    `orientation="vertical"`. Check that `aria-orientation` agrees with the axis
    you can see, and tab through with the arrow keys.
*   `Tabs` with JavaScript disabled: every panel should still be in the page,
    one after another, rather than all of them hidden.
*   `SegmentedControl` with three and with six segments at 390, including a
    `disabled` one.
*   `Pagination` at `size="sm"` with a three-digit page number.
*   `Carousel` gaps 2 to 6: check that the arrows disable at each end, that the
    rail still scrolls by touch and by keyboard with the arrows absent, and that
    it snaps under reduced motion — the snapping stays, only the smooth scroll
    goes.
*   The footer's legal and language rows at 390, and the back-to-top control if
    the site has one.

..  _developer-design-review-checklist-forms:

Forms
-----

`Field`, `TextInput`, `InputGroup`, `SearchForm`.

*   Every control has a `<label for>`, and a placeholder is never the only
    label.
*   `status="error"`, `"warning"` and `"success"` on `Field`: the message must
    say what is wrong in words, not only in red, and it must be announced —
    it is an `aria-live="polite"` region.
*   The `aria-describedby` chain. `Field` derives the hint and message ids from
    `inputId` and the caller hands them back to the control; check in the
    accessibility tree that the control actually points at both.
*   `orientation="horizontal"` at 390, where the label has to move back above
    the control.
*   Sizes `sm`, `md` and `lg`, and the coarse-pointer rule: on a touch viewport
    the font size of a text control must be at least 16px, or iOS zooms the page
    on focus and never zooms back.
*   `InputGroup` with a button attached at each end, in RTL as well: this is one
    of the layouts where a physical property would show up immediately.
*   `SearchForm` with JavaScript absent — the field must be visible and
    submittable rather than an icon that does nothing.

..  _developer-design-review-checklist-overlays:

Overlays
--------

`Dialog`, `Overlay`, `Tooltip`, `Banner`, `Collapsible`, `CollapsibleGroup`.

*   `Dialog` at 390 with content longer than the viewport: the body scrolls, the
    header and footer do not, and the backdrop covers the whole page.
*   The dialog's name. With a visible title it must be `aria-labelledby` that
    title; without one it must carry an `aria-label`. Check in the accessibility
    tree, since both attributes are always emitted and the unused one is empty.
*   Escape closes it and focus returns to the control that opened it — that is
    the browser's behaviour through `showModal()`, so what you are checking is
    that nothing in the markup has broken it.
*   `Tooltip` placements `top`, `bottom`, `start` and `end` near each edge of a
    390 viewport, and in RTL, where `start` and `end` swap.
*   `Overlay` `reveal="hover"` on a touch viewport: a caption that only appears
    on hover is a caption a touch user never sees. `reveal="always"` or
    `"focus"` is the answer, and `tone="dark"` has to keep the text readable
    over the lightest image the element allows.
*   `Banner` in all four statuses, on each section surface, in dark mode — and
    with an icon and without.
*   `Collapsible` at all three densities: the `<summary>` is the whole hit area,
    its focus ring must be visible, and the open and closed states must differ
    by more than the chevron's rotation.
*   `CollapsibleGroup` with two items open at once, and with the first item
    open on load.
