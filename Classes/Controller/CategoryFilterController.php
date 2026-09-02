<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Controller;

use Doctrine\DBAL\Exception;
use ProudNerds\PnCategoryFilter\Service\CategoryFilterService;
use ProudNerds\PnCategoryFilter\Service\SearchService;
use ProudNerds\PnCategoryFilter\Utility\CategoryUtility;
use ProudNerds\PnCategoryFilter\Utility\Typo3Utility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentValueException;

/**
 * CategoryFilterController
 *
 * Displays records filtered by categories from multiple tables
 */
class CategoryFilterController extends ActionController
{
    /**
     * SECURITY: Blacklist of sensitive system tables that must never be queryable
     *
     * These tables contain security-critical data and should be excluded even if
     * they exist in TCA or are configured in TSconfig.
     */
    private const array BLACKLISTED_TABLES = [
        'be_users',
        'be_sessions',
        'be_groups',

        'fe_sessions',

        'sys_log',
        'sys_history',
        'sys_refindex',
        'sys_registry',
        'sys_be_shortcuts',

        'cache_hash',
        'cache_imagesizes',
        'cache_pages',
        'cache_pagesection',
        'cache_rootline',
        'cache_treelist',

        'tx_extensionmanager_domain_model_extension',
    ];

    public function __construct(
        private readonly CategoryFilterService $categoryFilterService
    ) {}

    public function initializeView(): void
    {
        $this->view->assignMultiple([
            'site' => $this->request->getAttribute('site'),
        ]);
    }

    /**
     * List action - main entry point
     *
     * @return ResponseInterface
     * @throws Exception
     */
    public function listAction(): ResponseInterface
    {

        $this->processFilteredRecords();

        $this->view->assignMultiple([
            // ttContentUid is used to be able to get the Flexform settings on Ajax actions
            'ttContentUid' => $this->request->getAttribute('currentContentObject')->data['uid'],
        ]);

        return $this->htmlResponse();
    }

    /**
     * AJAX Load Results action - same as list action but for AJAX requests
     *
     * @return ResponseInterface
     * @throws InvalidArgumentValueException
     * @throws Exception
     */
    public function ajaxLoadResultsAction(): ResponseInterface
    {
        // SECURITY: Only accept POST parameters for AJAX requests to prevent parameter pollution
        // JavaScript sends filter data via POST to avoid cHash issues
        $postParams = $this->request->getParsedBody() ?? [];

        // Get URL query params separately (for base action/controller routing only)
        $queryParams = $this->request->getQueryParams();

        // Extract ttContentUid from plugin namespace (TYPO3 Extbase convention)
        // Prefer POST (our JS sends it there), fallback to GET for direct URL access
        $pluginParamsPost = $postParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];
        $pluginParamsQuery = $queryParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

        $ttContentUid = (int)($pluginParamsPost['ttContentUid'] ?? $pluginParamsQuery['ttContentUid'] ?? 0);

        if ($ttContentUid > 0) {
            // SECURITY: Validate ttContentUid belongs to current page
            $this->validateContentElementAccess($ttContentUid);
            $this->loadFlexFormSettings($ttContentUid);

            // Override sorting settings from POST if provided (frontend sorting)
            if (isset($pluginParamsPost['sorting']) && in_array($pluginParamsPost['sorting'], ['asc', 'desc', 'none'], true)) {
                $this->settings['sorting'] = $pluginParamsPost['sorting'];
            }
            if (!empty($pluginParamsPost['sortField'])) {
                // Validate sortField contains only allowed characters (alphanumeric, underscore, comma)
                if (preg_match('/^[a-zA-Z0-9_,]+$/', $pluginParamsPost['sortField'])) {
                    $this->settings['sortField'] = $pluginParamsPost['sortField'];
                }
            }
        } else {
            throw new InvalidArgumentValueException(
                'The ttContentUid parameter is missing or invalid. Please provide a valid content element UID in the Ajax URL.',
                1769097215
            );
        }

        $this->processFilteredRecords();

        // Generate URL for pushState with selected categories (if enabled)
        $pageId = $this->request->getAttribute('routing')->getPageId();
        $selectedCategories = $this->getCategoryListForFiltering();
        $pushStateBaseUrl = '';

        $activeSortingState = $this->getActiveSortingStateFromRequest();
        $searchTerm = $this->getSearchTerm();

