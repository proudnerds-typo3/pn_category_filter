<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hydrates pages-table records with content from related tt_content rows.
 *
 * Sets two virtual fields on each pages record (when configured):
 * - record.teaser          → bodytext (or other field) of the first tt_content row
 *                            on the configured teaser colPos, for display purposes
 * - record._pageContent    → concatenated text of configured search fields/colPos,
 *                            for use in SearchService::filterRecordsBySearch
 *
 * One batched query for all pages in the result set — no N+1.
 */
final class PageContentHydrationService
{
    public function __construct(
        private readonly Context $context,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array $records  Records keyed by tableName_uid (as produced by CategoryFilterService)
     * @param array $config   Effective pageContent settings (already merged FlexForm + TypoScript)
     * @return array          Records with teaser / _pageContent populated where applicable
     */
    public function hydrate(array $records, array $config): array
    {
        $pageUids = [];
        foreach ($records as $record) {
            if (($record['_tableName'] ?? '') === 'pages' && !empty($record['uid'])) {
                $pageUids[(int)$record['uid']] = true;
            }
        }

        if ($pageUids === []) {
            return $records;
        }

        $teaserConfig = $this->normaliseSection($config['teaser'] ?? [], ['bodytext']);
        $searchConfig = $this->normaliseSection($config['search'] ?? [], ['header', 'bodytext']);

        $allColPositions = array_values(array_unique(array_merge(
            $teaserConfig['colPos'],
            $searchConfig['colPos'],
        )));
        $allCTypes = array_values(array_unique(array_merge(
            $teaserConfig['cTypes'],
            $searchConfig['cTypes'],
        )));
        $allFields = array_values(array_unique(array_merge(
            $teaserConfig['fields'],
            $searchConfig['fields'],
        )));

        if ($allFields === []) {
            return $records;
        }

        $rows = $this->fetchContentRows(array_keys($pageUids), $allColPositions, $allCTypes, $allFields);

        [$teaserByPid, $searchByPid] = $this->buildIndexes($rows, $teaserConfig, $searchConfig);

        foreach ($records as $key => $record) {
            if (($record['_tableName'] ?? '') !== 'pages') {
                continue;
            }
            $uid = (int)$record['uid'];

            if (empty($record['teaser']) && isset($teaserByPid[$uid])) {
                $records[$key]['teaser'] = $teaserByPid[$uid];
            }
            if (isset($searchByPid[$uid])) {
                $records[$key]['_pageContent'] = $searchByPid[$uid];
            }
        }

        return $records;
    }

    /**
     * @return array{colPos: array<int>, fields: array<string>, cTypes: array<string>}
     */
    private function normaliseSection(array $section, array $defaultFields): array
    {
        return [
            'colPos' => $this->intList($section['colPos'] ?? ''),
            'fields' => $this->validateColumns(
                'tt_content',
                $this->stringList($section['fields'] ?? '', $defaultFields)
            ),
            'cTypes' => $this->stringList($section['cTypes'] ?? '', []),
        ];
    }

    /**
     * @param array<int> $pageUids
     * @param array<int> $colPositions  Empty array = no colPos restriction
     * @param array<string> $cTypes     Empty array = no CType restriction
     * @param array<string> $fields     tt_content columns to SELECT
     * @return array<array<string, mixed>>
     */
    private function fetchContentRows(array $pageUids, array $colPositions, array $cTypes, array $fields): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $qb->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
            ->add(GeneralUtility::makeInstance(StartTimeRestriction::class))
            ->add(GeneralUtility::makeInstance(EndTimeRestriction::class));

        $select = array_values(array_unique(array_merge(['pid', 'colPos', 'CType', 'sorting'], $fields)));

        $qb->select(...$select)
            ->from('tt_content')
            ->where(
                $qb->expr()->in('pid', $qb->createNamedParameter($pageUids, ArrayParameterType::INTEGER)),
                $qb->expr()->eq(
                    'sys_language_uid',
                    $qb->createNamedParameter($this->getCurrentLanguageUid(), ParameterType::INTEGER)
                )
            )
            ->orderBy('pid')->addOrderBy('colPos')->addOrderBy('sorting');

        if ($colPositions !== []) {
            $qb->andWhere($qb->expr()->in(
                'colPos',
                $qb->createNamedParameter($colPositions, ArrayParameterType::INTEGER)
            ));
        }
        if ($cTypes !== []) {
            $qb->andWhere($qb->expr()->in(
                'CType',
                $qb->createNamedParameter($cTypes, ArrayParameterType::STRING)
            ));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<array<string, mixed>> $rows
     * @param array{colPos: array<int>, fields: array<string>, cTypes: array<string>} $teaserConfig
     * @param array{colPos: array<int>, fields: array<string>, cTypes: array<string>} $searchConfig
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function buildIndexes(array $rows, array $teaserConfig, array $searchConfig): array
    {
        $teaserByPid = [];
        $searchByPid = [];

        foreach ($rows as $row) {
            $pid = (int)($row['pid'] ?? 0);
            $colPos = (int)($row['colPos'] ?? 0);
            $cType = (string)($row['CType'] ?? '');

            if (
                !isset($teaserByPid[$pid])
                && $this->matchesFilter($colPos, $cType, $teaserConfig)
            ) {
                $teaser = $this->firstNonEmpty($row, $teaserConfig['fields']);
                if ($teaser !== '') {
                    $teaserByPid[$pid] = $teaser;
                }
            }

            if ($this->matchesFilter($colPos, $cType, $searchConfig)) {
                $chunk = $this->concatenateFields($row, $searchConfig['fields']);
                if ($chunk !== '') {
                    $searchByPid[$pid] = isset($searchByPid[$pid])
                        ? $searchByPid[$pid] . ' ' . $chunk
                        : $chunk;
                }
            }
        }

        return [$teaserByPid, $searchByPid];
    }

    /**
     * @param array{colPos: array<int>, fields: array<string>, cTypes: array<string>} $config
     */
    private function matchesFilter(int $colPos, string $cType, array $config): bool
    {
        if ($config['fields'] === []) {
            return false;
        }
        if ($config['colPos'] !== [] && !in_array($colPos, $config['colPos'], true)) {
            return false;
        }
        if ($config['cTypes'] !== [] && !in_array($cType, $config['cTypes'], true)) {
            return false;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string> $fields
     */
    private function firstNonEmpty(array $row, array $fields): string
    {
        foreach ($fields as $field) {
            $value = $this->cleanText((string)($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string> $fields
     */
    private function concatenateFields(array $row, array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $value = $this->cleanText((string)($row[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        return implode(' ', $parts);
    }

    private function cleanText(string $value): string
    {
        $stripped = strip_tags($value);
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;
        return trim($collapsed);
    }

    /**
     * @return array<int>
     */
    private function intList(string $raw): array
    {
        return array_values(array_unique(array_map(
            'intval',
            GeneralUtility::trimExplode(',', $raw, true)
        )));
    }

    /**
     * @param array<string> $default
     * @return array<string>
     */
    private function stringList(string $raw, array $default): array
    {
        $list = GeneralUtility::trimExplode(',', $raw, true);
        return $list === [] ? $default : array_values(array_unique($list));
    }

    /**
     * @param array<string> $fields
     * @return array<string>
     */
    private function validateColumns(string $table, array $fields): array
    {
        $tca = $GLOBALS['TCA'][$table]['columns'] ?? [];
        if ($tca === []) {
            return $fields;
        }

        $valid = [];
        foreach ($fields as $field) {
            if (isset($tca[$field])) {
                $valid[] = $field;
            } else {
                $this->logger->warning('PageContentHydrationService: configured field does not exist in TCA, skipping', [
                    'table' => $table,
                    'field' => $field,
                ]);
            }
        }
        return $valid;
    }

    private function getCurrentLanguageUid(): int
    {
        try {
            /** @var LanguageAspect $aspect */
            $aspect = $this->context->getAspect('language');
            return $aspect->getId();
        } catch (\Throwable) {
            return 0;
        }
    }
}
