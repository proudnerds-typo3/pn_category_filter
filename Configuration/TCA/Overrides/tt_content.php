<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

$categoryFilterPluginSignature = ExtensionUtility::registerPlugin(
    'PnCategoryFilter',
    'CategoryFilterList',
    'Category Filter List View',
    'actions-filter',
    'plugins',
    'Show categorized records with filtering and sorting'
);

ExtensionManagementUtility::addToAllTCAtypes('tt_content', '--div--;Configuration,pi_flexform,', $categoryFilterPluginSignature, 'after:subheader');

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:pn_category_filter/Configuration/FlexForms/List.xml',
    $categoryFilterPluginSignature
);
