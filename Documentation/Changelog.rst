..  _changelog:

=========
Changelog
=========

The full changelog is maintained in the repository:
`CHANGELOG.md <https://github.com/proudnerds-typo3/pn_category_filter/blob/main/CHANGELOG.md>`__.

Releases follow `Semantic Versioning <https://semver.org/>`__. Only
``Major.Minor`` versions are published as separate documentation branches,
because patch releases never introduce breaking changes or new features.

1.1.0
=====

Performance, search quality and display-only enrichment. No breaking changes.

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
-   Fixed: boost fields and relevance were switched off after the first AJAX
    update
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

