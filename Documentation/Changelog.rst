..  _changelog:

=========
Changelog
=========

The full changelog is maintained in the repository:
`CHANGELOG.md <https://github.com/proudnerds-typo3/pn_category_filter/blob/main/CHANGELOG.md>`__.

Releases follow `Semantic Versioning <https://semver.org/>`__. Only
``Major.Minor`` versions are published as separate documentation branches,
because patch releases never introduce breaking changes or new features.

2.1.0
=====

-   Result records link to their page or, through ``recordLinks`` and the
    site's :typoscript:`config.recordLinks`, to their detail view; see
    :ref:`configuration-record-links`
-   The default :file:`Results.html` links the title when a link is available
    and shows the page ``abstract`` as preview text when there is no teaser
-   ``CategoryFilterController`` gets ``RecordLinkService`` through the
    constructor; subclasses that override it must pass it on
-   Fixed: the category list exposes list items to assistive technology
-   Fixed: after AJAX pagination focus moves to the results heading; an
    overridden ``Results.html`` marks its heading with ``data-results-title``
-   Fixed: quotes, slashes and other punctuation count as word boundaries in
    search, so ``haven`` finds *'Haven Leeft'*

2.0.0
=====

Support for TYPO3 v14.3 LTS; TYPO3 v13 is no longer supported. No database
changes and no upgrade wizard, see :ref:`upgrading-2-0`.

-   Requires TYPO3 v14.3 LTS and PHP 8.3 to 8.5
-   The FlexForm fields are on the :guilabel:`Plugin` tab of the content element
    instead of :guilabel:`Configuration`
-   ``HighlightSearchTermViewHelper`` is compatible with Fluid 5
-   Core services are injected through the constructor; subclasses that override
    a constructor must pass the new arguments on
-   Fixed: the error message after a failed AJAX request is translated and
    announced to screen readers
-   Fixed: the sort direction select has an accessible name

1.1.0
=====

Performance, search quality and display-only enrichment. No breaking changes;
see :ref:`upgrading-1-1` for the defaults that change what visitors see.

-   One category-relation query per table instead of one per category and table;
    the category tree is resolved in bulk (see :ref:`performance`)
-   :ref:`AfterRecordsPaginatedEvent <events-after-records-paginated>` with only
    the records of the current page
-   Built-in :ref:`file resolvers <configuration-file-resolvers>` and
    :ref:`category resolvers <configuration-category-resolvers>`
-   Search ignores accents and decodes HTML entities; results are ordered by
    :ref:`relevance <search-order>`
-   :ref:`Suggestions while typing <search-suggestions>`, on by default
-   Faster search feedback: 250 ms debounce, 100 ms fade, no fade with
    ``prefers-reduced-motion``
-   The IIFE bundle ``PnCategoryFilter.js`` for sites without a build step is
    generated from the ES module sources and includes all of the above
-   Fixed: boost fields and relevance were switched off after the first AJAX
    update
-   Fixed: sorting options on a field the table does not have are hidden, and an
    unknown sort field in the request no longer throws an exception
-   Fixed: a search field present in only one of several tables threw an
    exception
-   Fixed: the script now also initialises when loaded after
    ``DOMContentLoaded``

1.0.0
=====

First public release.

-   Category filtering with hierarchical support and multi-table querying
-   :ref:`Expand and Refine <filter-logic>` combine modes
-   AJAX :ref:`search <search>` with multi-word AND matching, debouncing and
    highlighting
-   Facet- and search-aware :ref:`result count badges <result-count>`
-   :ref:`Boost fields <boost-fields>`, default sorting and frontend sorting
    controls
-   :ref:`Page content hydration <page-content>` for :sql:`pages` records
-   Optional :ref:`Solr synonym expansion <solr-synonyms>`
-   PSR-14 :ref:`AfterRecordsFetchedEvent <events>`
-   English, Dutch and German labels

