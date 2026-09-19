..  include:: /Includes.rst.txt

..  _developer-accessibility:

=============
Accessibility
=============

The accessibility decisions in this extension are made inside the components,
which is the only place they can be made once for 250 content elements. Each one
is written down in the component's own `<f:comment>`, and the ones that can be
checked arithmetically or mechanically are checked: the contrast audit measures
1,500 colour pairs, and the design review runs axe against every page it opens.

This page collects the decisions and says why they were taken that way.

..  _developer-accessibility-headings:

A heading level is not a heading size
=====================================

`Heading` takes `level` and `type` as separate arguments. `level` decides which
element is emitted, and therefore what a screen reader announces and where the
heading lands in the document outline; `type` decides only how big it looks.

..  code-block:: html
    :caption: Resources/Private/Components/Atom/Heading/Heading.fluid.html (excerpt)

    <f:case value="3"><h3 id="{id}" class="astryx-heading" data-level="3" data-type="{type}" data-weight="{weight}" data-color="{color}"><f:slot /></h3></f:case>

A band that needs an `h2` to read as display-sized says `level="2"
type="display-2"` rather than lying about its level. The component's own comment
calls this the single most common way a design system breaks heading structure,
and keeping the two arguments apart is what prevents it.

The page's own `h1` comes from the :file:`Pages/PageTitle` partial on the
content-page layouts. A startpage has no masthead, so
:file:`AstryxStartpage.fluid.html` puts the page title into the accessibility
tree instead, as an `h1` that is clipped rather than hidden:

..  code-block:: html
    :caption: Resources/Private/Templates/Pages/AstryxStartpage.fluid.html (excerpt)

    <a:atom.visuallyHidden as="h1">{page.pageRecord.title}</a:atom.visuallyHidden>

An element that opens a page renders its own heading at `level="1"`: the hero
family, `article-header` and the pricing page headers, thirty templates in all
at the time of writing. Every other element heads its band with an `h2`, and
heads the items inside a grid or list at `h3`.

..  warning::
    That makes the page's `h1` an editorial responsibility rather than a
    guaranteed one. A page-opening element on a layout that also renders the
    page title produces two `h1` elements, and so do two such elements on one
    page. Put one of them at the top of a startpage, and leave the page title
    to the content-page layouts.

..  _developer-accessibility-links:

A link that looks like a button is still a link
===============================================

`Button` does not let the caller choose its element. Which element is rendered
follows from what the caller gives it: a TYPO3 link field in `parameter` becomes
an `<f:link.typolink>`, a resolved URL in `href` becomes a plain `<a>`, and
neither leaves a real `<button>` for something that submits or toggles.

..  code-block:: html
    :caption: Resources/Private/Components/Atom/Button/Button.fluid.html (excerpt)

    <f:variable name="mode">{f:if(condition: parameter, then: 'typolink', else: '{f:if(condition: href, then: \'link\', else: \'button\')}')}</f:variable>

The reason is in the component's comment: middle-click, "open in new tab" and
the screen reader's list of links all depend on a navigating control being an
anchor. `Link` and `IconButton` resolve their element the same way, so the rule
holds wherever an action appears.

`rel="noopener noreferrer"` is emitted only where it means something — a
`target` that opens a new browsing context — rather than on every link.

..  _developer-accessibility-icon-only:

An icon-only control takes an accessible name
=============================================

`Icon` renders a glyph that is decorative by default:

..  code-block:: html
    :caption: Resources/Private/Components/Atom/Icon/Icon.fluid.html (excerpt)

    <span
        class="astryx-icon {class}"
        data-size="{size}"
        aria-hidden="true"><g:icon name="{name}" /></span>

An icon beside text says what the text already says, so it stays out of the
accessibility tree. Passing `ariaLabel` is the explicit statement that this one
is not decoration, and the component then exposes it as `role="img"` with that
name. The SVG itself is drawn by :php:`IconViewHelper` in `currentColor`, with
`aria-hidden` and `focusable="false"` already set.

`IconButton` is the case where the glyph is the whole label. Without `ariaLabel`
the control is announced as "button" and nothing more, which is why the
component's comment calls the argument required in practice even though Fluid
cannot make it so. `VisuallyHidden` is the other half of the same answer: text
that is read aloud but never drawn, clipped to one pixel rather than removed
with `display: none` or `visibility: hidden`, both of which would take it out of
the accessibility tree as well as off the screen. Its `as` argument picks the
element for what it means — a `<caption>` that names a data table, a
`<label for>` that names a search field whose placeholder is not a label.

..  _developer-accessibility-current:

`aria-current` marks the selected item
======================================

Wherever one item in a set is the one you are on, it says so.
`SiteHeader` and `SiteFooter` write `aria-current="page"` on the active
navigation item, `Breadcrumb` renders the last crumb as a `<span>` carrying
`aria-current="page"` rather than as a link to itself, and `Pagination` does the
same for the current page number. `Step` carries both a data attribute and an
ARIA state, and its comment says exactly why:

