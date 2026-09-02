<?php

namespace ProudNerds\PnCategoryFilter\Utility;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Typo3Utility
 *
 * Handy tools for extensions
 */
class Typo3Utility
{
    /**
     * Get field value for a table with an int and a string constraint
     *
     * @param string|null $table
     * @param string $field
     * @param string $constrainField1
     * @param int $constrainValue1
     * @param string $constrainField2
     * @param string $constrainValue2
     *
     * @return array
     * @throws \Exception|Exception
     */
    public static function getFieldFromTable(
        string|null $table = null,
        string $field = 'uid',
        string $constrainField1 = '',
        int $constrainValue1 = 0,
        string $constrainField2 = '',
        string $constrainValue2 = '',
    ): array {
        $content = [];
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);

        $constraints = [
            $queryBuilder->expr()->eq($constrainField1, $queryBuilder->createNamedParameter($constrainValue1, Connection::PARAM_INT)),
        ];

        if ($constrainField2 !== '' && $constrainValue2 !== '') {
            $constraints[] = $queryBuilder->expr()->eq($constrainField2, $queryBuilder->createNamedParameter($constrainValue2, Connection::PARAM_STR));
        }

        $statement = $queryBuilder
            ->select($field)
            ->from($table)
            ->where(...$constraints)
            ->executeQuery();

        while ($row = $statement->fetchAssociative()) {
            $content[] = $row;
        }
        return $content;
    }
}
