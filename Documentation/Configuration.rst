..  _configuration:

=============
Configuration
=============

The extension is configured on four levels:

..  list-table::
    :header-rows: 1

    -   -   Level
        -   File
        -   Scope
    -   -   FlexForm
        -   *set in the backend*
        -   Per plugin instance
    -   -   TypoScript
        -   :file:`Configuration/TypoScript/`
        -   Per site or page tree
    -   -   Page TSconfig
        -   :file:`Configuration/page.tsconfig`
        -   Per page tree, backend only
    -   -   Site configuration
        -   :file:`config/sites/<site>/config.yaml`
        -   Per site

For every setting that exists on more than one level, **the FlexForm value
wins** over the TypoScript default. An empty FlexForm field falls back to
TypoScript.

Two TypoScript object paths are relevant, and Extbase merges them in this order:

..  list-table::
    :header-rows: 1

    -   -   Path
        -   Scope
    -   -   :typoscript:`plugin.tx_pncategoryfilter`
        -   All plugins of this extension — use this one
    -   -   :typoscript:`plugin.tx_pncategoryfilter_categoryfilterlist`
        -   Only the *Category Filter List View* plugin; overrides the path above

The examples below use :typoscript:`plugin.tx_pncategoryfilter`.

..  _configuration-site:

Site configuration
==================

The category tree selector in the FlexForm needs a starting point. Define it in
the site's :file:`config.yaml`:

..  code-block:: yaml
    :caption: config/sites/<site>/config.yaml

    base: 'https://www.example.com/'
    rootPageId: 1
    websiteTitle: 'My Website'

    # UID of the category that is the root of the filterable tree
    categories:
      root: 12

    dependencies:
      - proudnerds/pn-category-filter

The FlexForm reads it through the marker ``###SITE:categories.root###``:

..  code-block:: xml

    <settings.categories>
        <config>
            <type>category</type>
            <treeConfig>
                <startingPoints>###SITE:categories.root###</startingPoints>
            </treeConfig>
        </config>
    </settings.categories>

Each site can therefore have its own category tree, and editors only see the
categories relevant to their site. In *Refine* mode the direct children of this
root also define the facet groups — see :ref:`filter-logic`.

To find the UID: open :guilabel:`Web > List` on the page holding your categories
and look up the record under *System Categories*.

..  _configuration-tsconfig:

Page TSconfig
=============

The tables offered in the FlexForm's *Tables to Query* selector come from Page
TSconfig. The extension ships a default in :file:`Configuration/page.tsconfig`,
which TYPO3 v13 loads automatically:

..  code-block:: typoscript
    :caption: EXT:pn_category_filter/Configuration/page.tsconfig

    tx_pncategoryfilter {
        # Comma-separated list of table names to show in the FlexForm
        availableTables = pages,tt_content,tx_news_domain_model_news
    }

Add your own tables in the page tree's TSconfig, either by overwriting the list
or by extending it:

..  code-block:: typoscript

    tx_pncategoryfilter {
        availableTables := addToList(tx_myext_domain_model_item)
    }

A table only produces results if it has a ``categories`` relation in its TCA.

..  _configuration-flexform:

FlexForm settings
=================

All FlexForm fields, grouped by tab.

Tab: Category Settings
----------------------

..  confval:: settings.categories
    :name: flexform-categories
    :type: category tree

    The categories to filter by. The tree root comes from the
    :ref:`site configuration <configuration-site>`.

..  confval:: settings.includeSubcategories
    :name: flexform-includeSubcategories
    :type: boolean
    :Default: 1

    Also match records that are tagged to a subcategory of a selected category.

..  confval:: settings.showOnlyChildrenInFilter
    :name: flexform-showOnlyChildrenInFilter
    :type: boolean
    :Default: 1

    Show only child categories in the menu, hiding the configured parents.

..  confval:: settings.showFilterMenu
    :name: flexform-showFilterMenu
    :type: boolean
    :Default: 1

    Render the filter menu at all. Switch it off for a search-only listing or a
    plain categorized list.

..  confval:: settings.filterLogic
    :name: flexform-filterLogic
    :type: string
    :Default: expand

    How selected categories are combined: ``expand`` (union) or ``facet``
    (refine). See :ref:`filter-logic`.

