<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\UserFunc;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ItemsProcFunc to populate FlexForm fields dynamically
 */
class CategoryItemsProcFunc
{
    /**
     * Populate table items from TSconfig
     *
     * @param array $config
     * @throws \JsonException
     */
    public function getTableItems(array &$config): void
    {
        // Get page TSconfig
        $pageId = $this->getPageId($config);
        $pageTSconfig = BackendUtility::getPagesTSconfig($pageId);

        // Get available tables from TSconfig
        $availableTables = $pageTSconfig['tx_pncategoryfilter.']['availableTables'] ?? '';

        // Parse comma-separated list
        $tables = GeneralUtility::trimExplode(',', $availableTables, true);

        // Build items array
        $config['items'] = [];

        foreach ($tables as $tableName) {
            // Try to get a human-readable label
            $label = $this->getTableLabel($tableName);

            $config['items'][] = [
                'label' => $label,
                'value' => $tableName,
            ];
        }
    }

    /**
     * Get page ID from config context
     *
     * @param array $config
     * @return int
     */
    protected function getPageId(array $config): int
    {
        // Try to get page ID from various sources
        $pageId = 0;

        // From row uid (when editing content element)
        if (isset($config['flexParentDatabaseRow']['pid'])) {
            $pageId = (int)$config['flexParentDatabaseRow']['pid'];
        } elseif (isset($config['row']['pid'])) {
            $pageId = (int)$config['row']['pid'];
        }

        // Fallback: try to get from GET/POST
        if ($pageId === 0) {
            $queryParams = $GLOBALS['TYPO3_REQUEST']->getQueryParams();
            if (isset($queryParams['id'])) {
                $pageId = (int)$queryParams['id'];
            }
        }

        return $pageId;
    }

    /**
     * Get human-readable label for table
     *
     * @param string $tableName
     * @return string
     */
    protected function getTableLabel(string $tableName): string
    {
        // Try to get label from TCA
        if (isset($GLOBALS['TCA'][$tableName]['ctrl']['title'])) {
            $title = $GLOBALS['TCA'][$tableName]['ctrl']['title'];

            // If it's a translation key, resolve it
            if (str_starts_with($title, 'LLL:')) {
                $languageService = $GLOBALS['LANG'] ?? null;
                if ($languageService !== null) {
                    return $languageService->sL($title) ?: $tableName;
                }
            }

            return $title;
        }

        // Fallback: use table name with some formatting
        return ucfirst(str_replace(['_', 'tx_', 'domain_model_'], [' ', '', ''], $tableName));
    }
}
