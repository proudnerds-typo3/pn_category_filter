<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use TYPO3\CMS\Core\LinkHandling\LinkService;

/**
 * Attaches a typolink parameter as _link to the displayed records: pages link to themselves,
 * other tables through a linkhandler identifier from settings.recordLinks (table => identifier).
 * The target page comes from the site's config.recordLinks, so the plugin needs no detail pid.
 */
final class RecordLinkService
{
    public function __construct(
        private readonly LinkService $linkService,
    ) {}

    /**
     * @param array<int,array<string,mixed>> $records Records of the current page
     * @param array<array-key,mixed> $recordLinks settings.recordLinks
     * @return array<int,array<string,mixed>> Records with _link set, empty when the table has no mapping
     */
    public function resolve(array $records, array $recordLinks): array
    {
        foreach ($records as $key => $record) {
            $records[$key]['_link'] = $this->buildLink(
                (string)($record['_tableName'] ?? ''),
                (int)($record['uid'] ?? 0),
                $recordLinks
            );
        }

        return $records;
    }

    /**
     * @param array<array-key,mixed> $recordLinks
     */
    private function buildLink(string $table, int $uid, array $recordLinks): string
    {
        if ($uid <= 0) {
            return '';
        }
        if ($table === 'pages') {
            return $this->linkService->asString(['type' => LinkService::TYPE_PAGE, 'pageuid' => $uid]);
        }

        $identifier = trim((string)($recordLinks[$table] ?? ''));
        if ($identifier === '') {
            return '';
        }

        return $this->linkService->asString([
            'type' => LinkService::TYPE_RECORD,
            'identifier' => $identifier,
            'uid' => $uid,
        ]);
    }
}
