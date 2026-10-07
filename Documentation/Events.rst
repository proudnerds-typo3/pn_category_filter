..  _events:

===============
Events and API
===============

AfterRecordsFetchedEvent
========================

:php:`ProudNerds\PnCategoryFilter\Event\AfterRecordsFetchedEvent`

Dispatched after the records have been fetched from the database, before
sorting, boosting, searching and pagination. Use it to filter, modify or enrich
the records with your own logic: an access check, a computed field, an external
data source.

..  list-table::
    :header-rows: 1

    -   -   Method
        -   Returns
        -   Purpose
    -   -   :php:`getRecords()`
        -   :php:`array`
        -   The fetched records
    -   -   :php:`setRecords(array $records)`
        -   :php:`void`
        -   Replace the record set
    -   -   :php:`getCategoryUids()`
        -   :php:`array`
        -   The category UIDs that were used
    -   -   :php:`getTables()`
        -   :php:`array`
        -   The queried table names
    -   -   :php:`getRecordPids()`
        -   :php:`array`
        -   The active PID restriction
    -   -   :php:`getSettings()`
        -   :php:`array`
        -   The merged plugin settings

Anything you remove here also disappears from the
:ref:`result count badges <result-count>`, so the counts stay truthful.

Example listener
----------------

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/CustomRecordFilter.php

    <?php

    declare(strict_types=1);

    namespace Vendor\Extension\EventListener;

    use ProudNerds\PnCategoryFilter\Event\AfterRecordsFetchedEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    #[AsEventListener(
        identifier: 'vendor/custom-record-filter',
        event: AfterRecordsFetchedEvent::class
    )]
    final class CustomRecordFilter
    {
        public function __invoke(AfterRecordsFetchedEvent $event): void
        {
            $records = array_filter(
                $event->getRecords(),
                fn (array $record): bool => $this->shouldIncludeRecord($record)
            );

            $event->setRecords($records);
        }

        private function shouldIncludeRecord(array $record): bool
        {
            return true;
        }
    }

Registration
------------

With :yaml:`autoconfigure: true` in your
:file:`Configuration/Services.yaml`, the :php:`#[AsEventListener]` attribute is
picked up automatically — no tags needed:

..  code-block:: yaml
    :caption: EXT:my_extension/Configuration/Services.yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true
        public: false

      Vendor\Extension\:
        resource: '../Classes/*'

Manual registration also works:

..  code-block:: yaml
    :caption: EXT:my_extension/Configuration/Services.yaml

    services:
      Vendor\Extension\EventListener\CustomRecordFilter:
        tags:
          - name: event.listener
            identifier: 'vendor/custom-record-filter'
            event: ProudNerds\PnCategoryFilter\Event\AfterRecordsFetchedEvent

Built-in listener
-----------------

The extension registers one listener on this event itself:
:php:`ProudNerds\PnCategoryFilter\EventListener\HydratePageContent`, which
implements :ref:`page-content`. It returns immediately when the feature is
disabled or :sql:`pages` is not among the queried tables.

..  _events-after-records-paginated:

AfterRecordsPaginatedEvent
==========================

:php:`ProudNerds\PnCategoryFilter\Event\AfterRecordsPaginatedEvent`

Dispatched with only the records of the current page, right before they are
rendered. Use it for enrichment that is only needed for display, such as related
names or a computed label: it runs for one page of results instead of the whole
result set.

..  list-table::
    :header-rows: 1

    -   -   Method
        -   Returns
        -   Purpose
    -   -   :php:`getRecords()`
        -   :php:`array`
        -   The records of the current page, in display order
    -   -   :php:`setRecords(array $records)`
        -   :php:`void`
        -   Replace the records; keep their number and order, because the
            pagination has already been computed
    -   -   :php:`getTables()`
        -   :php:`array`
        -   The queried table names
    -   -   :php:`getSettings()`
        -   :php:`array`
        -   The merged plugin settings
    -   -   :php:`getRequest()`
        -   :php:`ServerRequestInterface`
        -   The current request (site, language, arguments)

The :ref:`file <configuration-file-resolvers>` and :ref:`category resolvers
<configuration-category-resolvers>` and the :ref:`record links
<configuration-record-links>` run before this event. For images, categories
and links, configure those instead of writing a listener.

Example listener
----------------

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/AddAuthorNames.php

    <?php

    declare(strict_types=1);

    namespace Vendor\Extension\EventListener;

    use ProudNerds\PnCategoryFilter\Event\AfterRecordsPaginatedEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    #[AsEventListener(identifier: 'vendor/add-author-names')]
    final class AddAuthorNames
    {
        public function __invoke(AfterRecordsPaginatedEvent $event): void
        {
            $records = $event->getRecords();
            $uids = array_column($records, 'uid');
            // Fetch the names for all $uids in one query, then:
            foreach ($records as $key => $record) {
                $records[$key]['_authorName'] = '';
            }
            $event->setRecords($records);
        }
    }

Which event to use
==================

..  list-table::
    :header-rows: 1

    -   -   You want to
        -   Use
    -   -   Remove records, or add a field that search, sorting or the result
            counts depend on
        -   :php:`AfterRecordsFetchedEvent`
    -   -   Add data that is only shown in the result list
        -   :php:`AfterRecordsPaginatedEvent`, or a resolver

ViewHelper
==========

``HighlightSearchTermViewHelper`` wraps matched search terms in
:html:`<span class="result-highlight">`. See :ref:`search` for its arguments and
an example.

Services
========

The services can be injected into your own classes through constructor
injection:

..  list-table::
    :header-rows: 1

    -   -   Class
        -   Responsibility
    -   -   :php:`Service\CategoryFilterService`
        -   Fetching and combining category-filtered records
    -   -   :php:`Service\SearchService`
        -   Matching a search term against the fetched records
    -   -   :php:`Service\SynonymService`
        -   Reading and expanding EXT:solr synonyms
    -   -   :php:`Service\PageContentHydrationService`
        -   Enriching :sql:`pages` records with :sql:`tt_content`
    -   -   :php:`Service\FileResolverService`
        -   Resolving FAL fields to file UIDs (:ref:`configuration-file-resolvers`)
    -   -   :php:`Service\CategoryResolverService`
        -   Attaching categories to records (:ref:`configuration-category-resolvers`)
    -   -   :php:`Service\RecordLinkService`
        -   Attaching a typolink parameter to records (:ref:`configuration-record-links`)
    -   -   :php:`Dto\FilteredRecordsResult`
        -   The result object holding the records and the category counts

..  note::
    These classes are not covered by a stability promise yet. Signatures may
    change in a minor release until the API is explicitly declared stable.
