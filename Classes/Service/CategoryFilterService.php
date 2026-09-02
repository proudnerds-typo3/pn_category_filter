<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use ProudNerds\PnCategoryFilter\Dto\FilteredRecordsResult;
use ProudNerds\PnCategoryFilter\Event\AfterRecordsFetchedEvent;
use ProudNerds\PnCategoryFilter\Utility\CategoryUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Category\Collection\CategoryCollection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CategoryFilterService
 *
 * Business logic for filtering records by categories across multiple tables
 */
class CategoryFilterService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SearchService $searchService,
        private readonly SynonymService $synonymService,
        private readonly LoggerInterface $logger,
    ) {}
    /**
     * Sorting options
     */
    public const string SORT_NONE = 'none';
    public const string SORT_ASC = 'asc';
    public const string SORT_DESC = 'desc';

    /**
     * Combine modes for multiple selected categories
     */
    public const string COMBINE_EXPAND = 'expand';
    public const string COMBINE_FACET = 'facet';

    /**
     * Fetch records filtered by categories from multiple tables
     *
     * @param array $categoryUids
     * @param array $tables
     * @param array $recordPids
     * @param bool $includeSubcategories
     * @param int $limit
     * @param string $sorting Sorting option: 'none', 'asc', or 'desc'
     * @param string $sortField Field name(s) to use for sorting (comma-separated)
     * @param array $settings Plugin settings from FlexForm/TypoScript (optional)
     * @param string $searchTerm Search term to filter records (optional)
     * @param string $boostFields Comma-separated boolean field names whose truthy value floats records to the top (e.g. 'istopnews,boost')
     * @param bool $solrSynonymsActive Whether to expand search terms with Solr synonyms
     * @param Site|null $site Current TYPO3 site (used for Solr synonym lookup)
     * @param int $language Language UID for Solr synonym lookup (default 0)
     * @param string $combineMode How selected categories combine: 'expand' (union) or 'facet' (OR within facet, AND across facets)
     * @param array $rootCategories Configured root category UIDs from FlexForm (required to resolve facets in 'facet' mode)
     * @return FilteredRecordsResult Filtered records plus per-category match counts
     * @throws Exception
     */
    public function fetchFilteredRecords(
        array $categoryUids,
        array $tables,
        array $recordPids,
        bool $includeSubcategories,
        int $limit,
        string $sorting = self::SORT_DESC,
        string $sortField = 'tstamp',
        array $settings = [],
        string $searchTerm = '',
        string $boostFields = '',
        bool $solrSynonymsActive = false,
        ?Site $site = null,
        int $language = 0,
        string $combineMode = self::COMBINE_EXPAND,
        array $rootCategories = [],
    ): FilteredRecordsResult {
        // Build the matched record set according to the combine mode.
        // Both branches produce the COMPLETE set (no slicing) — limit/pagination run later.
        // Alongside the records we keep a per-category attribution map (categoryUid => set of
        // record keys) so match counts can be derived without re-running the fetch pipeline.
        // In facet mode $facetData carries the extra drill-down material (union records, per-facet
        // key sets, category→facet map); it stays null in expand mode.
        //
        // The AfterRecordsFetchedEvent (which may REMOVE records, e.g. filtering ads without a
        // confirmed organization) must run over the SAME set that both the displayed records and the
        // counts derive from. In facet mode that set is the full union: we dispatch over the union and
        // then re-derive the displayed AND-intersection from the event-filtered result, so a record a
        // listener drops disappears from both the list and every badge count. All existing listeners
        // act per-record (keyed on _tableName/uid), so processing the union yields the same displayed
        // records as before — only the previously uncounted removals are now honoured.
        // Both branches populate $unionRecords with the full (event-filtered) union and $allRecords with
        // the displayed subset; $facetData stays null outside facet mode.
        $facetData = null;
        if ($combineMode === self::COMBINE_FACET && !empty($rootCategories)) {
            $facetData = $this->fetchFacetedRecords(
                $categoryUids,
                $tables,
                $recordPids,
                $includeSubcategories,
                $rootCategories
            );
            $categoryKeys = $facetData['categoryKeys'];

            // Event over the full union, then restrict to the displayed AND-intersection.
            $unionRecords = $this->dispatchAfterRecordsFetched(
                $facetData['unionRecords'],
                $categoryUids,
                $tables,
                $recordPids,
                $settings
            );
            $allRecords = $this->restrictRecordsToKeys($unionRecords, $facetData['intersectedKeys']);
        } else {
            // EXPAND (default): the DISPLAYED set is the union of the SELECTED categories, but to count
            // EVERY option (not only the selected ones) attribution is fetched over the FULL configured
            // tree. Without this, picking one category drops all other options to 0 — which, combined
            // with the "0 = disabled" rule, would trap the visitor after their first pick. Each option
            // therefore shows its own search-aware total (Solr OR-facet behaviour), consistent with
            // facet mode.
            $selectedUids = $includeSubcategories
                ? CategoryUtility::expandCategoryList($categoryUids)
                : $categoryUids;

            // Fetch the whole tree so unselected options can be counted too (falls back to the selected
            // union when no configured roots are known).
            $treeUids = !empty($rootCategories)
                ? CategoryUtility::expandCategoryList($rootCategories)
                : $selectedUids;

            $fetch = $this->fetchRecordsForCategories($treeUids, $tables, $recordPids);
            $categoryKeys = $fetch['categoryKeys'];

            // Event over the full union, then restrict the displayed set to the selected categories so a
            // record a listener drops disappears from both the list and the counts (see facet branch).
            $unionRecords = $this->dispatchAfterRecordsFetched(
                $fetch['records'],
                $categoryUids,
                $tables,
                $recordPids,
                $settings
            );

            $selectedKeys = $this->collectKeysForCategories($categoryKeys, $selectedUids);
            $allRecords = $this->restrictRecordsToKeys($unionRecords, $selectedKeys);
        }

        // Apply search filter if search term is provided. The synonym map is resolved once here so
        // it can be reused for the count-side search over the full union (facet mode) below.
        $synonymMap = [];
        if (!empty($searchTerm)) {
            // Fetch synonym map from Solr (empty array when synonyms are disabled or Solr is unavailable)
            $synonymMap = $solrSynonymsActive && $site !== null
                ? $this->synonymService->getSynonymsForSite($site, $language)
                : [];

            $allRecords = $this->searchService->filterRecordsBySearch($allRecords, $searchTerm, $settings, $synonymMap);
            $unionRecords = $this->searchService->filterRecordsBySearch($unionRecords, $searchTerm, $settings, $synonymMap);
        }

        // Count matches per category on the COMPLETE set (before limit/pagination), search-aware.
        if ($facetData !== null) {
            // Drill-down: count each option WITHOUT its own facet constraint. Search survivors are
            // computed over the FULL (event-filtered) union so unselected options can be counted too.
            $survivingCategoryKeys = $this->computeFacetDrillDownKeys(
                $categoryKeys,
                $facetData['facetKeysets'],
                $facetData['facetByCategory'],
                $this->extractRecordKeys($unionRecords)
            );
        } else {
            // Expand (union): each category's count is its attribution intersected with the surviving
            // records. Counting runs over the FULL-tree union ($unionRecords) so unselected options keep
            // their own totals. Leaf counts derive directly; attachCountsToTree() rolls parents up with
            // deduplication.
            $survivingCategoryKeys = $this->intersectSurvivingKeys($categoryKeys, $this->extractRecordKeys($unionRecords));
        }
        $counts = array_map('count', $survivingCategoryKeys);

        // Apply sorting and boost in a single combined pass for performance
        if ($sorting !== self::SORT_NONE || !empty($boostFields)) {
            $allRecords = $this->sortAndBoostRecords($allRecords, $sorting, $sortField, $boostFields);
        }

        // Apply limit if set
        if ($limit > 0) {
            $allRecords = array_slice($allRecords, 0, $limit);
        }

        return new FilteredRecordsResult(array_values($allRecords), $counts, $survivingCategoryKeys);
    }

    /**
     * Intersect each category's attribution with the surviving record keys.
     *
     * The result is search-aware and reflects the complete (un-limited) result set. It is the
     * basis for both leaf counts (count of a category's set) and parent rollups (deduplicated
     * union of descendant sets).
     *
     * @param array<int,array<string,bool>> $categoryKeys categoryUid => set of "{table}_{uid}" keys
     * @param array<string,bool> $survivorKeys Set of surviving "{table}_{uid}" keys
     * @return array<int,array<string,bool>> categoryUid => set of SURVIVING keys (empty sets omitted)
     */
    protected function intersectSurvivingKeys(array $categoryKeys, array $survivorKeys): array
    {
        $result = [];
        foreach ($categoryKeys as $categoryUid => $keys) {
            $surviving = array_intersect_key($keys, $survivorKeys);
            if ($surviving !== []) {
                $result[$categoryUid] = $surviving;
            }
        }

        return $result;
    }

    /**
     * Build a lookup set of "{table}_{uid}" keys for the given records.
     *
     * Derived from the record fields (not the array keys) so it is robust against re-indexing
     * by the search filter or the AfterRecordsFetchedEvent.
     *
     * @param array<int|string,array<string,mixed>> $records
     * @return array<string,bool> Set of "{table}_{uid}" keys
     */
    protected function extractRecordKeys(array $records): array
    {
        $keys = [];
        foreach ($records as $record) {
            $table = (string)($record['_tableName'] ?? '');
            if ($table === '') {
                continue;
            }
            $keys[$table . '_' . ($record['uid'] ?? 0)] = true;
        }

        return $keys;
    }

    /**
     * Dispatch AfterRecordsFetchedEvent and return the (possibly modified) record set.
     *
     * Listeners may enrich or REMOVE records before sorting/limiting. Runs once over whichever set is
     * passed in — the displayed set in expand mode, the full union in facet mode (see fetchFilteredRecords).
     *
     * @param array<string,array<string,mixed>> $records
     * @param array<int> $categoryUids
     * @param array<string> $tables
     * @param array<int> $recordPids
     * @param array<string,mixed> $settings
     * @return array<int|string,array<string,mixed>> Records after listener processing
     */
    protected function dispatchAfterRecordsFetched(
        array $records,
        array $categoryUids,
        array $tables,
        array $recordPids,
        array $settings
    ): array {
        $event = new AfterRecordsFetchedEvent($records, $categoryUids, $tables, $recordPids, $settings);
        $event = $this->eventDispatcher->dispatch($event);

        return $event->getRecords();
    }

    /**
     * Build the OR-union of the attribution key sets of the given categories.
     *
     * Used in expand mode to derive the displayed selection (union of the selected categories) from the
     * full-tree attribution that backs the counts.
     *
     * @param array<int,array<string,bool>> $categoryKeys categoryUid => set of "{table}_{uid}" keys
     * @param array<int> $categoryUids Categories whose key sets to union
     * @return array<string,bool> Union of the requested categories' key sets
     */
    protected function collectKeysForCategories(array $categoryKeys, array $categoryUids): array
    {
        $set = [];
        foreach ($categoryUids as $categoryUid) {
            $uid = (int)$categoryUid;
            if (isset($categoryKeys[$uid])) {
                // Array union deduplicates by "{table}_{uid}" key.
                $set += $categoryKeys[$uid];
            }
        }

        return $set;
    }

    /**
     * Keep only the records whose "{table}_{uid}" key is in the allowed set.
     *
     * Keys are derived from the record fields (not the array keys) so the result is robust against
     * re-indexing by the AfterRecordsFetchedEvent. Used in facet mode to re-derive the displayed
     * AND-intersection from the event-filtered union.
     *
     * @param array<int|string,array<string,mixed>> $records
     * @param array<string,bool> $allowedKeys Set of "{table}_{uid}" keys to retain
     * @return array<string,array<string,mixed>> Retained records, keyed by "{table}_{uid}"
     */
    protected function restrictRecordsToKeys(array $records, array $allowedKeys): array
    {
        $result = [];
        foreach ($records as $record) {
            $table = (string)($record['_tableName'] ?? '');
            if ($table === '') {
                continue;
            }
            $key = $table . '_' . ($record['uid'] ?? 0);
            if (isset($allowedKeys[$key])) {
                $result[$key] = $record;
            }
        }

        return $result;
    }

    /**
     * Fetch the UNION of records for a flat list of category UIDs across tables.
     *
     * Records are keyed by "{table}_{uid}" so the same record matched via multiple
     * categories appears once. This is the shared building block for both expand mode
     * (called once) and facet mode (called once per facet group).
     *
     * @param array $categoryUids
     * @param array $tables
     * @param array $recordPids
     * @return array{records: array<string,array<string,mixed>>, categoryKeys: array<int,array<string,bool>>}
     *         records = deduplicated records keyed by "{table}_{uid}";
     *         categoryKeys = per-category attribution (categoryUid => set of record keys)
     */
    protected function fetchRecordsForCategories(array $categoryUids, array $tables, array $recordPids): array
    {
        $records = [];
        $categoryKeys = [];

        foreach ($categoryUids as $categoryUid) {
            $catUid = (int)$categoryUid;
            foreach ($tables as $tableName) {
                try {
                    $fetched = $this->fetchRecordsFromCategoryCollection(
                        $catUid,
                        $tableName,
                        $recordPids
                    );

                    foreach ($fetched as $key => $record) {
                        $records[$key] = $record;
                        // Remember which category this record matched via (attribution for counts).
                        $categoryKeys[$catUid][$key] = true;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('CategoryFilterService: failed to fetch records', [
                        'categoryUid' => $categoryUid,
                        'table' => $tableName,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['records' => $records, 'categoryKeys' => $categoryKeys];
    }

    /**
     * Faceted matching: OR within a facet, AND across facets — with the data needed for
     * drill-down counting.
     *
     * A "facet" is a direct child of a configured root. The selected categories in one facet
     * are OR-combined; the resulting per-facet key sets are AND-combined across facets to form
     * the displayed set. To also count UNSELECTED options (drill-down), attribution is fetched
     * for EVERY category in the configured tree (not just the selected facets) — one fetch pass,
     * same order of cost as expand mode.
     *
     * Returns the COMPLETE displayed set plus the raw material for drill-down; sorting, limit and
     * pagination are applied by the caller afterwards.
     *
     * @param array $categoryUids Selected category UIDs
     * @param array $tables
     * @param array $recordPids
     * @param bool  $includeSubcategories
     * @param array $rootCategories Configured root UIDs (facet boundary)
     * @return array{
     *     records: array<string,array<string,mixed>>,
     *     unionRecords: array<string,array<string,mixed>>,
     *     intersectedKeys: array<string,bool>,
     *     categoryKeys: array<int,array<string,bool>>,
     *     facetKeysets: array<int,array<string,bool>>,
     *     facetByCategory: array<int,int>
     * } records = intersected (displayed) set; unionRecords = every fetched record (for search-aware
     *   counting AND as the single set the AfterRecordsFetchedEvent runs over — see fetchFilteredRecords);
     *   intersectedKeys = "{table}_{uid}" keys of the displayed AND-intersection (so the caller can
     *   re-derive the displayed set from the event-filtered union); categoryKeys = attribution for all
     *   tree categories; facetKeysets = per SELECTED facet the OR-union of its selected categories
     *   (AND-constraint); facetByCategory = every attributed category mapped to its facet.
     * @throws Exception
     */
    protected function fetchFacetedRecords(
        array $categoryUids,
        array $tables,
        array $recordPids,
        bool $includeSubcategories,
        array $rootCategories
    ): array {
        // Fetch attribution for EVERY category in the configured tree, so unselected options can be
        // counted too. One fetch pass over all descendants of the configured roots.
        $allTreeUids = CategoryUtility::expandCategoryList($rootCategories);
        $all = $this->fetchRecordsForCategories($allTreeUids, $tables, $recordPids);
        $unionRecords = $all['records'];
        $categoryKeys = $all['categoryKeys'];

        // Map every attributed category to its facet (direct child of a root).
        $facetByCategory = CategoryUtility::mapCategoriesToFacets(array_keys($categoryKeys), $rootCategories);

        // Build the AND-constraint: for each SELECTED facet, the OR-union of its selected categories
        // (expanded with subcategories) taken from the already-fetched attribution.
        $selectedFacetMap = CategoryUtility::mapCategoriesToFacets($categoryUids, $rootCategories);
        $groups = []; // facetUid => selected category UIDs in that facet
        foreach ($selectedFacetMap as $selectedUid => $facetUid) {
            $groups[$facetUid][] = (int)$selectedUid;
        }

        $facetKeysets = []; // facetUid => union of its selected categories' record keys
        foreach ($groups as $facetUid => $groupUids) {
            $uids = $includeSubcategories
                ? CategoryUtility::expandCategoryList($groupUids)
                : $groupUids;

            $keySet = [];
            foreach ($uids as $uid) {
                if (isset($categoryKeys[$uid])) {
                    // Array union deduplicates by "{table}_{uid}" key.
                    $keySet += $categoryKeys[$uid];
                }
            }
            $facetKeysets[$facetUid] = $keySet;
        }

        // AND across selected facets: intersect the per-facet key sets to get the displayed set.
        $intersectedKeys = null;
        foreach ($facetKeysets as $keySet) {
            $intersectedKeys = $intersectedKeys === null
                ? $keySet
                : array_intersect_key($intersectedKeys, $keySet);
        }
        $intersectedKeys ??= [];

        $records = [];
        foreach ($unionRecords as $key => $record) {
            if (isset($intersectedKeys[$key])) {
                $records[$key] = $record;
            }
        }

        return [
            'records' => $records,
            'unionRecords' => $unionRecords,
            'intersectedKeys' => $intersectedKeys,
            'categoryKeys' => $categoryKeys,
            'facetKeysets' => $facetKeysets,
            'facetByCategory' => $facetByCategory,
        ];
    }

    /**
     * Compute drill-down counts for facet mode: each option is counted WITHOUT its own facet's
     * constraint (Solr behaviour). Selecting an option shows its full count; other options show
     * how many results you would get if you also picked them, given the currently active facets.
     *
     * For a category C in facet F: count the records tagged to C that also match every OTHER active
     * facet AND pass the search filter. Because C's own key set already implies membership of F, we
     * simply skip F when intersecting the active facet constraints.
     *
     * All descendants of a facet share that facet, so the resulting per-category surviving sets can
     * be rolled up by attachCountsToTree() with correct deduplication.
     *
     * @param array<int,array<string,bool>> $categoryKeys categoryUid => attribution key set (all tree)
     * @param array<int,array<string,bool>> $facetKeysets facetUid => AND-constraint key set (active facets)
     * @param array<int,int> $facetByCategory categoryUid => facetUid
     * @param array<string,bool> $searchSurvivorKeys Keys of records passing the search filter
     * @return array<int,array<string,bool>> categoryUid => surviving key set (empty sets omitted)
     */
    protected function computeFacetDrillDownKeys(
        array $categoryKeys,
        array $facetKeysets,
        array $facetByCategory,
        array $searchSurvivorKeys
    ): array {
        $result = [];

        foreach ($categoryKeys as $categoryUid => $keys) {
            $facetOfCategory = $facetByCategory[$categoryUid] ?? $categoryUid;

            // Intersect all ACTIVE facets except the category's own facet.
            $base = null;
            foreach ($facetKeysets as $facetUid => $facetKeySet) {
                if ($facetUid === $facetOfCategory) {
                    continue;
                }
                $base = $base === null
                    ? $facetKeySet
                    : array_intersect_key($base, $facetKeySet);
            }

            // Apply the search filter (or use it as the whole base when no other facet constrains).
            $base = $base === null
                ? $searchSurvivorKeys
                : array_intersect_key($base, $searchSurvivorKeys);

            $surviving = array_intersect_key($keys, $base);
            if ($surviving !== []) {
                $result[$categoryUid] = $surviving;
            }
        }

        return $result;
    }

    /**
     * Fetch records from a category collection for a specific table
     *
     * @param int $categoryUid
     * @param string $tableName
     * @param array $recordPids
     * @return array
     */
    protected function fetchRecordsFromCategoryCollection(
        int $categoryUid,
        string $tableName,
        array $recordPids
    ): array {
        $records = [];

        // Load category collection
        $collection = CategoryCollection::load(
            $categoryUid,
            true, // Create if doesn't exist
            $tableName
        );

        // Load items from collection
        $collection->loadContents();

        // Get records from collection
        $now = time();
        foreach ($collection as $record) {
            // Check if record matches PID filter (if set)
            if (!empty($recordPids)) {
                $recordPid = (int)($record['pid'] ?? 0);
                if (!in_array($recordPid, $recordPids, true)) {
                    continue;
                }
            }

            // CategoryCollection::loadContents() removes all query restrictions, so we
            // must enforce frontend visibility manually: deleted, hidden, starttime, endtime.
            if (!empty($record['deleted']) || !empty($record['hidden'])) {
                continue;
            }
            $starttime = (int)($record['starttime'] ?? 0);
            if ($starttime > 0 && $now < $starttime) {
                continue;
            }
            $endtime = (int)($record['endtime'] ?? 0);
            if ($endtime > 0 && $now >= $endtime) {
                continue;
            }

            // Add table name to record for template usage
            $record['_tableName'] = $tableName;

            // Use uid + table as unique key to avoid duplicates
            $key = $tableName . '_' . ($record['uid'] ?? 0);
            $records[$key] = $record;
        }

        return $records;
    }

    /**
     * Sort records and apply boost fields in a single combined operation
     *
     * PERFORMANCE: One pass to pre-compute sort keys AND detect boost flag,
     * one usort(), one stable partition — instead of three separate iterations.
     *
     * @param array $records
     * @param string $sorting 'asc', 'desc', or 'none'
     * @param string $sortField Field name(s) to use for sorting (comma-separated)
     * @param string $boostFields Comma-separated boolean field names that float records to the top
     * @return array
     * @throws \RuntimeException If sort field doesn't exist in any of the records
     */
    protected function sortAndBoostRecords(
        array $records,
        string $sorting,
        string $sortField = 'tstamp',
        string $boostFields = ''
    ): array {
        if (empty($records)) {
            return $records;
        }

        $boostFieldList = !empty($boostFields)
            ? array_filter(array_map('trim', explode(',', $boostFields)))
            : [];

        // Single pass: pre-compute sort key AND boost flag per record
        $fieldFoundInAnyRecord = false;
        $failedRecords = [];

        foreach ($records as $key => $record) {
            // Boost detection
            $records[$key]['_boosted'] = false;
            foreach ($boostFieldList as $field) {
                if (isset($record[$field]) && (bool)$record[$field]) {
                    $records[$key]['_boosted'] = true;
                    break;
                }
            }

            // Sort key computation (skip if sorting is disabled)
            if ($sorting === self::SORT_NONE) {
                continue;
            }

            $result = $this->getSortValue($record, $sortField);

            if (is_array($result) && isset($result['_failed'])) {
                $failedRecords[] = $result;
                $records[$key]['_sortKey'] = 0;
            } else {
                $fieldFoundInAnyRecord = true;
                $records[$key]['_sortKey'] = $result;
            }
        }

        if ($sorting !== self::SORT_NONE && !$fieldFoundInAnyRecord && !empty($failedRecords)) {
            $firstFailed = $failedRecords[0];
            throw new \RuntimeException(
                sprintf(
                    'Sort field(s) "%s" not found in any records. Example from table "%s". Available fields: %s. ' .
                    'Please check your FlexForm sort field configuration.',
                    $sortField,
                    $firstFailed['_tableName'],
                    $firstFailed['_availableFields']
                ),
                1769174462
            );
        }

        // Sort if needed
        if ($sorting !== self::SORT_NONE) {
            if ($sorting === self::SORT_ASC) {
                usort($records, fn($a, $b) => $a['_sortKey'] <=> $b['_sortKey']);
            } else {
                usort($records, fn($a, $b) => $b['_sortKey'] <=> $a['_sortKey']);
            }
        }

        // Stable partition: boosted records first, preserving order within each group.
        // Since usort() has already run, both boosted and normal records are internally
        // sorted by the configured sort field. The final result is therefore:
        //   [boosted records, sorted by sortField]
        //   [normal records,  sorted by sortField]
        if (!empty($boostFieldList)) {
            $boosted = [];
            $normal = [];
            foreach ($records as $record) {
                unset($record['_sortKey']);
                if ($record['_boosted']) {
                    unset($record['_boosted']);
                    $boosted[] = $record;
                } else {
                    unset($record['_boosted']);
                    $normal[] = $record;
                }
            }
            return array_merge($boosted, $normal);
        }

        // No boost: just clean up temp keys
        foreach ($records as $key => $record) {
            unset($records[$key]['_sortKey'], $records[$key]['_boosted']);
        }

        return $records;
    }

    /**
     * Get sort value from record using configurable field names
     *
     * Returns the value if found, or a failure marker if not found
     * (so sortAndBoostRecords can check if ALL records failed)
     *
     * @param array $record
     * @param string $sortField Field name(s) to check (comma-separated)
     * @return mixed Value if found, or array with failure info
     */
    protected function getSortValue(array $record, string $sortField = 'tstamp'): mixed
    {
        // Parse comma-separated fields
        $fields = array_map('trim', explode(',', $sortField));

        // Try configured fields first
        foreach ($fields as $field) {
            if (!empty($field) && isset($record[$field])) {
                $value = $record[$field];

                // Normalize strings: strip leading non-alphabetic characters and lowercase
                // This ensures titles like 'Foo sort alongside Foo instead of before A
                if (is_string($value)) {
                    return mb_strtolower(preg_replace('/^[^\p{L}]+/u', '', $value));
                }

                return $value;
            }
        }

        // If we get here, none of the configured fields exist in this record
        // Return failure marker instead of throwing exception immediately
        // This allows sortRecords to check if ALL records failed
        $tableName = $record['_tableName'] ?? 'unknown_table';
        $availableFields = implode(', ', array_keys($record));

        return [
            '_failed' => true,
            '_tableName' => $tableName,
            '_availableFields' => $availableFields,
        ];
    }

    /**
     * Get categories with their information for filter menu
     *
     * @param array $categoryUids
     * @param bool $includeSubcategories
     * @param bool $showOnlyChildren
     * @return array
     * @throws Exception
     */
    public function getCategoryTreeForFilter(array $categoryUids, bool $includeSubcategories, bool $showOnlyChildren = false): array
    {

        if ($showOnlyChildren) {
            // Only get children and sub-children, exclude the parent categories themselves
            $childCategoryUids = [];

            foreach ($categoryUids as $parentUid) {
                $children = CategoryUtility::getChildCategoryUids($parentUid);
                $childCategoryUids = array_merge($childCategoryUids, $children);

                // If includeSubcategories is enabled, get all descendants
                if ($includeSubcategories) {
                    foreach ($children as $childUid) {
                        $descendants = CategoryUtility::getAllSubcategoryUids($childUid);
                        $childCategoryUids = array_merge($childCategoryUids, $descendants);
                    }
                }
            }

            $allCategoryUids = array_unique($childCategoryUids);
        } else {
            // Get all category UIDs (including subcategories if needed)
            $allCategoryUids = $includeSubcategories
                ? CategoryUtility::expandCategoryList($categoryUids)
                : $categoryUids;
        }

        // Fetch all category records in a single IN() query instead of one query per UID
        $categories = $this->getCategoryRecordsBulk($allCategoryUids);

        if (empty($categories)) {
            return [];
        }

        // Determine which categories have children (within our fetched set)
        foreach ($categories as &$category) {
            $hasChildren = false;
            // Check if any fetched category has this as parent
            foreach ($categories as $potentialChild) {
                if ((int)($potentialChild['parent'] ?? 0) === (int)$category['uid']) {
                    $hasChildren = true;
                    break;
                }
            }
            $category['hasChildren'] = $hasChildren;
            $category['childCategories'] = [];
        }
        unset($category);

        // Build hierarchical structure
        return $this->buildCategoryHierarchy($categories);
    }

    /**
     * Build hierarchical category structure
     *
     * @param array $categories
     * @return array
     */
    protected function buildCategoryHierarchy(array $categories): array
    {
        // Create lookup array with references
        $categoryMap = [];
        foreach ($categories as &$category) {
            $category['childCategories'] = [];
            $categoryMap[$category['uid']] = &$category;
        }
        unset($category); // Break reference

        // Build hierarchy
        $tree = [];
        foreach ($categories as &$category) {
            $parentUid = (int)($category['parent'] ?? 0);

            if ($parentUid > 0 && isset($categoryMap[$parentUid])) {
                // Add as child to parent (by reference)
                $categoryMap[$parentUid]['childCategories'][] = &$category;
            } else {
                // Root level category
                $tree[] = &$category;
            }
        }
        unset($category); // Break reference

        return $tree;
    }

    /**
     * Annotate every tree node with its match count (leaf counts + deduplicated parent rollups).
     *
     * A leaf's count is the number of its surviving records. A parent's count is the size of the
     * DEDUPLICATED union of its own and all descendants' surviving record keys — a record tagged to
     * multiple children is counted once. A post-order traversal merges keysets upward; the array
     * union operator (+) deduplicates by "{table}_{uid}" key automatically.
     *
     * Returns a fresh tree (no references) with an added integer 'count' on every node.
     *
     * @param array $tree Hierarchical nodes as produced by getCategoryTreeForFilter()
     * @param array<int,array<string,bool>> $survivingCategoryKeys categoryUid => set of surviving keys
     * @return array Tree with a 'count' key added to every node
     */
    public function attachCountsToTree(array $tree, array $survivingCategoryKeys): array
    {
        $annotated = [];
        foreach ($tree as $node) {
            [$annotated[]] = $this->annotateNodeCount($node, $survivingCategoryKeys);
        }

        return $annotated;
    }

    /**
     * Recursively annotate a single node and return it together with its merged surviving keyset.
     *
     * @param array $node
     * @param array<int,array<string,bool>> $survivingCategoryKeys
     * @return array{0: array, 1: array<string,bool>} [annotated node, merged surviving keyset]
     */
    private function annotateNodeCount(array $node, array $survivingCategoryKeys): array
    {
        $uid = (int)($node['uid'] ?? 0);
        $merged = $survivingCategoryKeys[$uid] ?? [];

        if (!empty($node['childCategories']) && is_array($node['childCategories'])) {
            $annotatedChildren = [];
            foreach ($node['childCategories'] as $child) {
                [$annotatedChild, $childKeys] = $this->annotateNodeCount($child, $survivingCategoryKeys);
                $annotatedChildren[] = $annotatedChild;
                // Array union deduplicates by key: a record under multiple children counts once.
                $merged += $childKeys;
            }
            $node['childCategories'] = $annotatedChildren;
        }

        $node['count'] = count($merged);

        return [$node, $merged];
    }

    /**
     * Flatten an annotated tree into a categoryUid => count map covering EVERY node.
     *
     * Unlike the flat counts map returned by fetchFilteredRecords() (which omits empty sets, so
     * zero-count categories are absent), this walks the tree annotated by attachCountsToTree() and
     * therefore keeps every node — including the ones with count 0. It is the single source of truth
     * the AJAX response ships to the frontend so JavaScript can patch each badge on data-category-uid.
     *
     * @param array $tree Tree annotated by attachCountsToTree() (each node carries an int 'count')
     * @return array<int,int> categoryUid => match count (all nodes, zeros included)
     */
    public function flattenTreeCounts(array $tree): array
    {
        $map = [];
        foreach ($tree as $node) {
            $this->collectNodeCounts($node, $map);
        }

        return $map;
    }

    /**
     * Recursively collect a node's count (and its descendants') into the flat map.
     *
     * @param array $node
     * @param array<int,int> $map Accumulator, mutated in place
     */
    private function collectNodeCounts(array $node, array &$map): void
    {
        $uid = (int)($node['uid'] ?? 0);
        if ($uid > 0) {
            $map[$uid] = (int)($node['count'] ?? 0);
        }

        if (!empty($node['childCategories']) && is_array($node['childCategories'])) {
            foreach ($node['childCategories'] as $child) {
                $this->collectNodeCounts($child, $map);
            }
        }
    }

    /**
     * Fetch multiple category records in a single IN() query.
     *
     * PERFORMANCE: Replaces the previous loop of getCategoryRecord() calls (N queries)
     * with one query regardless of how many UIDs are requested.
     *
     * @param array<int> $categoryUids
     * @return array
     * @throws Exception
     */
    protected function getCategoryRecordsBulk(array $categoryUids): array
    {
        if (empty($categoryUids)) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category');

        return $queryBuilder
            ->select('*')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($categoryUids, ArrayParameterType::INTEGER)
                )
            )
            ->orderBy('title', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