Tab: Table Settings
-------------------

..  confval:: settings.tables
    :name: flexform-tables
    :type: string (multiple)

    The tables to query. The list is populated from the Page TSconfig setting
    ``availableTables``, see :ref:`configuration-tsconfig`.

..  confval:: settings.recordPids
    :name: flexform-recordPids
    :type: page uids

    Restrict records to these storage pages. Empty means anywhere.

..  _configuration-display:

Tab: Display Settings
---------------------

..  confval:: settings.displayMode
    :name: flexform-displayMode
    :type: string
    :Default: horizontal

    The layout of the filter menu: ``vertical`` for a sidebar with checkboxes,
    ``horizontal`` for a row of dropdown facets. Both are shown in
    :ref:`examples`.

..  confval:: settings.showResultCount
    :name: flexform-showResultCount
    :type: boolean
    :Default: 0

    Show a result-count ``(n)`` badge behind every filter option. See
    :ref:`result-count`.

..  confval:: settings.showActiveFilterCount
    :name: flexform-showActiveFilterCount
    :type: boolean
    :Default: 1

    Show a badge on a parent category counting how many of its children are
    currently active. Independent of ``showResultCount``.

..  confval:: settings.enablePushState
    :name: flexform-enablePushState
    :type: boolean
    :Default: 1

    Reflect filters, search term and sort order in the URL, so a result set can
    be bookmarked and shared.

..  confval:: settings.limit
    :name: flexform-limit
    :type: int
    :Default: 0

    Hard cap on the number of records, ``0`` means no limit. Applied after
    sorting and before pagination; the :ref:`count badges <result-count>` are
    still calculated on the unlimited set.

..  confval:: settings.sorting
    :name: flexform-sorting
    :type: string
    :Default: desc

    The default sort direction: ``asc``, ``desc`` or ``none``.

..  confval:: settings.sortField
    :name: flexform-sortField
    :type: string
    :Default: crdate

    The field(s) to sort by, comma-separated. The first field that is present on
    the record wins, which makes it possible to sort a mixed multi-table result
    set.

..  confval:: settings.boostFields
    :name: flexform-boostFields
    :type: string

    Comma-separated boolean fields that pin records to the top, for example
    ``istopnews``. See :ref:`boost-fields`.

Tab: Pagination Settings
------------------------

..  confval:: settings.pagination.itemsPerPage
    :name: flexform-itemsPerPage
    :type: int
    :Default: 10

    Number of results per page. Minimum 1.

..  confval:: settings.pagination.maxLinks
    :name: flexform-maxLinks
    :type: int
    :Default: 8

    Number of page links shown in the sliding window. Minimum 1.

Tab: Search Settings
--------------------

..  confval:: settings.showSearchForm
    :name: flexform-showSearchForm
    :type: boolean
    :Default: 0

    Render the search input.

..  confval:: settings.searchFields
    :name: flexform-searchFields
    :type: string
    :Default: title,description

    The fields to search, comma-separated. Besides real database columns, the
    virtual fields ``teaser`` and ``_pageContent`` are accepted when
    :ref:`page content hydration <page-content>` is enabled.

..  confval:: settings.searchPlaceholder
    :name: flexform-searchPlaceholder
    :type: string

    Placeholder text for the search input. Empty falls back to the translated
    default label.

..  confval:: settings.searchMinChars
    :name: flexform-searchMinChars
    :type: int
    :Default: 3

    Minimum number of characters before a search fires, between 1 and 10.
    Validated both in JavaScript and in PHP.

..  confval:: settings.useSolrSynonyms
    :name: flexform-useSolrSynonyms
    :type: boolean
    :Default: 0

    Expand search terms with the synonyms configured in EXT:solr. Requires
    ``showSearchForm`` to be on as well. See :ref:`solr-synonyms`.

..  _configuration-typoscript:

TypoScript constants
====================

Editable in :guilabel:`Site Management > TypoScript > Constant Editor`, category
``plugin.tx_pncategoryfilter``.

Template paths
--------------

..  confval:: view.templateRootPath
    :name: constant-templateRootPath
    :type: string
    :Default: EXT:pn_category_filter/Resources/Private/Templates/

