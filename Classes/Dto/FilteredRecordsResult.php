<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Dto;

/**
 * FilteredRecordsResult
 *
 * Immutable carrier for the output of CategoryFilterService::fetchFilteredRecords():
 * the filtered records plus the per-category match counts, both produced in a single
 * pass so counts never require re-running the fetch pipeline.
 */
final readonly class FilteredRecordsResult
{
    /**
     * @param array<int,array<string,mixed>> $records Filtered (and sorted/limited) records
     * @param array<int,int> $counts categoryUid => number of matching records (leaf-level, search-aware, complete set)
     * @param array<int,array<string,bool>> $survivingCategoryKeys categoryUid => set of surviving "{table}_{uid}"
     *        keys; source of truth for deduplicated parent rollups (see CategoryFilterService::attachCountsToTree())
     */
    public function __construct(
        public array $records,
        public array $counts,
        public array $survivingCategoryKeys,
    ) {}
}
