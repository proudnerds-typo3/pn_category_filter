..  _field-facets:

=============
Field filters
=============

Besides categories, records can be filtered on the value of a TCA ``select``
field, for example a status. A field filter is shown above the category groups,
with the same markup as a parent category: a collapsible group with one
checkbox per value.

Field filters are available since version 2.2.0.

Configuration
=============

A field filter takes three steps.

1.  Define it in TypoScript under a key of your choice:

    ..  code-block:: typoscript

        plugin.tx_pncategoryfilter.settings.fieldFacets {
            status {
                table = pages
                field = tx_mysite_status
                # Optional: the TCA label of the field is used otherwise
                label = LLL:EXT:my_site/Resources/Private/Language/locallang.xlf:filter.status
                # 1 = checkboxes (default), 0 = radio buttons with "All"
                multiple = 1
            }
        }

2.  Offer it in the FlexForm through page TSconfig, key = label:

    ..  code-block:: typoscript

        tx_pncategoryfilter.availableFieldFacets {
            status = LLL:EXT:my_site/Resources/Private/Language/locallang.xlf:filter.status
        }

3.  Select it in the plugin: :ref:`fieldFacetKeys <confval-flexform-fieldfacetkeys>`.

The table must be one of the plugin's :ref:`tables <confval-flexform-tables>`, and the field
must be a ``select`` field. A key that does not meet these conditions is
skipped. Keys may contain letters, digits, ``-`` and ``_``.

Values and labels
=================

-   The values and their order come from the field's TCA ``items``; labels are
    translated in the language of the page.
-   Without a ``label`` in TypoScript the filter takes the field's TCA label. A
    field with a label in the ``columnsOverrides`` of exactly one record type,
    as Content Blocks creates for a page type, uses that label.
-   The item with an empty value (often "Select …") and dividers are no filter
    value.
-   A field that stores several values (comma-separated) matches every value it
    contains.
-   Items added by an ``itemsProcFunc``, ``checkbox`` fields and
    ``foreign_table`` relations are not supported.

Combining
=========

-   Values within one field filter combine with OR.
-   A field filter combines with AND with the selected categories, the search
    term and the other field filters.
-   While a field filter is active, records of other tables than its ``table``
    drop out: they have no value for the field.
-   Only configured keys and known values are accepted from the request; anything
    else is ignored, as unknown categories are.

With :ref:`result counts <result-count>` enabled every value gets a badge. A
field filter counts its values without its own selection, as the refine mode
does for categories: each badge shows how many records have that value, given
the categories, the search term and the other field filters. A value with zero
results is disabled, unless it is selected.

The category badges in turn respect the active field filters.

With :ref:`search suggestions <search-suggestions>` enabled, a value whose label
matches the search term is suggested as "<filter>: <value> (<count>)"; choosing
it checks the value.

Request and URL
===============

The selection travels as
``tx_pncategoryfilter_categoryfilterlist[fieldFilters][<key>][]=<value>``, in
the AJAX request, the pushState URL and the pagination links. On a page load
with this argument the matching values are rendered checked and the group is
open.

The AJAX response ships the counts as
``<script type="application/json" data-field-facet-counts>`` (key => value =>
count); the JavaScript patches the badges from it.

Templates
=========

:file:`Partials/FilterMenu/FieldFacet.html` renders one field filter. It is
called from :file:`Partials/FilterMenu/CategoryList.html` for every entry in
``fieldFacets``. A :file:`List.html` override must pass that variable on, and
render the menu also when the category tree is empty, for example a plugin
that only selects a type category without children:

..  code-block:: html

    <f:if condition="{categoryTree} || {fieldFacets}">
        <f:render partial="FilterMenu/CategoryList"
            arguments="{categoryTree: categoryTree, fieldFacets: fieldFacets, level: 0, ttContentUid: ttContentUid, settings: settings}" />
    </f:if>

The JavaScript uses ``data-field-facet`` (the input, holding the key),
``data-field-facet-group`` (the group) and ``data-field-facet-value`` (the node
with the badge). Keep them in an override.

The variable ``hasActiveFieldFilters`` tells :file:`Results.html` whether a
field filter is active, for example to announce the result count as a status
message.