..  confval:: view.partialRootPath
    :name: constant-partialRootPath
    :type: string
    :Default: EXT:pn_category_filter/Resources/Private/Partials/

..  confval:: view.layoutRootPath
    :name: constant-layoutRootPath
    :type: string
    :Default: EXT:pn_category_filter/Resources/Private/Layouts/

These are registered on index ``10``, so the extension's own templates on index
``0`` remain the fallback. Point them at your sitepackage to override individual
templates — see :ref:`configuration-templates`.

General settings
----------------

..  confval:: settings.textCropLength
    :name: constant-textCropLength
    :type: int
    :Default: 140

    Crop length for the teaser or description in the result list. This setting is
    **not** available in the FlexForm.

..  confval:: settings.displayMode
    :name: constant-displayMode
    :type: string
    :Default: horizontal

    The default display mode, used when the FlexForm field is empty.

..  confval:: settings.showResultCount
    :name: constant-showResultCount
    :type: boolean
    :Default: 0

    The default for the result-count badge.

..  confval:: settings.showActiveFilterCount
    :name: constant-showActiveFilterCount
    :type: boolean
    :Default: 1

    The default for the active-sub-filter badge.

..  confval:: settings.pageType.ajaxLoadResults
    :name: constant-pageType
    :type: int
    :Default: 1769081246

    The ``typeNum`` of the :typoscript:`PAGE` object that serves the AJAX
    fragment. Change it only when another extension already claims this number.

Page content hydration
----------------------

..  confval:: settings.pageContent.enabled
    :name: constant-pageContent-enabled
    :type: boolean
    :Default: 0

    Master switch for :ref:`page-content`.

..  confval:: settings.pageContent.teaser.colPos
    :name: constant-pageContent-teaser-colPos
    :type: string
    :Default: 1

    The single ``colPos`` the teaser is taken from. Empty skips teaser
    extraction.

..  confval:: settings.pageContent.teaser.fields
    :name: constant-pageContent-teaser-fields
    :type: string
    :Default: bodytext

    The :sql:`tt_content` columns to read; the first non-empty one wins per row.

..  confval:: settings.pageContent.teaser.cTypes
    :name: constant-pageContent-teaser-cTypes
    :type: string

    Restrict the teaser to these CTypes, comma-separated. Empty means all.

..  confval:: settings.pageContent.search.colPos
    :name: constant-pageContent-search-colPos
    :type: string

    The ``colPos`` values to include in ``_pageContent``, comma-separated. Empty
    means all.

..  confval:: settings.pageContent.search.fields
    :name: constant-pageContent-search-fields
    :type: string
    :Default: header,bodytext

    The :sql:`tt_content` columns concatenated into ``_pageContent``.

..  confval:: settings.pageContent.search.cTypes
    :name: constant-pageContent-search-cTypes
    :type: string

    Restrict the searchable content to these CTypes. Empty means all.

Frontend sorting options
------------------------

Two sorting options are pre-configured. Their labels are switched to Dutch and
German through ``siteLanguage("locale")`` conditions in
:file:`Configuration/TypoScript/constants.typoscript`.

..  confval:: settings.frontendSortingOptions.10.label
    :name: constant-sorting-10-label
    :type: string
    :Default: Date

..  confval:: settings.frontendSortingOptions.10.field
    :name: constant-sorting-10-field
    :type: string
    :Default: crdate

..  confval:: settings.frontendSortingOptions.20.label
    :name: constant-sorting-20-label
    :type: string
    :Default: Title

..  confval:: settings.frontendSortingOptions.20.field
    :name: constant-sorting-20-field
    :type: string
    :Default: title

TypoScript setup
================

Some settings only exist in the setup: not as a constant, and not in the
FlexForm.

..  _configuration-sorting:

Adding more sorting options
---------------------------

``frontendSortingOptions`` renders the sorting controls — a checkbox plus an
ascending/descending dropdown — in the filter menu, letting visitors re-sort the
results themselves. The constants cover two entries; add more directly in the
setup:

..  code-block:: typoscript

    plugin.tx_pncategoryfilter.settings {
        frontendSortingOptions {
            30 {
                label = Relevance
                field = sorting
            }
        }
    }

Notes:

