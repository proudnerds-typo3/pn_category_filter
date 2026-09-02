<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Event;

/**
 * AfterRecordsFetchedEvent
 *
 * Dispatched after records have been fetched and before sorting/limiting
 * Allows modifications to the record list, e.g., filtering based on parent relations
 *
 * @event
 */
final class AfterRecordsFetchedEvent
{
    /**
     * @param array $records Fetched records (keyed by tableName_uid)
     * @param array $categoryUids Category UIDs used for filtering
     * @param array $tables Table names queried
     * @param array $recordPids PIDs used for filtering
     * @param array $settings Plugin settings from FlexForm/TypoScript
     */
    public function __construct(
        private array $records,
        private readonly array $categoryUids,
        private readonly array $tables,
        private readonly array $recordPids,
        private readonly array $settings = []
    ) {}

    /**
     * @return array
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @param array $records
     */
    public function setRecords(array $records): void
    {
        $this->records = $records;
    }

    /**
     * @return array
     */
    public function getCategoryUids(): array
    {
        return $this->categoryUids;
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return array
     */
    public function getRecordPids(): array
    {
        return $this->recordPids;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }
}
