# Changelog

All notable changes to `pn_category_filter` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/proudnerds-typo3/pn_category_filter/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/proudnerds-typo3/pn_category_filter/releases/tag/v1.0.0
