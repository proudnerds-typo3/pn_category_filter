..  _page-content:

======================
Page content hydration
======================

When you query the :sql:`pages` table, the searchable surface is normally
limited to the columns of the page record itself: ``title``, ``description``,
``nav_title`` and so on. The actual content lives in :sql:`tt_content` rows and
stays invisible to the search.

Page content hydration solves that without an external indexer. With one batched
query, every :sql:`pages` record in the result set is enriched with two virtual
fields:

..  list-table::
    :header-rows: 1

    -   -   Virtual field
        -   Purpose
        -   Source
    -   -   ``record.teaser``
        -   The snippet shown under each result in the template
        -   The first non-empty configured field of the first :sql:`tt_content`
            row on the configured teaser ``colPos``
    -   -   ``record._pageContent``
        -   Searchable content for the search
        -   The concatenated configured fields of all :sql:`tt_content` rows
            matching the configured ``colPos`` and CTypes

Both are opt-in and configurable per site through TypoScript.

How it works
============

..  code-block:: text

    1. fetchFilteredRecords() returns the category-matched records
    2. AfterRecordsFetchedEvent is dispatched
    3. The built-in HydratePageContent listener:
       - skips when pageContent.enabled = 0
       - skips when 'pages' is not among the queried tables
       - runs one batched SELECT on tt_content for all remaining pids
       - fills record.teaser and record._pageContent
    4. SearchService filters using searchFields (which may now include _pageContent)
    5. The paginator pages the final result set

The extra query therefore only runs when the feature is enabled, :sql:`pages` is
among the tables, and page records actually survived the category filter.

Hydration also runs without an active search term, because ``record.teaser`` is
needed for display.

TypoScript
==========

..  code-block:: typoscript

    plugin.tx_pncategoryfilter.settings.pageContent {
        # Master switch — off by default
        enabled = 1

        teaser {
            # Single colPos holding the teaser, e.g. a backend layout with a
            # dedicated "Teaser" column on colPos = 1. Empty skips the teaser.
            colPos = 1

            # tt_content columns to look in; first non-empty wins per row
            fields = bodytext

            # Optional: limit to these CTypes (comma-separated, empty = all)
            cTypes =
        }

        search {
            # colPos values to include in _pageContent (empty = all)
            colPos = 0,1

            # tt_content columns concatenated into _pageContent
            fields = header,bodytext

            # Optional: limit to these CTypes
            cTypes =
        }
    }

Every setting is also available as a constant, see
:ref:`configuration-typoscript`.

Using ``_pageContent`` in the search
====================================

Add the virtual field to
:ref:`searchFields <confval-flexform-searchfields>` in the plugin's FlexForm::

    title,_pageContent

Or include the teaser as a separate source::

    title,teaser,_pageContent

``SearchService`` treats ``_pageContent`` like any other field: the same word
matching and, if enabled, the same :ref:`synonym expansion <solr-synonyms>`.

Rendering the teaser
====================

``record.teaser`` contains stripped, whitespace-collapsed plain text. The shipped
templates already render it:

..  code-block:: html

    <div class="pn-category-filter__item-text pn-category-filter__item-text--preview">
        <pn:highlightSearchTerm searchTerm="{activeSearchTerm}" stripTags="1">
            {record.teaser -> f:format.crop(maxCharacters: '{settings.textCropLength}', respectWordBoundaries: '1', append: '…')}
        </pn:highlightSearchTerm>
    </div>

Performance
===========

-   One batched query per request, never N+1, and only when the feature is
    active and page records exist.
-   Deleted, hidden, ``starttime`` and ``endtime`` restrictions are applied.
-   Language-aware: only rows whose ``sys_language_uid`` matches the current
    :php:`LanguageAspect` are fetched.
-   :php:`strip_tags()` runs once during hydration, so matching happens on plain
    text.
-   Fields are validated against the TCA; unknown fields are logged and skipped
    rather than causing an error.

Configuration examples
======================

Vacancy listing with a teaser on colPos = 1
-------------------------------------------

..  code-block:: typoscript

    plugin.tx_pncategoryfilter.settings.pageContent {
        enabled = 1
        teaser.colPos = 1
        teaser.fields = bodytext
        search.colPos = 0,1
        search.fields = header,bodytext
    }

``searchFields`` = ``title,teaser,_pageContent``

News pages — no separate teaser, just search the page content
-------------------------------------------------------------

..  code-block:: typoscript

    plugin.tx_pncategoryfilter.settings.pageContent {
        enabled = 1
        teaser.colPos =
        search.colPos =
        search.fields = header,subheader,bodytext
    }

``searchFields`` = ``title,description,_pageContent``

Knowledge base limited to specific CTypes
-----------------------------------------

..  code-block:: typoscript

    plugin.tx_pncategoryfilter.settings.pageContent {
        enabled = 1
        search.cTypes = textmedia,text
        search.fields = header,bodytext
    }

When not to enable it
=====================

-   Your records are not in :sql:`pages` — the feature does nothing then anyway.
-   The plain page columns already cover your search needs.
-   You are in genuine Solr territory: 10,000+ pages with deep content search.
