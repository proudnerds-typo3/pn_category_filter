<?php

declare(strict_types=1);

use ProudNerds\PnCategoryFilter\Controller\CategoryFilterController;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::configurePlugin(
    'PnCategoryFilter',
    'CategoryFilterList',
    [
        CategoryFilterController::class => 'list, ajaxLoadResults',
    ],
    [
        CategoryFilterController::class => 'ajaxLoadResults',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

// Register cache for Solr synonyms (used by SynonymService)
// Uses SimpleFileBackend as a safe default; can be overridden in system/settings.php
// with an APCu or Redis backend for better performance.
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pn_category_filter'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pn_category_filter'] = [
        'backend' => SimpleFileBackend::class,
        'options' => [],
        'groups' => ['all'],
    ];
}
