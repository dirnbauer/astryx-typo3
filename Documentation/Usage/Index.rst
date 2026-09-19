..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

..  _usage-editors:

For editors
===========

Adding content works the way it always does: **+ Content**, then a group in the
wizard. The ten groups are the ones Desiderio already uses, so the shelf labels
read the same whichever theme a site is on.

Every element carries a short description and a set of keywords, and with
`friendsoftypo3/visual-editor` installed the plus-button opens the element
library: a searchable picker that renders a live preview of each element from
its own demo record, rather than a screenshot that went stale two releases ago.

The **Frame** field on any element sets the surface it sits on — body, surface,
muted or accent. It wins over whatever the element's own template prefers, so an
editor can always break a run of identical bands without asking for a variant.

..  _usage-seeding:

Seeding a showcase
==================

..  code-block:: bash

    ddev exec vendor/bin/typo3 astryx-typo3:site:seed --content
    ddev exec vendor/bin/typo3 desiderio:library:seed --parent=<root uid> --hosts=astryx_typo3,core

The first creates the site root, a :file:`/components` hub, one chapter page per
group and the legal and error pages, then places every element on its chapter
page from its own fixture. The second seeds one demo record per element, which
is what the plus-button picker previews.

..  _usage-integrators:

For integrators
===============

A page template composes components, never classes:

..  code-block:: html

    <html xmlns:a="http://typo3.org/ns/Webconsulting/AstryxTypo3/Components/ComponentCollection"
          data-namespace-typo3-fluid="true">
        <a:layout.section spacing="tight" surface="muted">
            <a:layout.container size="lg">
                <a:atom.heading level="2">What we do</a:atom.heading>
                <a:atom.button variant="primary" parameter="{link}">Start</a:atom.button>
            </a:layout.container>
        </a:layout.section>
    </html>

The namespace declaration is required: without it Fluid renders `<a:…>` as
literal text rather than failing, so the mistake is invisible until someone
reads the page source. A unit test checks for it.

Which components exist, what each one may compose and how modifiers are spelled
is :ref:`developer-component-contract`. Overriding a template from a site
package, authoring a new element and running the design review are
:ref:`developer-authoring-elements` and :ref:`developer-design-review`.