-   Only one sorting option can be active at a time in the frontend.
-   The field name must be an existing database column of the queried table(s).
-   Re-sorting runs over AJAX and is reflected in the URL.
-   Remove the whole ``frontendSortingOptions`` block to hide the sorting UI.

The difference with the FlexForm:

..  list-table::
    :header-rows: 1

    -   -   Setting
        -   Controlled by
        -   Effect
    -   -   ``sorting`` / ``sortField`` (FlexForm)
        -   Editor
        -   The default order of the list, with multiple fallback fields allowed
    -   -   ``frontendSortingOptions`` (TypoScript)
        -   Visitor
        -   Interactive re-sorting; overrides the default and disables
            :ref:`boosting <boost-fields>`

The AJAX PAGE object
--------------------

The setup registers the endpoint that returns the result fragment:

..  code-block:: typoscript
    :caption: EXT:pn_category_filter/Configuration/TypoScript/setup.typoscript

    ajaxLoadResults = PAGE
    ajaxLoadResults {
        typeNum = {$plugin.tx_pncategoryfilter.settings.pageType.ajaxLoadResults}
        config {
            disableAllHeaderCode = 1
            no_cache = 1
        }
        10 = USER
        10 {
            userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
            extensionName = PnCategoryFilter
            pluginName = CategoryFilterList
            vendorName = ProudNerds
            controller = CategoryFilter
            action = ajaxLoadResults
        }
    }

Caching
=======

:file:`ext_localconf.php` registers a cache named ``pn_category_filter``, used
for the :ref:`Solr synonyms <solr-synonyms>`. It defaults to
:php:`SimpleFileBackend` and is only registered when the site has not already
defined it, so you can override it in :file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pn_category_filter'] = [
        'backend' => \TYPO3\CMS\Core\Cache\Backend\ApcuBackend::class,
        'options' => [],
        'groups' => ['all'],
    ];

If the cache is unavailable the extension degrades gracefully and simply skips
persistent caching.

Translations
============

..  list-table::
    :header-rows: 1

    -   -   File
        -   Contains
    -   -   :file:`Resources/Private/Language/locallang.xlf`
        -   Frontend labels: search, filter menu, pagination, sorting
    -   -   :file:`Resources/Private/Language/locallang_be.xlf`
        -   Backend labels: FlexForm fields, sheet titles, descriptions

Dutch and German ship as ``nl.`` and ``de.`` prefixed files. Override individual
labels without touching the extension:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['EXT:pn_category_filter/Resources/Private/Language/locallang.xlf'][]
        = 'EXT:my_sitepackage/Resources/Private/Language/Overrides/pn_category_filter.xlf';

..  _configuration-templates:

Overriding templates
====================

Register your own paths on an index above ``0``:

..  code-block:: typoscript

    plugin.tx_pncategoryfilter.view {
        templateRootPaths.20 = EXT:my_sitepackage/Resources/Private/Extensions/PnCategoryFilter/Templates/
        partialRootPaths.20 = EXT:my_sitepackage/Resources/Private/Extensions/PnCategoryFilter/Partials/
        layoutRootPaths.20 = EXT:my_sitepackage/Resources/Private/Extensions/PnCategoryFilter/Layouts/
    }

The relevant files:

..  list-table::
    :header-rows: 1

    -   -   File
        -   Renders
    -   -   :file:`Templates/CategoryFilter/List.html`
        -   The complete plugin
    -   -   :file:`Templates/CategoryFilter/AjaxLoadResults.html`
        -   The fragment returned by the AJAX endpoint
    -   -   :file:`Partials/Results.html`
        -   The result list
    -   -   :file:`Partials/Pagination.html`
        -   The pager
    -   -   :file:`Partials/FilterMenu/CategoryList.html`
        -   The filter menu
    -   -   :file:`Partials/FilterMenu/CategoryListChildren.html`
        -   A nested level of the menu
    -   -   :file:`Partials/FilterMenu/SearchForm.html`
        -   The search input
    -   -   :file:`Partials/FilterMenu/Actions.html`
        -   The reset button and the sorting controls

..  warning::
    The JavaScript hooks onto ``data-*`` attributes — ``data-ajax-url``,
    ``data-search-fields``, ``data-category-counts`` and others — never onto CSS
    class names. Keep those attributes intact when you override a template.
