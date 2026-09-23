<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves FAL fields of the displayed records to file uids, as configured in
 * settings.fileResolvers (one query per resolver). Missing files are skipped.
 */
final class FileResolverService
{
    public const string MODE_FIRST = 'first';
    public const string MODE_ALL = 'all';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<int,array<string,mixed>> $records Records of the current page
     * @param array<array-key,mixed> $resolvers settings.fileResolvers
     * @return array<int,array<string,mixed>> Records with the configured target keys set
     * @throws Exception
     */
    public function resolve(array $records, array $resolvers): array
    {
        foreach ($resolvers as $name => $resolver) {
            $config = is_array($resolver) ? $this->normalizeConfig($resolver) : null;
            if ($config === null) {
                $this->logger->warning('FileResolverService: invalid resolver configuration', ['resolver' => $name]);
                continue;
            }

            $recordUids = [];
            foreach ($records as $record) {
                if (($record['_tableName'] ?? '') === $config['table']) {
                    $recordUids[] = (int)($record['uid'] ?? 0);
                }
            }
            if ($recordUids === []) {
                continue;
            }

            $fileUidsByRecord = $this->fetchFileUids($config['table'], $config['field'], $recordUids, $config['fileTypes']);

            foreach ($records as $key => $record) {
                if (($record['_tableName'] ?? '') !== $config['table']) {
                    continue;
                }
                $fileUids = $fileUidsByRecord[(int)($record['uid'] ?? 0)] ?? [];
                $records[$key][$config['as']] = $config['mode'] === self::MODE_FIRST
                    ? ($fileUids[0] ?? 0)
                    : $fileUids;
            }
        }

        return $records;
    }

    /**
     * @param array<array-key,mixed> $resolver
     * @return array{table: string, field: string, as: string, mode: string, fileTypes: array<int>}|null
     */
    private function normalizeConfig(array $resolver): ?array
    {
        $table = trim((string)($resolver['table'] ?? ''));
        $field = trim((string)($resolver['field'] ?? ''));
        $as = trim((string)($resolver['as'] ?? ''));
        $mode = trim((string)($resolver['mode'] ?? self::MODE_FIRST));

        if ($table === '' || $as === '' || !isset($GLOBALS['TCA'][$table]['columns'][$field])) {
            return null;
        }
        if (!in_array($mode, [self::MODE_FIRST, self::MODE_ALL], true)) {
            return null;
        }

        $fileTypes = [];
        foreach (GeneralUtility::trimExplode(',', (string)($resolver['fileTypes'] ?? ''), true) as $typeName) {
            $type = $this->fileTypeFromName($typeName);
            if ($type === null) {
                return null;
            }
            $fileTypes[] = $type->value;
        }

        return ['table' => $table, 'field' => $field, 'as' => $as, 'mode' => $mode, 'fileTypes' => $fileTypes];
    }

    private function fileTypeFromName(string $name): ?FileType
    {
        foreach (FileType::cases() as $case) {
            if ($case !== FileType::UNKNOWN && strtolower($case->name) === strtolower($name)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @param array<int> $recordUids
     * @param array<int> $fileTypes FileType values to keep; empty keeps all
     * @return array<int,array<int>> record uid => file uids in reference order
     * @throws Exception
     */
    private function fetchFileUids(string $table, string $field, array $recordUids, array $fileTypes): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $queryBuilder
            ->select('sys_file_reference.uid_foreign', 'sys_file.uid')
            ->from('sys_file_reference')
            ->join(
                'sys_file_reference',
                'sys_file',
                'sys_file',
                $queryBuilder->expr()->eq('sys_file.uid', $queryBuilder->quoteIdentifier('sys_file_reference.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->in(
                    'sys_file_reference.uid_foreign',
                    $queryBuilder->createNamedParameter($recordUids, ArrayParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq('sys_file_reference.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('sys_file_reference.fieldname', $queryBuilder->createNamedParameter($field)),
                $queryBuilder->expr()->eq('sys_file.missing', 0)
            )
            ->orderBy('sys_file_reference.uid_foreign')
            ->addOrderBy('sys_file_reference.sorting_foreign');

        if ($fileTypes !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'sys_file.type',
                    $queryBuilder->createNamedParameter($fileTypes, ArrayParameterType::INTEGER)
                )
            );
        }

        $fileUidsByRecord = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $fileUidsByRecord[(int)$row['uid_foreign']][] = (int)$row['uid'];
        }

        return $fileUidsByRecord;
    }
}
