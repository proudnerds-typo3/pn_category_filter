<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Utility;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CategoryUtility
 *
 * Utility functions for working with sys_category records
 */
class CategoryUtility
{
    /**
     * Maximum tree depth to guard against circular references in category data.
     */
    private const int MAX_DEPTH = 10;

    /**
     * Get all direct child category UIDs for a given parent category.
     *
     * @param int $parentCategoryUid
     * @return array<int>
     * @throws Exception
     */
    public static function getChildCategoryUids(int $parentCategoryUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category');

        $children = $queryBuilder
            ->select('uid')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->eq(
                    'parent',
                    $queryBuilder->createNamedParameter($parentCategoryUid, ParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $child): int => (int)$child['uid'], $children);
    }

    /**
     * Recursively get all descendant category UIDs for a given category.
     *
     * PERFORMANCE: Delegates to BFS bulk loader — one IN() query per depth level
     * instead of one query per node.
     *
     * @param int $categoryUid
     * @return array<int>
     * @throws Exception
     */
    public static function getAllSubcategoryUids(int $categoryUid): array
    {
        return self::getDescendantUids([$categoryUid]);
    }

    /**
     * Expand a list of category UIDs to include all their descendants.
     *
     * PERFORMANCE: Single BFS pass over all root UIDs together —
     * one IN() query per depth level regardless of how many root UIDs are given.
     *
     * @param array<int> $categoryUids
     * @return array<int>
     * @throws Exception
     */
    public static function expandCategoryList(array $categoryUids): array
    {
        if (empty($categoryUids)) {
            return [];
        }

        $descendants = self::getDescendantUids($categoryUids);

        return array_values(array_unique(array_merge($categoryUids, $descendants)));
    }

    /**
     * BFS: fetch all descendants of the given UIDs using one IN() query per depth level.
     *
     * @param array<int> $rootUids  Starting UIDs (excluded from result)
     * @return array<int>           All descendant UIDs
     * @throws Exception
     */
    private static function getDescendantUids(array $rootUids): array
    {
        $allDescendants = [];
        $currentLevel  = $rootUids;
        $visited       = array_flip($rootUids); // O(1) lookup, prevents cycles

        for ($depth = 0; $depth < self::MAX_DEPTH && !empty($currentLevel); $depth++) {
            $children = self::getChildUidsBulk($currentLevel);

            if (empty($children)) {
                break;
            }

            $newChildren = [];
            foreach ($children as $uid) {
                if (!isset($visited[$uid])) {
                    $visited[$uid]    = true;
                    $newChildren[]    = $uid;
                    $allDescendants[] = $uid;
                }
            }

            $currentLevel = $newChildren;
        }

        return $allDescendants;
    }

    /**
     * Fetch all direct children for multiple parent UIDs in a single IN() query.
     *
     * @param array<int> $parentUids
     * @return array<int>
     * @throws Exception
     */
    private static function getChildUidsBulk(array $parentUids): array
    {
        if (empty($parentUids)) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category');

        $rows = $queryBuilder
            ->select('uid')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->in(
                    'parent',
                    $queryBuilder->createNamedParameter($parentUids, ArrayParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): int => (int)$row['uid'], $rows);
    }

    /**
     * Map each selected category UID to the facet it belongs to.
     *
     * A "facet" is a direct child of one of the configured root categories. The parent
     * chain of every selected UID is resolved up to the root using ONE IN() query per
     * tree depth level (BFS upward) — not one query per ancestor. Each selected UID is
     * then walked over the in-memory parent map to find the ancestor whose parent is a
     * root; that ancestor is the facet. A selected UID that is itself a configured root,
     * or whose facet cannot be resolved, maps to its own UID (defensive fallback — it
     * still constrains results as a singleton facet).
     *
     * @param array<int> $selectedUids
     * @param array<int> $rootUids
     * @return array<int,int> [selectedUid => facetUid]
     * @throws Exception
     */
    public static function mapCategoriesToFacets(array $selectedUids, array $rootUids): array
    {
        if (empty($selectedUids)) {
            return [];
        }

        $rootSet = array_flip(array_map('intval', $rootUids));
        $selectedUids = array_map('intval', $selectedUids);

        // Build a parent map for all ancestors: one IN() query per depth level.
        $parentMap = []; // uid => parentUid (0 when none)
        $toResolve = $selectedUids;

        for ($depth = 0; $depth < self::MAX_DEPTH && !empty($toResolve); $depth++) {
            $pending = [];
            foreach ($toResolve as $uid) {
                if (!isset($parentMap[$uid])) {
                    $pending[] = $uid;
                }
            }
            if (empty($pending)) {
                break;
            }

            $rows = self::getParentUidsBulk($pending);

            $nextLevel = [];
            foreach ($pending as $uid) {
                $parent = $rows[$uid] ?? 0;
                $parentMap[$uid] = $parent;

                // Keep climbing only while we haven't reached a root yet.
                if ($parent !== 0 && !isset($rootSet[$parent]) && !isset($parentMap[$parent])) {
                    $nextLevel[] = $parent;
                }
            }
            $toResolve = $nextLevel;
        }

        $map = [];
        foreach ($selectedUids as $selectedUid) {
            $map[$selectedUid] = self::resolveFacetUidFromMap($selectedUid, $rootSet, $parentMap);
        }

        return $map;
    }

    /**
     * Walk the in-memory parent map to find a category's facet (direct child of a root).
     *
     * @param int               $categoryUid
     * @param array<int,mixed>  $rootSet    Root UIDs as array keys (O(1) lookup)
     * @param array<int,int>    $parentMap  uid => parentUid
     * @return int Facet UID
     */
    private static function resolveFacetUidFromMap(int $categoryUid, array $rootSet, array $parentMap): int
    {
        // A configured root selected directly is its own facet (default "show all" state).
        if (isset($rootSet[$categoryUid])) {
            return $categoryUid;
        }

        $current = $categoryUid;
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $parent = $parentMap[$current] ?? 0;

            if ($parent === 0) {
                // Top-level / unresolved category: it is its own facet (defensive fallback).
                return $current;
            }

            if (isset($rootSet[$parent])) {
                // $current is a direct child of a root → it is the facet.
                return $current;
            }

            $current = $parent;
        }

        // Depth/cycle guard hit: fall back to the original category.
        return $categoryUid;
    }

    /**
     * Fetch the parent UID for multiple categories in a single IN() query.
     *
     * @param array<int> $uids
     * @return array<int,int> uid => parentUid (0 when none)
     * @throws Exception
     */
    private static function getParentUidsBulk(array $uids): array
    {
        if (empty($uids)) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_category');

        $rows = $queryBuilder
            ->select('uid', 'parent')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, ArrayParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['uid']] = (int)$row['parent'];
        }

        return $map;
    }
}
