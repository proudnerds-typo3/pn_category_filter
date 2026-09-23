<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * AfterRecordsPaginatedEvent
 *
 * Dispatched with only the records of the current page, right before they are rendered.
 * Use it for enrichment that is only needed for display (images, related names); anything
 * that filters records or affects search and counts belongs in AfterRecordsFetchedEvent.
 *
 * @event
 */
final class AfterRecordsPaginatedEvent
{
    /**
     * @param array<int,array<string,mixed>> $records Records of the current page, in display order
     * @param array<string> $tables Table names queried
     * @param array<string,mixed> $settings Plugin settings from FlexForm/TypoScript
     * @param ServerRequestInterface $request Current request (site, language, arguments)
     */
    public function __construct(
        private array $records,
        private readonly array $tables,
        private readonly array $settings,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Replace the records of the current page; keep the number and order unchanged,
     * because the pagination has already been computed.
     *
     * @param array<int,array<string,mixed>> $records
     */
    public function setRecords(array $records): void
    {
        $this->records = $records;
    }

    /**
     * @return array<string>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