..  code-block:: html
    :caption: Resources/Private/Components/Molecule/Step/Step.fluid.html (excerpt)

    aria-current="{f:if(condition: '{status} == \'current\'', then: 'step', else: 'false')}"

The attribute is for the stylesheet and `aria-current` is for the reader; they
are not the same statement and are not made with the same mechanism. `Button`,
`Token` and `Item` take an `ariaCurrent` argument for the same purpose.

..  _developer-accessibility-motion:

One answer to reduced motion, and why it is unlayered
=====================================================

Before 2.0.0 the reduced-motion answer was scattered: nine CSS partials each
remembered to guard their own transition, and the design review found 704
elements where one had been forgotten, `.astryx-link`'s colour transition among
them. A vestibular disorder does not care which partial an animation was
declared in, so there is now one answer for the whole design system in
:file:`Resources/Private/Css/astryx/09-reduced-motion.css`.

..  code-block:: css
    :caption: Resources/Private/Css/astryx/09-reduced-motion.css (excerpt)

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        animation-duration: 0.01ms;
        animation-delay: 0.01ms;
        animation-iteration-count: 1;
        transition-duration: 0.01ms;
        transition-delay: 0.01ms;
      }

That partial is listed in `UNLAYERED` in
:file:`Build/Scripts/build-astryx-css.mjs`, so it is emitted outside every
cascade layer. An unlayered rule beats every layered one however specific the
layered selector is, which is exactly the guarantee needed here — and it is the
reason there is not a single `!important` in the file. A component may still
declare a reduced-motion rule of its own where "no motion" is not the same as
"no animation", a spinner that has to keep indicating progress being the
obvious case, and because such a rule sits inside its own layer it says so
deliberately rather than by accident.

Two details are worth keeping. The duration is `0.01ms` rather than `0`, because
an animation with a zero duration never fires its `animationend` event and any
script waiting for one would hang. And scroll-snap survives: smooth programmatic
scrolling into a snap point is motion and is turned off, but the snapping itself
is what makes a carousel usable from the keyboard.

..  _developer-accessibility-focus:

Focus is visible, and only where it should be
=============================================

The base rule draws a focus ring on anything the browser considers
keyboard-focused, and removes the ring the browser would otherwise draw on a
mouse click:

..  code-block:: css
    :caption: Resources/Private/Css/astryx/01-base.css (excerpt)

    :focus-visible {
      outline: 2px solid var(--color-accent);
      outline-offset: var(--focus-offset, 2px);
    }

Components that need their own ring use the tokens Astryx added at v0.6.0 —
`--focus-outline-width`, `--focus-outline-style`, `--focus-outline-color` and
`--focus-outline-offset` — so a theme can move the ring without a component
knowing. The destructive button variant swaps the ring to `--color-error`,
because an accent-coloured ring on a red control reads as the wrong affordance.

The skip link is the first focusable element on the page and moves into view on
focus rather than being revealed by a class, and `<main>` carries
`id="main-content"` and `tabindex="-1"` so the jump lands somewhere focusable:

..  code-block:: html
    :caption: Resources/Private/Templates/Layouts/Pages/Default.fluid.html (excerpt)

    <a href="#main-content" class="astryx-skip-link">
        <f:translate key="LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:chrome.skipToContent">Skip to main content</f:translate>
    </a>

The design review probes this mechanically: it focuses every focusable element
on the page and records a `focus-visible` finding for any whose outline and
box-shadow are unchanged by focusing it.

..  _developer-accessibility-touch:

Touch screens get a press affordance
====================================

Every hover rule in the component CSS sits inside `@media (hover: hover)`, and
every press rule sits outside it:

..  code-block:: css
    :caption: Resources/Private/Css/components/02-button.css (excerpt)

    @media (hover: hover) {
      .astryx-button:hover:not([disabled]):not([aria-disabled='true']) {
        background-image: linear-gradient(var(--color-overlay-hover), var(--color-overlay-hover));
      }
    }

    .astryx-button:active:not([disabled]):not([aria-disabled='true']) {
      background-image: linear-gradient(var(--color-overlay-pressed), var(--color-overlay-pressed));
    }

On a touch screen the hover state never happens, so a control whose only
feedback is hover gives no feedback at all. `.astryx-link` and
`.astryx-clickable-card` gained an `:active` state in 2.0.0 for exactly that
reason — a card-sized tap target that did nothing when pressed. The press
transform is removed again under `prefers-reduced-motion`, so the affordance
survives without the movement.

Form controls get one more coarse-pointer rule: iOS zooms the page when a
focused control is under 16px and never zooms back, so the font size of text
controls is raised under `@media (pointer: coarse)` only, leaving the desktop
type scale untouched.

Control heights come from `--size-element-sm`, `-md` and `-lg`, which the
vendored payload sets to 28, 32 and 36 pixels. All three clear the 24 by 24 CSS
pixel minimum of WCAG 2.2 SC 2.5.8 without reaching the 44 pixel comfort target,
so an icon-only control at the small size is worth looking at in context — and
axe's `target-size` rule runs on every page the design review opens.

