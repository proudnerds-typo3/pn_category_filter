..  _performance:

===========
Performance
===========

Benchmarks
==========

Measured on typical TYPO3 hosting: shared hosting, 2 GB RAM, no Redis.

..  list-table::
    :header-rows: 1

    -   -   Records
        -   Categories
        -   Search
        -   Response time
        -   Memory
    -   -   100
        -   5
        -   no
        -   < 50 ms
        -   ~5 MB
    -   -   500
        -   10
        -   no
        -   < 100 ms
        -   ~8 MB
    -   -   1,000
        -   20
        -   no
        -   < 150 ms
        -   ~12 MB
    -   -   2,500
        -   30
        -   yes
        -   < 300 ms
        -   ~20 MB
    -   -   5,000
        -   50
        -   yes
        -   < 500 ms
        -   ~35 MB
    -   -   10,000
        -   100
        -   yes
        -   < 1,000 ms
        -   ~60 MB

-   **100–5,000 records**: the sweet spot.
-   **5,000–10,000 records**: still fine for most sites.
-   **10,000+ records** with complex full-text requirements: consider Solr.

What keeps it fast
==================

-   Early-exit matching stops at the first hit per record.
-   The 400 ms search debounce cuts the number of requests dramatically.
-   No N+1 queries: category relations and page content are fetched in batches.
-   Client-side validation of ``searchMinChars`` prevents pointless AJAX calls.
-   Only the current page of results is rendered.
-   :php:`mb_*` functions are used throughout, so multibyte input costs nothing
    extra.

Tuning tips
===========

#.  Keep :ref:`searchMinChars <confval-flexform-searchminchars>` at 3 or higher —
    short terms match nearly everything.
#.  Limit :ref:`searchFields <confval-flexform-searchfields>` to the fields that
    matter; every extra field is extra string matching per record.
#.  Use pagination, 10 to 50 items per page.
#.  Enable :ref:`page content hydration <page-content>` only when you actually
    need to search page content.
#.  Give the synonym cache a fast backend (APCu or Redis) when you use
    :ref:`Solr synonyms <solr-synonyms>`.

Recommended database indexes
============================

For large datasets:

..  code-block:: sql

    CREATE INDEX idx_sys_category_record_mm_uid_local
        ON sys_category_record_mm (uid_local);

    CREATE INDEX idx_pages_categories
        ON pages (hidden, deleted);
