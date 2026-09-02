..  _boost-fields:

============
Boost fields
============

Boost fields pin certain records to the top of the list, regardless of the
active sort order — useful for "featured" or "top news" items that editors want
to promote.

Configuration
=============

Fill in a comma-separated list of **boolean database columns** in the FlexForm,
:guilabel:`Display Settings` tab, field
:ref:`Boost Fields <confval-flexform-boostfields>`::

    istopnews

Or several, where the first truthy field wins::

    istopnews,featured

Behaviour
=========

Any record where one of the listed fields is truthy (``1``, ``true``) floats to
the top. Within the boosted group the normal sort order still applies, and so it
does within the non-boosted group::

    [boosted records, sorted by date desc]     ← istopnews = 1
    [normal records,  sorted by date desc]     ← istopnews = 0

Boosting only applies to the **default** sort order from the FlexForm. As soon as
a visitor picks a different order through the
:ref:`frontend sorting controls <configuration-sorting>`, boosting is skipped —
their explicit choice wins.

Notes
=====

-   The field names must exist on the queried table(s); a missing column is
    simply ignored.
-   Works with EXT:news (``istopnews``), custom tables, or any boolean column.
