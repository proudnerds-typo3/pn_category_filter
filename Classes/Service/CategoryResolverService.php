<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Attaches the categories of the displayed records as a list of ['uid', 'title'] rows,
 * as configured in settings.categoryResolvers (one query per resolver).
 */
final class CategoryResolverService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<int,array<string,mixed>> $records Records of the current page
     * @param array<array-key,mixed> $resolvers settings.categoryResolvers
     * @return array<int,array<string,mixed>> Records with the configured target keys set
     * @throws Exception
     */
    public function resolve(array $records, array $resolvers): array
    {
        foreach ($resolvers as $name => $resolver) {
            $config = is_array($resolver) ? $this->normalizeConfig($resolver) : null;
            if ($config === null) {
                $this->logger->warning('CategoryResolverService: invalid resolver configuration', ['resolver' => $name]);
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

            $categoriesByRecord = $this->fetchCategories($config['table'], $config['field'], $recordUids);

            foreach ($records as $key => $record) {
                if (($record['_tableName'] ?? '') === $config['table']) {
                    $records[$key][$config['as']] = $categoriesByRecord[(int)($record['uid'] ?? 0)] ?? [];
                }
            }
        }

        return $records;
    }

    /**
     * @param array<array-key,mixed> $resolver
     * @return array{table: string, field: string, as: string}|null
     */
    private function normalizeConfig(array $resolver): ?array
    {
        $table = trim((string)($resolver['table'] ?? ''));
        $field = trim((string)($resolver['field'] ?? 'categories'));
        $as = trim((string)($resolver['as'] ?? '_categories'));

        if ($table === '' || $as === '' || !isset($GLOBALS['TCA'][$table]['columns'][$field])) {
            return null;
        }

        return ['table' => $table, 'field' => $field, 'as' => $as];
    }

    /**
     * @param array<int> $recordUids
     * @return array<int,array<int,array{uid: int, title: string}>> record uid => categories in relation order
     * @throws Exception
     */
    private function fetchCategories(string $table, string $field, array $recordUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category');

        $rows = $queryBuilder
            ->select('sys_category_record_mm.uid_foreign', 'sys_category.uid', 'sys_category.title')
            ->from('sys_category')
            ->join(
                'sys_category',
                'sys_category_record_mm',
                'sys_category_record_mm',
                $queryBuilder->expr()->eq(
                    'sys_category_record_mm.uid_local',
                    $queryBuilder->quoteIdentifier('sys_category.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->in(
                    'sys_category_record_mm.uid_foreign',
                    $queryBuilder->createNamedParameter($recordUids, ArrayParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq('sys_category_record_mm.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('sys_category_record_mm.fieldname', $queryBuilder->createNamedParameter($field))
            )
            ->orderBy('sys_category_record_mm.uid_foreign')
            ->addOrderBy('sys_category_record_mm.sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        $categoriesByRecord = [];
        foreach ($rows as $row) {
            $categoriesByRecord[(int)$row['uid_foreign']][] = [
                'uid' => (int)$row['uid'],
                'title' => (string)$row['title'],
            ];
        }

        return $categoriesByRecord;
    }
}
