..  _filter-logic:

====================
Filter combine logic
====================

By default, ticking more categories **widens** the result set: every selected
category adds its records. For some listings — a vacancy search, a product
catalogue — you want the opposite, where each extra filter **narrows** the
results like a faceted search on a web shop.

The FlexForm setting :ref:`filterLogic <confval-flexform-filterlogic>`
(:guilabel:`Category Settings` tab) controls this per plugin instance.

The two modes
=============

..  list-table::
    :header-rows: 1

    -   -   Mode
        -   Value
        -   Behaviour
        -   Use for
    -   -   Expand
        -   ``expand`` (default)
        -   Union / OR across all selected categories — more filters means more
            results
        -   Discovery pages: "show me anything about these topics"
    -   -   Refine
        -   ``facet``
        -   OR within a group, AND across groups
        -   Listings where each filter group is an additional requirement

In *Refine* mode a **group** (facet) is a direct child of the category root
configured in the site's :file:`config.yaml`. Categories inside the same group
are OR'd; categories from different groups are AND'd.

Example
=======

A root *Social vacancies* with two groups::

    Social vacancies            ← root from config.yaml
    ├── Hours                   ← group
    │   ├── Fulltime
    │   └── Parttime
    └── Contract type           ← group
        ├── Target group
        └── Regular

The result in *Refine* mode::

    Fulltime + Parttime         → EXPANDS   (same group "Hours" → OR)
    Fulltime + Target group     → NARROWS   (different groups → AND)

A group with no selection never constrains the result. When nothing is selected
at all, the configured root acts as a single group and every record is shown.

Notes
=====

-   ``expand`` is the default, so existing plugin instances keep working
    unchanged after an upgrade — no migration is needed.
-   Search, sorting, boosting and pagination all run **after** the category
    combine step, so they behave identically in both modes: the facets narrow by
    category first, then the search term narrows further within that set.
-   The mode is read from the plugin settings, never from request input, so a
    visitor cannot switch it by manipulating the URL.
-   The mode also determines how the :ref:`result count badges <result-count>`
    are calculated.
