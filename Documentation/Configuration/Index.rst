..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Everything a site chooses is a site setting, declared in
:file:`Configuration/Sets/AstryxTypo3/settings.definitions.yaml` with a label
and a description, so the whole list is also visible in the backend under
**Site Management → Settings**.

..  code-block:: yaml
    :caption: config/sites/<site>/settings.yaml

    astryx.theme.default: neutral
    astryx.theme.colorScheme: system
    astryx.brand.wordmark: 'Your name'
    astryx.brand.tagline: 'What you do, in six words'
    astryx.header.breadcrumb: true
    astryx.footer.legalPageIds: '12,13,14'
    astryx.footer.copyrightText: '© Your name'
    astryx.search.enabled: true
    astryx.search.targetPageId: 42
    elementLibrary.hosts: 'astryx_typo3,core'

..  _configuration-theme:

Theme and colour scheme
=======================

`astryx.theme.default` names one of the twenty-five themes. All of them ship in
one stylesheet as custom-property overrides, so switching is a repaint: nothing
is rebuilt and no content changes.

`astryx.theme.colorScheme` is `system`, `light` or `dark`, and it is independent
of the theme. Every colour token is a `light-dark()` pair resolved against
`color-scheme`, which is what lets a site pick a theme and a scheme separately
instead of shipping fifty of them.

A page overrides the theme for itself and everything below it through the
**Astryx theme** field in its page properties — useful for a campaign subtree
that should not look like the rest of the site.

..  _configuration-element-library:

Which elements the wizard offers
================================

`elementLibrary.hosts` restricts the New Content Element wizard to the catalogs
named. Without it, an installation carrying more than one theme lists every
catalog in one wizard, which is how an editor ends up placing a Desiderio hero
on an Astryx page.

..  _configuration-search:

Search
======

`astryx.search.enabled` puts the loupe in the header;
`astryx.search.targetPageId` is the page it submits to, and
`astryx.search.queryParameter` is the parameter it submits under (`q` by
default, `tx_solr[q]` for EXT:solr). The results page itself needs the third
site set — see :ref:`installation-sets`.
