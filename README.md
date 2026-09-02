# TYPO3 extension `pn_category_filter`

[![Latest Stable Version](https://poser.pugx.org/proudnerds/pn-category-filter/v/stable)](https://packagist.org/packages/proudnerds/pn-category-filter)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://get.typo3.org/version/13)
[![License](https://poser.pugx.org/proudnerds/pn-category-filter/license)](LICENSE)

Filter and search categorized TYPO3 records in the frontend: AJAX-powered,
faceted, multi-table, with optional Solr synonym expansion — but without a Solr
server, an indexer or a queue to maintain.

> **Sponsored by Gemeente Tilburg**, who funded the development for their own
> websites and made it possible to release the result as open source.

## Highlights

- Category filtering with hierarchy, over `pages`, `tt_content` or any
  categorized custom table
- Two combine modes: **Expand** (union) and **Refine** (faceted — OR within a
  group, AND across groups)
- AJAX search with multi-word AND matching, debouncing and term highlighting
- Optional result-count `(n)` badges per filter option, facet- and search-aware
- Boost fields, default sorting and optional frontend sorting controls
- Page content hydration: make `pages` searchable on their `tt_content`
- Optional synonym expansion through an existing EXT:solr synonym list
- PSR-14 event to filter or enrich the records
- WCAG 2.1 AA, with English, Dutch and German labels

## Requirements

TYPO3 v13.4 LTS · PHP 8.3+

## Installation

```bash
composer require proudnerds/pn-category-filter
```

Add the site set to your site's `config.yaml`:

```yaml
dependencies:
  - proudnerds/pn-category-filter
```

Then include the assets, add the plugin to a page and configure it through the
FlexForm. The [installation chapter](Documentation/Installation.rst) has the
details — note that the JavaScript is required and is deliberately not injected
automatically.

## Documentation

The full manual lives in [`Documentation/`](Documentation/Index.rst) and is
published at
[docs.typo3.org](https://docs.typo3.org/p/proudnerds/pn-category-filter/main/en-us/).

| Chapter | |
|---|---|
| [Introduction](Documentation/Introduction.rst) | What it does, and when you do (not) need Solr |
| [Implementation examples](Documentation/Examples.rst) | Screenshots of both display modes |
| [Installation](Documentation/Installation.rst) | Install, TypoScript, assets |
| [Configuration](Documentation/Configuration.rst) | Every FlexForm, TypoScript, TSconfig and site setting |
| [Filter combine logic](Documentation/FilterLogic.rst) | Expand vs. Refine |
| [Search](Documentation/Search.rst) | Search behaviour, highlighting, Solr synonyms |
| [Result count badges](Documentation/ResultCount.rst) | The `(n)` badges |
| [Boost fields](Documentation/BoostFields.rst) | Pinning records to the top |
| [Page content hydration](Documentation/PageContent.rst) | Searching `pages` on their content |
| [Events and API](Documentation/Events.rst) | PSR-14 event, ViewHelper, services |
| [Performance](Documentation/Performance.rst) | Benchmarks and tuning |

Render the manual locally:

```bash
docker run --rm -v "$PWD":/project -w /project \
  ghcr.io/typo3-documentation/render-guides:latest --config=Documentation
```

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Credits

Created and maintained by [ProudNerds](https://www.proudnerds.com/):
Jacco van der Post and Emile Blume.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
