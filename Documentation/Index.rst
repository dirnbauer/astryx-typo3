..  include:: /Includes.rst.txt

..  _start:

================
Astryx for TYPO3
================

:Extension key:
    astryx_typo3

:Package name:
    webconsulting/astryx-typo3

:Version:
    |release|

:Language:
    en

:Author:
    webconsulting studio

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

Meta's `Astryx <https://github.com/facebook/astryx>`__ design system,
server-rendered for TYPO3 14. Astryx ships as React components styled with
StyleX; this extension ships the same design system as Fluid components and
plain CSS, so there is no JavaScript framework between an editor pressing save
and a visitor seeing the page. What comes from upstream is the token vocabulary
and the seven official themes, pinned to release `v0.6.0` rather than to a
moving branch.

The catalogue is 250 Content Blocks composed out of 74 Fluid components in four
layers, and 25 themes that switch at runtime in light and dark. Everything a
reader of this manual needs to change — a component, an element, a theme, the
vendored upstream data — is generated from, or checked against, a file in
:file:`Build/`, and every rule that keeps 250 elements looking like one design
system is a test rather than a convention.

..  contents:: Table of Contents
    :depth: 2

----

..  toctree::
    :maxdepth: 2
    :caption: Overview

    Introduction/Index

..  toctree::
    :maxdepth: 2
    :caption: For developers

    Developer/Index

..  toctree::
    :maxdepth: 1
    :caption: Reference

    Sitemap