        // Only generate pushState URL if the feature is enabled
        if ((bool)($this->settings['enablePushState'] ?? true)) {
            $arguments = [
                'tx_pncategoryfilter_categoryfilterlist' => [],
            ];

            // Only add selectedCategories if user actively selected them (not FlexForm defaults)
            if ($this->request->hasArgument('selectedCategories')) {
                $userSelectedCategories = $this->request->getArgument('selectedCategories');
                if (is_array($userSelectedCategories) && !empty($userSelectedCategories)) {
                    // Sort categories to ensure consistent URL parameter order
                    $sortedCategories = array_map('intval', $userSelectedCategories);
                    sort($sortedCategories, SORT_NUMERIC);
                    $arguments['tx_pncategoryfilter_categoryfilterlist']['selectedCategories'] = $sortedCategories;
                }
            }

            // Only add sorting parameters if user actively changed them (not FlexForm defaults)
            // Check if sorting parameters were sent in the request (POST or GET)
            $userHasSorting = false;
            if ($this->request->hasArgument('sorting') || $this->request->hasArgument('sortField')) {
                $userHasSorting = true;
            } else {
                // Also check raw POST/GET parameters
                $postParams = $this->request->getParsedBody() ?? [];
                $queryParams = $this->request->getQueryParams();
                $pluginParamsPost = $postParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];
                $pluginParamsQuery = $queryParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

                if (isset($pluginParamsPost['sorting']) || isset($pluginParamsPost['sortField']) ||
                    isset($pluginParamsQuery['sorting']) || isset($pluginParamsQuery['sortField'])) {
                    $userHasSorting = true;
                }
            }

            if ($userHasSorting) {
                if (!empty($activeSortingState['sorting']) && $activeSortingState['sorting'] !== 'none') {
                    $arguments['tx_pncategoryfilter_categoryfilterlist']['sorting'] = $activeSortingState['sorting'];
                }
                if (!empty($activeSortingState['sortField'])) {
                    $arguments['tx_pncategoryfilter_categoryfilterlist']['sortField'] = $activeSortingState['sortField'];
                }
            }

            // Add search term to URL if present
            if (!empty($searchTerm)) {
                $arguments['tx_pncategoryfilter_categoryfilterlist']['search'] = $searchTerm;
            }

            // Generate URL with parameters or clean URL if nothing is set
            $hasParameters = !empty($arguments['tx_pncategoryfilter_categoryfilterlist']);