..  _developer-accessibility-rtl:

Right-to-left
=============

There is no `[dir="rtl"]` block anywhere in the component CSS, and that is the
design rather than an omission: the component stylesheets use logical properties
throughout — `margin-inline`, `padding-inline`, `inset-inline-start`,
`border-inline` — and contain no physical `margin-left`, `padding-right` or
`text-align: left`. A page that sets `dir="rtl"` therefore flips without a
second stylesheet.

That claim is measured rather than asserted. The design review loads the harness
a second time with `document.documentElement.dir = 'rtl'`, checks that the page
does not scroll sideways, and checks the five components whose whole point is a
horizontal axis — `carousel`, `breadcrumbs`, `stepper`, `split` and `toolbar` —
for a first child that is still on the left. See :ref:`developer-design-review`.

..  _developer-accessibility-native:

Native elements, and what happens without JavaScript
====================================================

No element ships JavaScript of its own, and only two write a script hook into
their own markup. Everything else is either the platform — disclosures are
`<details>` and `<summary>`, modals are `<dialog>` opened with `showModal()`,
menus are the popover API, carousels are CSS scroll-snap — or one of the
seventeen components that carry a `data-g-…` hook for the single shared
:file:`Resources/Public/Js/astryx.js`.

A component that cannot do its whole job without a script says so in its own
comment rather than rendering a control that takes a click and does nothing.
`Lightbox` renders no previous and next arrows for that reason: a
`showModal()` dialog lives in the top layer, where `:target` cannot reach it,
so stepping works on the gallery in the page and not inside the dialog.

`Dialog` is the clearest case of why. The element gives the top layer, the focus
trap, the Escape key and a real `::backdrop` for nothing, so the only script
left is the click that opens it. Naming it is handled deliberately: a dialog
with a visible title points `aria-labelledby` at that title, and a dialog that
is nothing but a video takes `ariaLabel` instead.

Where a script is involved, the markup works without it. `Tabs` hides nothing:
with the script absent every panel stays in the page, so the band degrades into
a longer document rather than a broken one, and the text is still printed,
indexed and found by the browser's own search. `TabList` carries
`role="tablist"` and an accessible name, and emits `aria-orientation`
beside `data-orientation`, so the arrow keys a screen reader announces agree
with the axis the stylesheet lays the tabs out on. `Carousel` renders as a
named `<nav>` landmark only when it is given a label, and as a plain `<div>`
otherwise, because an unnamed rail of cards is not navigation.

..  _developer-accessibility-forms:

Form fields wire themselves up explicitly
=========================================

A Fluid component cannot reach into slotted content to annotate it, so `Field`
makes the wiring explicit instead of magical: `inputId` names the control, the
hint and the message take ids derived from it, and the caller hands those ids
straight back to the control as `describedBy`. `TextInput` turns
`status="error"` into `aria-invalid` at its own end, because the red border is a
colour and a colour is not an announcement.

..  code-block:: html
    :caption: Resources/Private/Components/Molecule/Field/Field.fluid.html (excerpt)

    <p class="astryx-field-status {f:if(condition: '{status} != \'default\'', then: status)}" id="{inputId}-message" aria-live="polite">{message}</p>

The message is a polite live region on purpose: a validation error that appears
after the page has settled has to be said, not merely painted red.

..  _developer-accessibility-contrast:

Contrast is measured, not eyeballed
===================================

:file:`Build/Scripts/audit-contrast.mjs` checks 1,500 pairs — 25 themes in light
and dark, thirty pairs each — against WCAG 2.2 AA: 4.5:1 for body text and 3:1
for boundaries, focus rings and meaningful graphics. `npm run build` runs it
last and exits non-zero on any failure, so a palette change cannot quietly
regress the site. What it can and cannot answer is stated in the script's own
header: 1.4.3 and 1.4.11 are arithmetic and are checked; 1.4.1 Use of Colour,
2.4.7 Focus Visible and 2.4.11 Focus Not Obscured are structural and are
reviewed in the templates instead.

Two things make the number trustworthy. Every pair is one a stylesheet actually
declares — the audit checks `--color-text-yellow` on `--color-background-yellow`
because that is the pair the badge partial writes, and an audit that measures
plausible-looking pairs reports failures nobody can see while missing the ones
they can. And translucent tints are composited onto the page canvas before being
measured, because that is what a reader's eye receives; several hue backgrounds
are 20% alpha, and measured raw they produce nonsense.

..  _developer-accessibility-checking:

Checking a change
=================

..  code-block:: bash
    :caption: The two automated gates

    npm run audit:contrast
    node Build/Scripts/design-review.mjs --harness

Neither replaces reading the markup. The harness has no content, so it cannot
see a heading order, an alt text, a form label or a colour contrast in situ; for
those, run the review against the lab as described in
:ref:`developer-design-review-live`, and walk the page with a keyboard and a
screen reader afterwards.
