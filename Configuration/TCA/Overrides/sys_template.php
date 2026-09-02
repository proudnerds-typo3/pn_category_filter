<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

ExtensionManagementUtility::addStaticFile(
    'pn_category_filter',
    'Configuration/TypoScript/',
    'ProudNerds Category Filter'
);
