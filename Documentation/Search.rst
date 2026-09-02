..  _search:

======
Search
======

Enable the search input per plugin instance with
:ref:`showSearchForm <confval-flexform-showsearchform>`
(:guilabel:`Search Settings` tab). It renders a native
:html:`<input type="search">`, so the browser's own clear button and mobile
keyboard behaviour come for free.

How matching works
==================

-   **Multi-word AND**: ``fonds cultuur`` matches records containing both words,
    in any order and possibly in different fields.
-   **Substring matching**: ``activ`` matches *Activiteit*, and so does ``tiv``.
-   **Case-insensitive** and **UTF-8 safe**: ``FONDS`` finds *fonds*, and
    *café*, *naïef* and *über* behave correctly, thanks to :php:`mb_strtolower()`
    and :php:`mb_strlen()`.
-   **Cross-field**: the first word may match the title while the second matches
    a keyword field.
-   All input is escaped; the search never builds SQL from user input and the
    highlighted output is XSS-safe.

Searching runs on the records that survived the category filter, so search and
filters always combine.

Which fields are searched
=========================

:ref:`searchFields <confval-flexform-searchfields>` takes a comma-separated list
of database columns of the queried tables, for example::

    title,description,keywords

Two virtual fields are available on top of the real columns when
:ref:`page content hydration <page-content>` is enabled:

..  list-table::
    :header-rows: 1

    -   -   Field
        -   Contains
    -   -   ``teaser``
        -   The extracted teaser text of a :sql:`pages` record
    -   -   ``_pageContent``
        -   The concatenated :sql:`tt_content` content of a :sql:`pages` record

Typing behaviour
================

..  list-table::
    :header-rows: 1

    -   -   Setting
        -   Effect
    -   -   :ref:`searchMinChars <confval-flexform-searchminchars>`
            (1–10, default 3)
        -   Nothing is searched below this length. Validated both in JavaScript
            and in PHP.
    -   -   400 ms debounce (fixed)
        -   The AJAX request only fires once typing pauses.

Highlighting
============

Matched terms are wrapped by the ``HighlightSearchTermViewHelper``:

..  code-block:: html

    <html xmlns:pn="http://typo3.org/ns/ProudNerds/PnCategoryFilter/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <pn:highlightSearchTerm searchTerm="{activeSearchTerm}" stripTags="1">
        {record.title}
    </pn:highlightSearchTerm>

..  confval:: searchTerm
    :name: viewhelper-searchTerm
    :type: string
    :Default: ''

    The active search term. Each word is highlighted separately.

..  confval:: stripTags
    :name: viewhelper-stripTags
    :type: boolean
    :Default: false

    Strip existing HTML from the content before highlighting. Useful for RTE
    content.

Words shorter than three characters are skipped, and existing HTML tags are
never broken open — only text nodes are wrapped.

Every match is wrapped in :html:`<span class="result-highlight">`, which you
style yourself:

..  code-block:: css

    .result-highlight {
      font-weight: 700;
    }

..  _solr-synonyms:

Solr synonym expansion
======================

If EXT:solr is already installed you can reuse its managed synonym list to
expand search terms — without indexing anything, and without a queue::

    "car"    →  matches car | automobile | vehicle
    "cost"   →  matches cost | price | fee | charge

The AND logic across words is preserved: ``car cost`` requires a match from
**both** groups.

Requirements
------------

-   ``apache-solr-for-typo3/solr`` installed and connected
-   Synonyms configured in :guilabel:`Search > Core Optimization > Synonyms`
-   In the plugin's FlexForm, both
    :ref:`showSearchForm <confval-flexform-showsearchform>` and
    :ref:`useSolrSynonyms <confval-flexform-usesolrsynonyms>` enabled

Without EXT:solr the extension detects the missing class and silently returns no
synonyms, so nothing breaks.

How it works
------------

Synonyms live inside the Solr server itself, not in the TYPO3 database, and are
read through the Solr Managed Resources API
(``schema/analysis/synonyms/<managedResourceId>``). ``SynonymService`` expands
each search word into an OR group before matching.

Caching
-------

..  list-table::
    :header-rows: 1

    -   -   Level
        -   Storage
        -   TTL
        -   Scope
    -   -   Runtime
        -   PHP array
        -   Current request
        -   Per process
    -   -   Persistent
        -   TYPO3 cache ``pn_category_filter``
        -   15 minutes
        -   Shared across requests

The cache key is the **Solr core name**, not the site or the language. All sites
sharing a core — the common single-core setup — therefore share one entry:

..  list-table::
    :header-rows: 1

    -   -   Setup
        -   Cache entries
        -   Result
    -   -   All sites on one core (typical)
        -   1
        -   Synonyms shared across sites
    -   -   A separate core per site
        -   1 per core
        -   Synonyms isolated per site

New synonyms become active once the persistent cache expires, at most 15
minutes, or immediately after flushing caches.

Performance
-----------

-   The Solr connection is only opened when both settings are on **and** a search
    term was actually submitted.
-   Warm cache: under 0.5 ms overhead per request.
-   Cold cache: one HTTP call to Solr, roughly 2–50 ms depending on the
    infrastructure.
-   Matching with synonyms uses regex alternates, which is not measurably slower
    than plain matching.

Adding synonyms
---------------

#.  Go to :guilabel:`Search > Core Optimization` in the TYPO3 backend.
#.  Select the right site or core in the page tree.
#.  Open the :guilabel:`Synonyms` tab.
#.  Enter a base word (``car``) and its synonyms
    (``automobile, vehicle, auto``).
#.  Click :guilabel:`Add synonyms`.
