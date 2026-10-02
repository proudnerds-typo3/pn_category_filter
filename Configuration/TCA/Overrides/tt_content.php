<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'PnCategoryFilter',
    'CategoryFilterList',
    'Category Filter List View',
    'actions-filter',
    'plugins',
    'Show categorized records with filtering and sorting',
    'FILE:EXT:pn_category_filter/Configuration/FlexForms/List.xml',
);
