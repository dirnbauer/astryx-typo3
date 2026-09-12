..  include:: /Includes.rst.txt

..  _developer:

=========
Developer
=========

Everything in this chapter is about one of two things: keeping the extension in
step with upstream Astryx, and keeping 250 content elements in step with each
other. Both are done by generating what can be generated and testing what
cannot, so almost every page here names a file in :file:`Build/` and a test in
:file:`Tests/`.

Read :ref:`developer-component-contract` first if you are changing markup, and
:ref:`developer-upstream-sync` first if you are changing the vendored Astryx
data. The other pages stand on their own.

..  toctree::
    :maxdepth: 1

    UpstreamSync
    ComponentContract
    Accessibility
    Commands
    AuthoringElements
    DesignReview
