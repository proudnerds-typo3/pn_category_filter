..  _installation:

============
Installation
============

Requirements
============

-   TYPO3 v14.3 LTS
-   PHP 8.3, 8.4 or 8.5
-   Optional: EXT:solr (``apache-solr-for-typo3/solr``), only for
    :ref:`synonym expansion <solr-synonyms>`

For TYPO3 v13.4 LTS, use version 1.x of the extension.

Install the extension
=====================

Composer (recommended):

..  code-block:: bash

    composer require proudnerds/pn-category-filter

Classic mode: download the ZIP from the
`TYPO3 Extension Repository <https://extensions.typo3.org/extension/pn_category_filter>`__
and import it through :guilabel:`System > Extensions`.

Load the TypoScript
===================

The extension ships a **site set**. Add it to the site's
:file:`config.yaml`:

..  code-block:: yaml
    :caption: config/sites/<site>/config.yaml

    dependencies:
      - proudnerds/pn-category-filter

Alternatively, import the TypoScript directly in your sitepackage:

..  code-block:: typoscript

    @import 'EXT:pn_category_filter/Configuration/TypoScript/constants.typoscript'
    @import 'EXT:pn_category_filter/Configuration/TypoScript/setup.typoscript'

A static template *ProudNerds Category Filter* is registered as well, for sites
that still use :sql:`sys_template` records.

..  important::
    The TypoScript defines a :typoscript:`PAGE` object ``ajaxLoadResults`` on
    ``typeNum = 1769081246``. Without it the AJAX endpoint returns the normal
    page instead of the result fragment. Change the type number through the
    constant
    :typoscript:`plugin.tx_pncategoryfilter.settings.pageType.ajaxLoadResults`
    if it collides with another extension.

..  _installation-assets:

Add the assets
==============

..  important::
    The JavaScript is **required**. Filtering, search, sorting and pagination
    are entirely AJAX-driven; there is no non-JavaScript fallback, so without
    the bundle the checkboxes and the search field do nothing.

The extension does not inject CSS or JavaScript itself, so it never conflicts
with a sitepackage that has its own asset pipeline. Pick one of the two options
below.

Option A — plain TypoScript
---------------------------

..  code-block:: typoscript

    page {
      includeCSS {
        pnCategoryFilter = EXT:pn_category_filter/Resources/Public/Css/style.css
      }
      includeJSFooter {
        pnCategoryFilter = EXT:pn_category_filter/Resources/Public/JavaScript/PnCategoryFilter.js
      }
    }

:file:`PnCategoryFilter.js` is a self-contained IIFE bundle that needs no build
step.

Option B — your own bundler
---------------------------

Import the ES module entry point in your sitepackage's bundle (Vite, webpack, …):

..  code-block:: javascript

    import 'pn_category_filter/Resources/Private/Assets/PnCategoryFilter.entry.js'

The sources live in :file:`Resources/Private/Assets/Scripts/`.

..  warning::
    Do not use both options at once — the filter would initialise twice and
    every click would fire two AJAX requests.

Add the plugin to a page
========================

#.  Create a content element of type :guilabel:`Category Filter List View`
    (:guilabel:`Plugins` tab).
#.  Open the :guilabel:`Plugin` tab and fill in the FlexForm — at minimum
    the categories and the tables to query.
#.  Save and view the page.

The category tree in the FlexForm stays empty until the category root is
configured in the site configuration; see :ref:`configuration-site`.

Clear caches
============

..  code-block:: bash

    vendor/bin/typo3 cache:flush

..  _upgrading-2-0:

Upgrading to 2.0
================

Version 2.0 supports TYPO3 v14.3 LTS only. Upgrade TYPO3 first, then the
extension:

..  code-block:: bash

    composer require proudnerds/pn-category-filter:^2.0
    vendor/bin/typo3 cache:flush

There are no database changes and no upgrade wizard. The plugin was already a
content element of its own (``CType``) in 1.x, so the list type migration of
TYPO3 v14 does not apply and existing plugin instances keep working.

-   The FlexForm fields moved from the :guilabel:`Configuration` tab to the
    :guilabel:`Plugin` tab of the content element. The fields and their values
    are unchanged.
-   If you extend ``CategoryFilterController``, ``CategoryFilterService``,
    ``PageContentHydrationService`` or ``SynonymService`` and override the
    constructor, pass the new constructor arguments on to the parent.

..  _upgrading-1-1:

Upgrading to 1.1
================

There are no database changes and no FlexForm migration: existing plugin
instances keep working without being saved again. After the update:

#.  Clear caches.
#.  If you bundle the ES module sources yourself (option B), rebuild your
    frontend assets. The IIFE bundle (option A) is replaced by the update.

Three defaults change what visitors see:

-   **Suggestions while typing** are on wherever the search form is shown. To
    switch them off, uncheck
    :ref:`showSearchSuggestions <confval-flexform-showsearchsuggestions>` in the
    plugin instance. The
    :ref:`constant <confval-constant-showsearchsuggestions>` only applies to
    instances that have not been saved since the update: saving stores the
    checkbox, which then wins over the constant.
-   **Search results are ordered by relevance** (:ref:`search-order`). Set the
    :ref:`relevanceSorting <confval-constant-relevancesorting>` constant to ``0``
    to keep the configured sort order.
-   **Search ignores accents**, so ``een`` also finds ``één``.

Sorting options whose field exists in none of the queried tables are no longer
shown in the frontend.

If you have your own listener on ``AfterRecordsFetchedEvent`` that only adds
data for display, such as images or related names, consider moving it to
:ref:`AfterRecordsPaginatedEvent <events-after-records-paginated>` or replacing
it with a :ref:`file <configuration-file-resolvers>` or
:ref:`category resolver <configuration-category-resolvers>`: it then runs for
one page of results instead of the whole result set. Existing listeners keep
working unchanged.
