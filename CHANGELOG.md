# Changelog

All notable changes to `pn_category_filter` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] – 2026-09-23

No database changes; clear caches after the update. Suggestions while typing,
relevance ordering and accent-insensitive search are on by default. See
"Upgrading to 1.1" in `Documentation/Installation.rst`.

### Added

- `AfterRecordsPaginatedEvent`, dispatched with only the records of the current
  page, for enrichment that is only needed for display
- `fileResolvers` TypoScript setting: resolves FAL fields of the displayed
  records to file UIDs, one query per configured table and field
- `categoryResolvers` TypoScript setting: attaches the categories of the
  displayed records as `uid` + `title` rows
- `CategoryUtility::getChildCategoryUidsBulk()` as public API
- Search results are sorted by relevance: a match in an earlier `searchFields`
  field ranks higher. Boost fields still come first and a visitor-chosen sort
  order overrides it; switch it off with the `relevanceSorting` constant
- Suggestions while typing (`showSearchSuggestions`, on by default wherever the
  search form is shown, switch it off per plugin instance): up to five matching
  titles and three matching categories with their count, as an accessible
  combobox; they arrive with the results, so no extra request

### Changed

- Records are fetched with one `sys_category_record_mm` query per table instead
  of a `CategoryCollection` per category and table
- Record visibility is checked through each table's TCA enable columns instead
  of fixed `hidden`, `starttime` and `endtime` column names
- Child categories are resolved in bulk and cached per request, and the search
  filter runs once per request
- Faster search feedback: 250 ms debounce instead of 400 ms, a 100 ms fade
  instead of 150 ms, and the request starts while the old results fade out;
  with `prefers-reduced-motion` there is no fade at all
- `Resources/Public/JavaScript/PnCategoryFilter.js` (the IIFE bundle for sites
  without a build step) is now generated from the ES module sources with
  esbuild and contains all changes of this release; in 1.0.x it was maintained
  by hand
- Search and highlighting ignore accents ("cafe" finds and marks "café") and
  decode HTML entities, so "allergie&euml;n" matches "allergieën"

### Fixed

- A sort field that does not exist in the queried tables no longer throws an
  exception: frontend sorting options without such a field are hidden, and an
  unknown `sortField` in the request is ignored
- Boost fields were ignored after the first AJAX update: the frontend always
  sent the pre-checked default sort order, so the server treated it as a
  visitor's choice. Sorting is now only sent, and kept in the pagination links,
  after the visitor picked one
- A search field that exists in only one of several queried tables no longer
  throws "Search field(s) … do not exist"; the check used to look at the first
  ten records only
- `PnCategoryFilter.js` now also initialises when it is loaded after
  `DOMContentLoaded`, for example through a dynamic `import()`

## [1.0.0] – 2026-09-02

First public release.

### Added

- Category filtering with hierarchical support (`includeSubcategories`), over
  `pages`, `tt_content` or any categorized custom table in one plugin instance
- Two combine modes: *Expand* (union, default) and *Refine* (faceted — OR within
  a group, AND across groups), configurable per plugin instance via
  `filterLogic`
- Vertical (sidebar) and horizontal (dropdown) display modes
- AJAX search with multi-word AND matching, 400 ms debouncing, a configurable
  minimum length and UTF-8 safe, case-insensitive substring matching
- `HighlightSearchTermViewHelper` for highlighting search terms in results
- Facet- and search-aware result count `(n)` badges (`showResultCount`), with
  zero-result options dimmed and disabled
- Active-sub-filter count badges on parent categories (`showActiveFilterCount`)
- Boost fields that pin records to the top of the default sort order
- Default sorting with multiple fallback fields, plus optional frontend sorting
  controls configured through `frontendSortingOptions`
- Pagination with a sliding window and bookmarkable URLs via `pushState`
- Page content hydration: `record.teaser` and `record._pageContent` for `pages`
  records, filled with one batched `tt_content` query
- Optional synonym expansion using an existing EXT:solr synonym list, cached in
  the `pn_category_filter` cache
- PSR-14 `AfterRecordsFetchedEvent` to filter, modify or enrich the record set
- TYPO3 v13 site set `proudnerds/pn-category-filter`
- English, Dutch and German labels

### Fixed

- **Search stopped working after the first AJAX update.**
  `ContentLoader.replaceContent()` replaces the container's `innerHTML`, which
  destroys the original search form node. `SearchManager` kept a reference to
  that detached node, so events fired on an invisible element.
  `CategoryFilterManager.attachContentUpdateListener()` now re-queries the form
  and creates a fresh `SearchManager` after every `ContentLoaderUpdated` event,
  preserving the current search term.
- **Search stopped working after "Reset filters".** The `ContentLoaderUpdated`
  handler returned early while `isResetting` was true, which was only meant to
  skip the `pushState` update but also skipped the `SearchManager`
  re-initialisation. Only the `pushState` call is conditional now.

[Unreleased]: https://github.com/proudnerds-typo3/pn_category_filter/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/proudnerds-typo3/pn_category_filter/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/proudnerds-typo3/pn_category_filter/releases/tag/v1.0.0
