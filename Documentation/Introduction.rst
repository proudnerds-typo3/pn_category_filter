..  _introduction:

============
Introduction
============

..  note::
    **Sponsored by Gemeente Tilburg.** This extension was built for the websites
    of the municipality of Tilburg, who funded the development and made it
    possible to release the result as open source. Our thanks to them.

What does it do?
================

`pn_category_filter` renders a frontend plugin that filters and searches records
by their TYPO3 system categories. Visitors tick categories, type a search term or
change the sort order; results are reloaded over AJAX, and the URL stays
bookmarkable.

Everything runs against the live database. There is no index to build, no queue
to process and no external service to keep alive.

Typical use cases:

-   Content portals with categorized articles, news or products
-   Knowledge bases with a hierarchical category tree
-   Directory sites with filterable listings
-   Media libraries spanning multiple content types

Features
========

Filtering
---------

-   Category filtering with hierarchical support: parents include their children
-   Two combine modes, :ref:`Expand and Refine <filter-logic>`: a union, or a
    faceted search with OR within a group and AND across groups
-   Multi-table: query :sql:`pages`, :sql:`tt_content` or any categorized custom
    table in one plugin instance
-   Optional :ref:`result count badges <result-count>` per option, facet- and
    search-aware, with zero-result options dimmed and disabled
-   Vertical (sidebar checkboxes) or horizontal (dropdown facets) layout

Search
------

-   Multi-word AND search across configurable fields, case-insensitive and
    UTF-8 safe
-   Debounced as-you-type search with a configurable minimum length
-   Search terms highlighted in the results through a dedicated ViewHelper
-   Optional :ref:`synonym expansion <solr-synonyms>` using an existing EXT:solr
    synonym list
-   Optional :ref:`page content hydration <page-content>`, making :sql:`pages`
    records searchable on their actual :sql:`tt_content` content

Result handling
---------------

-   Sorting with a configurable default plus optional user-facing sort controls
-   :ref:`Boost fields <boost-fields>` that pin records to the top
-   Pagination with state preservation in the URL

Integration
-----------

-   Configured per plugin instance through a FlexForm — no TypoScript required
    for the common cases
-   A :ref:`PSR-14 event <events>` to filter, modify or enrich the fetched records
-   WCAG 2.1 AA: ARIA labels, keyboard navigation, no colour-only status
-   English, Dutch and German labels included

Do you still need Solr?
=======================

Solr remains the right tool for full-text search over very large datasets with
relevance ranking, typo tolerance and faceted search across everything.

For **category-based filtering and search** this extension delivers a comparable
user experience without the infrastructure:

..  list-table::
    :header-rows: 1

    -   -   Use Solr when
        -   Use this extension when
    -   -   10,000+ records with deep full-text search
        -   Category filtering with a search box
    -   -   Relevance ranking and typo tolerance matter
        -   Exact or substring matching is enough
    -   -   You already operate a Solr cluster
        -   Hosting has no Solr, or you don't want the operational overhead

Already running Solr? Then you can keep using its synonym list without any
indexing — see :ref:`solr-synonyms`.

Styling
=======

The templates render semantic markup with BEM class names
(:css:`pn-category-filter__…`). A minimal example stylesheet ships in
:file:`Resources/Public/Css/style.css`, but it is not loaded automatically — the
visual result is meant to come from your own sitepackage. See
:ref:`installation`.
