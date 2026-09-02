<?php

// Required for TER uploads and Classic-mode (non-Composer) installations.
// Keep 'version' in sync with composer.json — `typo3/tailor set-version` updates both.
// Note: TER rejects this file when it declares strict_types or uses anything but $_EXTKEY.
$EM_CONF[$_EXTKEY] = [
    'title' => 'Category Filter',
    'description' => 'Category filtering and search for TYPO3 records — AJAX-powered, faceted, no Solr required, with optional Solr synonym support.',
    'category' => 'fe',
    'author' => 'Jacco van der Post, Emile Blume',
    'author_email' => 'extensions@proudnerds.com',
    'author_company' => 'ProudNerds',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.3.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'solr' => '',
        ],
    ],
];
