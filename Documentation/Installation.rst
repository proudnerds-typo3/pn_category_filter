..  _installation:

============
Installation
============

Requirements
============

-   TYPO3 v13.4 LTS
-   PHP 8.3 or 8.4
-   Optional: EXT:solr (``apache-solr-for-typo3/solr``), only for
    :ref:`synonym expansion <solr-synonyms>`

Install the extension
=====================

Composer (recommended):

..  code-block:: bash

    composer require proudnerds/pn-category-filter

Classic mode: download the ZIP from the
`TYPO3 Extension Repository <https://extensions.typo3.org/extension/pn_category_filter>`__
and import it through :guilabel:`Admin Tools > Extensions`.

Load the TypoScript
===================

The extension ships a TYPO3 v13 **site set**. Add it to the site's
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
#.  Open the :guilabel:`Configuration` tab and fill in the FlexForm — at minimum
    the categories and the tables to query.
#.  Save and view the page.

The category tree in the FlexForm stays empty until the category root is
configured in the site configuration; see :ref:`configuration-site`.

Clear caches
============

..  code-block:: bash

    vendor/bin/typo3 cache:flush