            if ($hasParameters) {
                $pushStateBaseUrl = $this->uriBuilder
                    ->reset()
                    ->setTargetPageUid($pageId)
                    ->setCreateAbsoluteUri(false)
                    ->setArguments($arguments)
                    ->build();
            } else {
                $pushStateBaseUrl = $this->uriBuilder
                    ->reset()
                    ->setTargetPageUid($pageId)
                    ->setCreateAbsoluteUri(false)
                    ->build();
            }
        }

        $this->view->assignMultiple([
            'ttContentUid' => $ttContentUid,
            'pushStateBaseUrl' => $pushStateBaseUrl,
        ]);

        return $this->htmlResponse();
    }

    /**
     * Process filtered records - shared logic for list and AJAX actions
     *
     * @throws Exception
     */
    protected function processFilteredRecords(): void
    {
        // Get categories for filtering records (uses selected categories if available)
        $filterCategories = $this->getCategoryListForFiltering();

        // Get categories for displaying the tree (always from FlexForm)
        $treeCategories = $this->getCategoryListFromFlexForm();

        $tables = $this->getTableList();
        $currentPage = $this->getCurrentPageNumberFromRequest();

        $records = [];
        $categoryTree = [];
        $categoryCounts = [];
        $categoryCountsMap = [];

        // Get sorting with URL override priority
        $activeSortingState = $this->getActiveSortingStateFromRequest();
        $sorting = $activeSortingState['sorting'];
        $sortField = $activeSortingState['sortField'];
        $userHasSorted = $activeSortingState['userHasSorted'];

        // Get search term from request
        $searchTerm = $this->getSearchTerm();

        // Resolve site + language from request (always available in a frontend context)
        $site = $this->request->getAttribute('site');
        $language = $this->request->getAttribute('language');
        $languageUid = $language !== null ? $language->getLanguageId() : 0;

        // Solr synonyms are only used when both the search form and the synonym setting are enabled.
        // If showSearchForm is off there is no search input, so synonyms are never needed.
        $showSearchForm = (bool)($this->settings['showSearchForm'] ?? false);
        $useSolrSynonyms = (bool)($this->settings['useSolrSynonyms'] ?? false);
        $solrSynonymsActive = $showSearchForm && $useSolrSynonyms;

        // Facet AND-logic would intersect all configured categories on the unfiltered view
        // (empty result). Fall back to expand (union) until the visitor selects a filter.
        $filterLogic = (string)($this->settings['filterLogic'] ?? CategoryFilterService::COMBINE_EXPAND);
        if ($filterLogic === CategoryFilterService::COMBINE_FACET && !$this->hasActiveCategorySelection()) {
            $filterLogic = CategoryFilterService::COMBINE_EXPAND;
        }

        if (!empty($filterCategories) && !empty($tables)) {
            $filteredResult = $this->categoryFilterService->fetchFilteredRecords(
                $filterCategories,
                $tables,
                $this->getPidList($this->settings['recordPids'] ?? ''),
                (bool)($this->settings['includeSubcategories'] ?? true),
                (int)($this->settings['limit'] ?? 0),
                $sorting,
                $sortField,
                $this->settings,
                $searchTerm,
                // When the user actively sorts via the frontend, boost is disabled.
                // Applying boost on top of a user-chosen sort order would be confusing:
                // the user explicitly chose a different order and would expect that to be respected.
                // Boost only applies to the default/initial sort order as configured in the FlexForm.
                $userHasSorted ? '' : trim((string)($this->settings['boostFields'] ?? '')),
                $solrSynonymsActive,
                $site,
                $languageUid,
                $filterLogic,
                $this->getCategoryListFromFlexForm(),
            );

            $records = $filteredResult->records;
            $categoryCounts = $filteredResult->counts;

            // Get category tree for filter menu if enabled - always based on FlexForm categories
            if (!empty($this->settings['showFilterMenu']) && !empty($treeCategories)) {
                $categoryTree = $this->categoryFilterService->getCategoryTreeForFilter(
                    $treeCategories,
                    (bool)($this->settings['includeSubcategories'] ?? true),
                    (bool)($this->settings['showOnlyChildrenInFilter'] ?? false)
                );

                // Annotate each node with its match count (leaf counts + deduplicated parent rollups)
                $categoryTree = $this->categoryFilterService->attachCountsToTree(
                    $categoryTree,
                    $filteredResult->survivingCategoryKeys
                );

                // Full categoryUid => count map (every node, zeros included) for the AJAX response.
                // Built from the annotated tree — NOT from the flat $categoryCounts, which omits
                // zero-count categories. JavaScript patches badges on data-category-uid from this map.
                $categoryCountsMap = $this->categoryFilterService->flattenTreeCounts($categoryTree);
            }
        }

        $paginator = new ArrayPaginator(
            $records,
            $currentPage,
            (int)($this->settings['pagination']['itemsPerPage'] ?? 10)
        );
        $pagination = new SlidingWindowPagination(
            $paginator,
            (int)($this->settings['pagination']['maxLinks'] ?? 5)
        );

        // Update settings with actual used values for pagination arguments
        $this->settings['sorting'] = $sorting;
        $this->settings['sortField'] = $sortField;

        // Expose active sorting state to Fluid and pagination
        $this->view->assignMultiple([
            'records' => $paginator->getPaginatedItems(),
            'paginator' => $paginator,
            'pagination' => $pagination,
            // categoryTree is used to render the filter menu
            'categoryTree' => $categoryTree,
            // categoryCounts (categoryUid => matches) feeds the result-count badges
            'categoryCounts' => $categoryCounts,
            // Full map incl. zeros (every tree node) + its JSON form for the AJAX response;
            // JavaScript reads the JSON to patch badges after a filter/search/reset/paginate swap.
            'categoryCountsMap' => $categoryCountsMap,
            'categoryCountsJson' => $categoryCountsMap === []
                ? ''
                : json_encode($categoryCountsMap, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP),
            'totalRecords' => count($records),
            'settings' => $this->settings,
            'selectedCategories' => $filterCategories,
            'activeSorting' => $sorting,
            'activeSortField' => $sortField,
            'activeSearchTerm' => $searchTerm,
        ]);

        // Base arguments for pagination links
        $paginationArguments = $this->buildPaginationArguments(
            $filterCategories,
            $this->getTtContentUidFromRequest(),
            $sorting,
            $sortField
        );

        $this->view->assign('paginationArguments', $paginationArguments);
    }

    /**
     * Get category UIDs for filtering - uses selected categories from request if available
     *
     * SECURITY: Validates that selected categories are within allowed FlexForm categories
     *
     * @return array
     * @throws Exception
     */
    protected function getCategoryListForFiltering(): array
    {
        // Get allowed categories from FlexForm (security boundary)
        $allowedCategories = $this->getCategoryListFromFlexForm();

        // First check if there are selected categories from the request (AJAX filter)
        if ($this->request->hasArgument('selectedCategories')) {
            $selectedCategories = $this->request->getArgument('selectedCategories');
            if (is_array($selectedCategories) && !empty($selectedCategories)) {
                $selectedCategories = array_map('intval', $selectedCategories);

                // SECURITY: Validate selected categories are within allowed tree
                if (!empty($allowedCategories)) {
                    $selectedCategories = $this->validateSelectedCategories($selectedCategories, $allowedCategories);
                }

                return $selectedCategories;
            }
        }

        // Fall back to FlexForm categories (default selection)
        return $allowedCategories;
    }

    /**
     * Determine whether the visitor has an active category filter selection.
     *
     * Mirrors the fallback logic in getCategoryListForFiltering(): a selection is only
     * "active" when the request carries a non-empty selectedCategories array. On the
     * initial (unfiltered) page load — or after the visitor clears all filters — this
     * returns false and the controller uses the FlexForm default categories.
     *
     * @return bool
     */
    protected function hasActiveCategorySelection(): bool
    {
        if (!$this->request->hasArgument('selectedCategories')) {
            return false;
        }

        $selectedCategories = $this->request->getArgument('selectedCategories');

        return is_array($selectedCategories) && !empty($selectedCategories);
    }

    /**
     * Validate that selected categories are within allowed category tree
     *
     * SECURITY: Prevents users from accessing categories outside the FlexForm configuration
     *
     * @param array $selectedCategories User-selected category UIDs
     * @param array $allowedCategories FlexForm-configured category UIDs
     * @return array Validated category UIDs
     * @throws Exception
     */
    protected function validateSelectedCategories(array $selectedCategories, array $allowedCategories): array
    {
        if (empty($allowedCategories)) {
            return $selectedCategories;
        }

        // Expand allowed categories to include all subcategories if enabled
        $includeSubcategories = (bool)($this->settings['includeSubcategories'] ?? true);
        if ($includeSubcategories) {
            $allowedCategoriesExpanded = CategoryUtility::expandCategoryList($allowedCategories);
        } else {
            $allowedCategoriesExpanded = $allowedCategories;
        }

        // Only keep selected categories that are within the allowed tree
        return array_values(array_intersect($selectedCategories, $allowedCategoriesExpanded));
    }

    /**
     * Get category UIDs from FlexForm only (for category tree display)
     *
     * @return array
     */
    protected function getCategoryListFromFlexForm(): array
    {
        $categories = $this->settings['categories'] ?? '';

        if (empty($categories)) {
            return [];
        }

        if (is_string($categories)) {
            return GeneralUtility::intExplode(',', $categories, true);
        }

        return array_map('intval', (array)$categories);
    }

    /**
     * Get table names from FlexForm
     *
     * SECURITY: Validates table names against TCA and blacklist to prevent SQL injection
     * and unauthorized access to sensitive system tables
     *
     * @return array
     */
    protected function getTableList(): array
    {
        $tables = $this->settings['tables'] ?? '';

        if (empty($tables)) {
            return [];
        }

        if (is_string($tables)) {
            $tables = GeneralUtility::trimExplode(',', $tables, true);
        } else {
            $tables = array_filter((array)$tables);
        }

        // SECURITY: Only allow tables that:
        // 1. Exist in TCA (prevents SQL injection)
        // 2. Are NOT in the blacklist (prevents unauthorized access to sensitive data)
        return array_filter($tables, function (string $tableName) {
            return isset($GLOBALS['TCA'][$tableName])
                && !in_array($tableName, self::BLACKLISTED_TABLES, true);
        });
    }

    /**
     * Read current page number from request.
     *
     * Supports both a top-level "page" argument and the plugin argument
     * "tx_pncategoryfilter_categoryfilterlist[page]".
     *
     * SECURITY: Check POST first, then GET (don't merge to prevent parameter pollution)
     */
    protected function getCurrentPageNumberFromRequest(): int
    {
        // Extbase's hasArgument/getArgument handles plugin namespace automatically
        if ($this->request->hasArgument('page')) {
            return max(1, (int)$this->request->getArgument('page'));
        }

        // Manual fallback for direct parameter access (AJAX calls)
        $postParams = $this->request->getParsedBody() ?? [];
        $queryParams = $this->request->getQueryParams();

        $pluginParamsPost = $postParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];
        $pluginParamsQuery = $queryParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

        $page = (int)($pluginParamsPost['page'] ?? $pluginParamsQuery['page'] ?? 1);

        return max(1, $page);
    }

    /**
     * Extract ttContentUid (content element uid) from request.
     *
     * SECURITY: Check POST first, then GET (don't merge to prevent parameter pollution)
     *
     * @return int
     */
    protected function getTtContentUidFromRequest(): int
    {
        // For normal plugin requests this attribute exists
        $cObj = $this->request->getAttribute('currentContentObject');
        if ($cObj !== null && isset($cObj->data['uid'])) {
            return (int)$cObj->data['uid'];
        }

        // For Ajax calls: Extract from plugin namespace
        // Prefer POST (our JS sends via POST), fallback to GET for direct URL access
        $postParams = $this->request->getParsedBody() ?? [];
        $queryParams = $this->request->getQueryParams();

        $pluginParamsPost = $postParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];
        $pluginParamsQuery = $queryParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

        return (int)($pluginParamsPost['ttContentUid'] ?? $pluginParamsQuery['ttContentUid'] ?? 0);
    }

    /**
     * Get search term from request
     *
     * SECURITY: Check POST first, then GET (don't merge to prevent parameter pollution)
     * Returns trimmed search term, empty string if not present
     *
     * @return string
     */
    protected function getSearchTerm(): string
    {
        // Try Extbase argument first
        if ($this->request->hasArgument('search')) {
            return mb_substr(trim((string)$this->request->getArgument('search')), 0, SearchService::MAX_SEARCH_LENGTH, 'UTF-8');
        }

        // Manual fallback for direct parameter access (AJAX calls)
        $postParams = $this->request->getParsedBody() ?? [];
        $queryParams = $this->request->getQueryParams();

        $pluginParamsPost = $postParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];
        $pluginParamsQuery = $queryParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

        $searchTerm = $pluginParamsPost['search'] ?? $pluginParamsQuery['search'] ?? '';

        return mb_substr(trim((string)$searchTerm), 0, SearchService::MAX_SEARCH_LENGTH, 'UTF-8');
    }

    /**
     * Build pagination arguments
     *
     * @param array $selectedCategories
     * @param int $ttContentUid
     * @param string $sorting
     * @param string $sortField
     * @return array
     */
    protected function buildPaginationArguments(
        array $selectedCategories,
        int $ttContentUid = 0,
        string $sorting = 'desc',
        string $sortField = 'tstamp'
    ): array {
        $arguments = [
            'tx_pncategoryfilter_categoryfilterlist' => [],
        ];

        if ($this->request->hasArgument('selectedCategories')) {
            $userSelectedCategories = $this->request->getArgument('selectedCategories');
            if (is_array($userSelectedCategories) && !empty($userSelectedCategories)) {
                $arguments['tx_pncategoryfilter_categoryfilterlist']['selectedCategories'] = array_values(
                    array_map('intval', $userSelectedCategories)
                );
            }
        }

        if (!empty($sorting) && $sorting !== 'none') {
            $arguments['tx_pncategoryfilter_categoryfilterlist']['sorting'] = $sorting;
        }

        if (!empty($sortField)) {
            $arguments['tx_pncategoryfilter_categoryfilterlist']['sortField'] = $sortField;
        }

        // Add search term if present
        $searchTerm = $this->getSearchTerm();
        if (!empty($searchTerm)) {
            $arguments['tx_pncategoryfilter_categoryfilterlist']['search'] = $searchTerm;
        }

        return $arguments;
    }

    /**
     * Convert PID string to array
     *
     * @param string $pidString
     * @return array
     */
    protected function getPidList(string $pidString): array
    {
        if (empty($pidString)) {
            return [];
        }

        return GeneralUtility::intExplode(',', $pidString, true);
    }

    /**
     * Validate that the content element belongs to the current page
     *
     * SECURITY: Prevents unauthorized access to FlexForm settings from other pages
     *
     * @param int $ttContentUid
     * @throws InvalidArgumentValueException
     * @throws \Doctrine\DBAL\Exception
     */
    private function validateContentElementAccess(int $ttContentUid): void
    {
        $currentPageId = $this->request->getAttribute('routing')->getPageId();

        // Query the content element to check it belongs to current page
        $rows = Typo3Utility::getFieldFromTable(
            'tt_content',
            'pid',
            'uid',
            $ttContentUid,
        );

        $contentElementPid = (int)($rows[0]['pid'] ?? 0);

        if ($contentElementPid !== $currentPageId) {
            throw new InvalidArgumentValueException(
                'Access denied: The content element does not belong to the current page.',
                1769097216
            );
        }
    }

    /**
     * Load FlexForm settings into the settings array when not loaded,
     * e.g. via Ajax requests
     *
     * @param int $ttContentUid
     * @throws \Doctrine\DBAL\Exception
     */
    private function loadFlexFormSettings(int $ttContentUid): void
    {
        $rows = Typo3Utility::getFieldFromTable(
            'tt_content',
            'pi_flexform',
            'uid',
            $ttContentUid,
        );

        $piFlexform = $rows[0]['pi_flexform'] ?? null;
        if (!empty($piFlexform)) {
            $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
            $flexformSettings = $flexFormService->convertFlexFormContentToArray($piFlexform);
            $this->settings = array_merge($this->settings, $flexformSettings['settings'] ?? []);
        }
    }

    /**
     * Get active sorting state from request.
     *
     * Priority:
     * 1) POST (AJAX)
     * 2) GET (pagination/navigation)
     * 3) FlexForm / TypoScript settings
     *
     * @return array{sorting: string, sortField: string, userHasSorted: bool}
     */
    protected function getActiveSortingStateFromRequest(): array
    {
        $sorting = (string)($this->settings['sorting'] ?? 'desc');
        $sortField = (string)($this->settings['sortField'] ?? 'tstamp');
        $userHasSorted = false;

        // 1) POST (AJAX)
        $postParams = $this->request->getParsedBody() ?? [];
        $pluginParamsPost = $postParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

        if (
            isset($pluginParamsPost['sorting'])
            && in_array($pluginParamsPost['sorting'], ['asc', 'desc', 'none'], true)
        ) {
            $sorting = $pluginParamsPost['sorting'];
            $userHasSorted = true;
        }

        if (
            isset($pluginParamsPost['sortField'])
            && is_string($pluginParamsPost['sortField'])
            && preg_match('/^[a-zA-Z0-9_,]+$/', $pluginParamsPost['sortField'])
        ) {
            $sortField = $pluginParamsPost['sortField'];
            $userHasSorted = true;
        }

        // 2) GET (pagination/navigation) — only if POST did not provide a value
        $queryParams = $this->request->getQueryParams();
        $pluginParamsQuery = $queryParams['tx_pncategoryfilter_categoryfilterlist'] ?? [];

        if (
            !isset($pluginParamsPost['sorting'])
            && isset($pluginParamsQuery['sorting'])
            && in_array($pluginParamsQuery['sorting'], ['asc', 'desc', 'none'], true)
        ) {
            $sorting = $pluginParamsQuery['sorting'];
            $userHasSorted = true;
        }

        if (
            !isset($pluginParamsPost['sortField'])
            && isset($pluginParamsQuery['sortField'])
            && is_string($pluginParamsQuery['sortField'])
            && preg_match('/^[a-zA-Z0-9_,]+$/', $pluginParamsQuery['sortField'])
        ) {
            $sortField = $pluginParamsQuery['sortField'];
            $userHasSorted = true;
        }

        return [
            'sorting' => $sorting,
            'sortField' => $sortField,
            'userHasSorted' => $userHasSorted,
        ];
    }
}
