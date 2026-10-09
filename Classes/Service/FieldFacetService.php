<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use ProudNerds\PnCategoryFilter\Dto\FieldFacet;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Filters and counts records on the value of a TCA select field (settings.fieldFacets)
 */
class FieldFacetService
{
    private const string KEY_PATTERN = '/^[A-Za-z0-9_-]+$/';

    public function __construct(
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * Only enabled keys with a valid definition become a facet; requested values outside the
     * field's TCA items are dropped, as unknown categories are.
     *
     * @param array<string,mixed> $definitions settings.fieldFacets
     * @param list<string> $enabledKeys Keys chosen in the plugin, in display order
     * @param list<string> $tables Queried tables
     * @param array<mixed> $requested fieldFilters argument: key => list of values
     * @return array<string,FieldFacet> Keyed by facet key
     */
    public function buildFacets(
        array $definitions,
        array $enabledKeys,
        array $tables,
        array $requested,
        ?SiteLanguage $siteLanguage,
    ): array {
        $languageService = $siteLanguage !== null
            ? $this->languageServiceFactory->createFromSiteLanguage($siteLanguage)
            : $this->languageServiceFactory->create('default');

        $facets = [];
        foreach ($enabledKeys as $key) {
            $definition = $definitions[$key] ?? null;
            if (!is_array($definition) || preg_match(self::KEY_PATTERN, $key) !== 1) {
                continue;
            }

            $table = (string)($definition['table'] ?? '');
            $field = (string)($definition['field'] ?? '');
            $column = $GLOBALS['TCA'][$table]['columns'][$field] ?? [];
            if (!in_array($table, $tables, true) || ($column['config']['type'] ?? '') !== 'select') {
                continue;
            }

            $values = $this->resolveValues((array)($column['config']['items'] ?? []), $languageService);
            if ($values === []) {
                continue;
            }

            $multiple = (bool)($definition['multiple'] ?? true);
            $selected = [];
            foreach ((array)($requested[$key] ?? []) as $value) {
                if (is_scalar($value) && isset($values[(string)$value]) && !in_array((string)$value, $selected, true)) {
                    $selected[] = (string)$value;
                }
            }
            if (!$multiple) {
                $selected = array_slice($selected, 0, 1);
            }

            $label = $languageService->sL((string)($definition['label'] ?? ''))
                ?: $languageService->sL($this->resolveColumnLabel($table, $field))
                ?: $key;

            $facets[$key] = new FieldFacet($key, $table, $field, $label, $multiple, $values, $selected);
        }

        return $facets;
    }

    /**
     * Keys of the records that match every active facet; values within one facet combine with OR.
     *
     * @param array<int|string,array<string,mixed>> $records
     * @param array<string,FieldFacet> $facets
     * @param string|null $exceptKey Facet to leave out, for counting that facet's own values
     * @return array<string,bool>|null "{table}_{uid}" set, or null when no facet constrains
     */
    public function matchingKeys(array $records, array $facets, ?string $exceptKey = null): ?array
    {
        $active = array_filter(
            $facets,
            static fn(FieldFacet $facet): bool => $facet->isActive() && $facet->key !== $exceptKey
        );
        if ($active === []) {
            return null;
        }

        $keys = [];
        foreach ($records as $record) {
            foreach ($active as $facet) {
                if (array_intersect($this->recordValues($record, $facet), $facet->selected) === []) {
                    continue 2;
                }
            }
            $keys[$record['_tableName'] . '_' . $record['uid']] = true;
        }

        return $keys;
    }

    /**
     * @param array<int|string,array<string,mixed>> $records
     * @param array<string,bool>|null $allowedKeys Only count these records; null counts all
     * @return array<int|string,int> value => number of records, zeros included
     */
    public function countValues(array $records, FieldFacet $facet, ?array $allowedKeys): array
    {
        $counts = array_fill_keys(array_keys($facet->values), 0);
        foreach ($records as $record) {
            if ($allowedKeys !== null && !isset($allowedKeys[($record['_tableName'] ?? '') . '_' . ($record['uid'] ?? 0)])) {
                continue;
            }
            foreach (array_unique($this->recordValues($record, $facet)) as $value) {
                if (isset($counts[$value])) {
                    $counts[$value]++;
                }
            }
        }

        return $counts;
    }

    /**
     * A field of one record type (e.g. a Content Blocks page type) has its real label in that
     * type's columnsOverrides; with one distinct override label that one wins.
     */
    private function resolveColumnLabel(string $table, string $field): string
    {
        $overrideLabels = [];
        foreach ($GLOBALS['TCA'][$table]['types'] ?? [] as $type) {
            $label = (string)($type['columnsOverrides'][$field]['label'] ?? '');
            if ($label !== '') {
                $overrideLabels[$label] = true;
            }
        }

        return count($overrideLabels) === 1
            ? (string)array_key_first($overrideLabels)
            : (string)($GLOBALS['TCA'][$table]['columns'][$field]['label'] ?? '');
    }

    /**
     * @param array<mixed> $items TCA items
     * @return array<int|string,string> value => label
     */
    private function resolveValues(array $items, LanguageService $languageService): array
    {
        $values = [];
        foreach ($items as $item) {
            $value = (string)($item['value'] ?? '');
            // An empty value is the "nothing chosen" item, not something to filter on
            if ($value === '' || $value === '--div--') {
                continue;
            }
            $values[$value] = $languageService->sL((string)($item['label'] ?? '')) ?: $value;
        }

        return $values;
    }

    /**
     * A select with several values stores them comma-separated.
     *
     * @param array<string,mixed> $record
     * @return list<string>
     */
    private function recordValues(array $record, FieldFacet $facet): array
    {
        if (($record['_tableName'] ?? '') !== $facet->table) {
            return [];
        }

        return GeneralUtility::trimExplode(',', (string)($record[$facet->field] ?? ''), true);
    }
}
