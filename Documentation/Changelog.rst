..  _changelog:

=========
Changelog
=========

The full changelog is maintained in the repository:
`CHANGELOG.md <https://github.com/proudnerds-typo3/pn_category_filter/blob/main/CHANGELOG.md>`__.

Releases follow `Semantic Versioning <https://semver.org/>`__. Only
``Major.Minor`` versions are published as separate documentation branches,
because patch releases never introduce breaking changes or new features.

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

