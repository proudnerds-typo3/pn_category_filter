..  _result-count:

===================
Result count badges
===================

With :ref:`showResultCount <confval-flexform-showresultcount>` enabled, every
filter option gets a Solr-style ``(n)`` badge showing how many records it
yields. The counts are facet-aware and search-aware, and are recalculated on
every AJAX filter, search, reset or pagination action.

The setting is off by default; enable it per plugin instance in the FlexForm
(:guilabel:`Display Settings` tab) or globally through the TypoScript constant
:ref:`showResultCount <confval-constant-showresultcount>`. See
:ref:`examples` for what it looks like.

Behaviour
=========

-   **Leaf counts** are the number of matching records for that category.
-   **Parent counts** are the deduplicated rollup of all descendants: a record
    tagged to two children is counted once.
-   The drill-down follows the active :ref:`filter combine logic <filter-logic>`:

    *   *Refine* (``facet``): each option is counted **without its own facet's
        constraint**, which is how Solr behaves. A selected option therefore
        shows its full total, while the other options show "how many results you
        would get if you also picked this one".
    *   *Expand* (``expand``): every option shows its own search-aware total over
        the whole configured tree, so picking one filter never drops the others
        to zero — there is no filter trap.

-   Counts respect record removals: a record dropped by an
    :ref:`AfterRecordsFetchedEvent <events>` listener disappears from both the
    result list and every badge.

Empty options
=============

A leaf that yields zero results is disabled and dimmed: the ``--empty`` modifier
is added, the checkbox gets ``disabled``, and the cursor becomes
``not-allowed``.

A leaf that is **checked** and drops to zero stays operable, so it can still be
unchecked — otherwise the visitor would be locked into an empty result set
(WCAG 2.1.2, no keyboard trap).

Accessibility
=============

-   The visible ``(n)`` badge is decorative and carries ``aria-hidden``. An
    adjacent visually hidden ``.assistive`` span holds the phrase
    *{count} results* (``filter.resultCountUnit``, available in English, Dutch
    and German), and each leaf checkbox references it through
    ``aria-describedby``. A screen reader therefore announces e.g.
    "Images, 121 results".
-   Unavailability is signalled by opacity on the whole row **plus** the disabled
    control and the ``not-allowed`` cursor, never by colour alone (WCAG 1.4.1).
    Disabled controls are exempt from the contrast requirement of WCAG 1.4.3.

Styling
=======

The badge and empty-state styling is not shipped in a stylesheet that is loaded
automatically. Style these classes in your sitepackage:

..  list-table::
    :header-rows: 1

    -   -   Class
        -   Element
    -   -   ``pn-category-filter__result-count``
        -   The visible ``(n)`` badge
    -   -   ``pn-category-filter__amount``
        -   The active-sub-filter badge on a parent
    -   -   ``pn-category-filter__category-item--empty``
        -   A top-level row with zero results
    -   -   ``pn-category-filter__category-child--empty``
        -   A child row with zero results

Related setting
===============

:ref:`showActiveFilterCount <confval-flexform-showactivefiltercount>` is a
separate badge, shown on a **parent** category, counting how many of its
children are currently active. It is on by default and works independently of
``showResultCount``.
