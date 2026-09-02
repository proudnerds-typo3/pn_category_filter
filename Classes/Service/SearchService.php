<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

/**
 * SearchService
 *
 * Handles record searching with intelligent word boundary matching
 */
class SearchService
{
    /**
     * Maximum number of characters accepted as a search term.
     *
     * Rationale: the longest realistic query ("duurzame energie besparing subsidie aanvraag")
     * is ~45 characters. 50 is therefore a comfortable upper bound for legitimate input.
     * Enforcing this limit prevents ReDoS: a very long term combined with many synonym
     * alternatives would produce an extremely complex regex pattern that can stall the
     * PHP process for seconds.
     *
     * The same limit is applied earlier in CategoryFilterController::getSearchTerm()
     * so this acts as a second safety net when the service is called directly.
     */
    public const int MAX_SEARCH_LENGTH = 50;
    /**
     * Filter records by search term with word boundary detection.
     *
     * PERFORMANCE OPTIMIZED:
     * - Pre-compiles regex patterns once instead of per-record (O(m) instead of O(n×m))
     * - Uses array joining instead of string concatenation in loops
     * - Early exit on first non-matching word (short-circuit evaluation)
     * - Samples only first 10 records for field validation
     * - Single lowercase conversion per record
     *
     * Complexity: O(n×m×k) where:
     * - n = number of records
     * - m = number of search words
     * - k = average field content length (regex matching)
     *
     * Supports:
     * - Multi-word search with AND logic (all words must be present)
     * - Synonym expansion with OR logic per word (when $synonymMap is provided)
     * - Word boundary matching (prevents partial word matches)
     * - Hyphenated words (e.g., "basis-opleiding" matches "opleiding")
     * - Case-insensitive matching
     * - Stem/plural matching (e.g., "eerlijk" matches "eerlijke")
     *
     * Examples:
     * - "eerlijk" matches "eerlijke" but NOT "heerlijk"
     * - "opleiding" matches "basis-opleiding" but NOT "nopleiding"
     * - "fonds cultuur" matches records containing both words
     * - "cursus" (with synonym map) also matches records containing "opleiding", "training", etc.
     *
     * @param array                $records    Records to filter
     * @param string               $searchTerm Search term (can contain multiple words)
     * @param array                $settings   Plugin settings containing searchFields configuration
     * @param array<string,string[]> $synonymMap Optional synonym map from SynonymService::getSynonymsForSite()
     * @return array Filtered records
     */
    public function filterRecordsBySearch(array $records, string $searchTerm, array $settings, array $synonymMap = []): array
    {
        if (empty($searchTerm) || empty($records)) {
            return $records;
        }

        // SECURITY: cap length to prevent ReDoS — see MAX_SEARCH_LENGTH for rationale.
        // Truncated silently; the controller already enforces this limit upstream.
        $searchTerm = mb_substr($searchTerm, 0, self::MAX_SEARCH_LENGTH, 'UTF-8');

        // Check minimum character requirement
        $minChars = (int)($settings['searchMinChars'] ?? 3);
        if (mb_strlen($searchTerm, 'UTF-8') < $minChars) {
            // Search term too short, return all records
            return $records;
        }

        // Get search fields from settings (default: title,description)
        $searchFieldsConfig = $settings['searchFields'] ?? 'title,description';
        $searchFields = array_map('trim', explode(',', $searchFieldsConfig));

        // Validate search fields: check which fields exist across all records
        $availableFields = $this->getAvailableFieldsFromRecords($records);

        $missingFields = [];
        foreach ($searchFields as $fieldName) {
            if (!empty($fieldName) && !in_array($fieldName, $availableFields, true)) {
                $missingFields[] = $fieldName;
            }
        }

        if (!empty($missingFields)) {
            throw new \RuntimeException(
                sprintf(
                    'Search field(s) "%s" do not exist in the queried records. ' .
                    'Please check your FlexForm configuration in "Search Settings" → "Fieldnames to Search".',
                    implode(', ', $missingFields)
                ),
                1769612742
            );
        }

        // All fields validated, use them for searching
        $searchFieldsToUse = $searchFields;

        // Split search term into words for AND logic
        // "fonds cultuur" becomes ["fonds", "cultuur"]
        $searchWords = array_filter(
            array_map('trim', explode(' ', $searchTerm)),
            fn($word) => !empty($word)
        );

        // Normalize all search words for case-insensitive matching
        $searchWordsLower = array_map(
            fn($word) => mb_strtolower($word, 'UTF-8'),
            $searchWords
        );

        // PERFORMANCE OPTIMIZATION: Pre-compile regex patterns ONCE for all records.
        //
        // Synonym expansion uses OR logic per word group:
        //   "cursus" expands to (cursus|opleiding|training|workshop)
        //
        // AND logic across groups: ALL groups must match (each word / synonym-group).
        //
        // Each $compiledPatterns entry is ONE pattern that matches the word OR any synonym.
        $compiledPatterns = [];
        foreach ($searchWordsLower as $searchWord) {
            // Collect this word + its synonyms (if a synonym map was provided)
            $terms = [$searchWord];
            if (!empty($synonymMap) && isset($synonymMap[$searchWord])) {
                foreach ($synonymMap[$searchWord] as $synonym) {
                    $synonym = mb_strtolower(trim($synonym), 'UTF-8');
                    if ($synonym !== '' && !in_array($synonym, $terms, true)) {
                        $terms[] = $synonym;
                    }
                }
            }

            // Build word-boundary alternatives: (?:^|[\s\-...])(word1|word2|...)
            $alternatives = implode('|', array_map(fn($t) => preg_quote($t, '/'), $terms));
            $compiledPatterns[] = '/(?:^|[\s\-.,;:!?()\[\]{}])(?:' . $alternatives . ')/u';
        }

        // Filter records
        return array_filter($records, function ($record) use ($compiledPatterns, $searchFieldsToUse) {
            // PERFORMANCE: Build array of field values first, then join once
            // More efficient than string concatenation in loop
            $fieldValues = [];

            foreach ($searchFieldsToUse as $fieldName) {
                if (empty($fieldName)) {
                    continue;
                }

                if (isset($record[$fieldName]) && is_string($record[$fieldName])) {
                    // Strip HTML tags so fields like bodytext don't break word-boundary
                    // matching and don't leak markup into the search string
                    $fieldValues[] = strip_tags($record[$fieldName]);
                }
            }

            // If no searchable fields found in this record, exclude it from results
            if (empty($fieldValues)) {
                return false;
            }

            // PERFORMANCE: Join array once instead of concatenating in loop
            // Convert to lowercase once for all comparisons
            $concatenatedFieldsLower = mb_strtolower(implode(' ', $fieldValues), 'UTF-8');

            // Check if ALL word groups (word OR its synonyms) are present (AND logic across groups)
            // Using pre-compiled patterns for performance
            foreach ($compiledPatterns as $pattern) {
                if (preg_match($pattern, $concatenatedFieldsLower) !== 1) {
                    return false; // EARLY EXIT: word group not found = no match
                }
            }

            // All word groups found with proper word boundaries!
            return true;
        });
    }

    /**
     * Check if search word matches text with word boundary detection
     *
     * NOTE: This method is kept for potential future use but is not used in the main
     * search flow anymore due to performance optimization (pre-compiled patterns with
     * synonym support are built directly in filterRecordsBySearch).
     *
     * Word boundaries are defined as:
     * - Start/end of string
     * - Whitespace (space, tab, newline)
     * - Hyphens (-)
     * - Common punctuation (., ,, ;, :, !, ?, (, ), [, ], {, })
     *
     * This allows:
     * - "eerlijk" to match "eerlijke" (stem/plural) but NOT "heerlijk"
     * - "opleiding" to match "basis-opleiding" but NOT "nopleiding"
     *
     * @param string $text Haystack text (already lowercase)
     * @param string $searchWord Needle (already lowercase)
     * @return bool True if search word matches with proper word boundaries
     */
    protected function matchesWithWordBoundary(string $text, string $searchWord): bool
    {
        // Escape special regex characters in search word
        $escapedWord = preg_quote($searchWord, '/');

        // Word boundary pattern:
        // (?:^|[\s\-.,;:!?()\[\]{}])  = start OR boundary character (space, hyphen, punctuation)
        // {$escapedWord}              = the search word
        // (?=[\s\-.,;:!?()\[\]{}]|$)  = followed by boundary OR end (lookahead, doesn't consume)
        //
        // Using lookahead (?=...) for end boundary so we don't consume characters
        // This allows matching stems like "eerlijk" in "eerlijke"
        $pattern = '/(?:^|[\s\-.,;:!?()\[\]{}])' . $escapedWord . '/u';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * Get list of available field names from records
     *
     * @param array $records
     * @return array List of unique field names
     */
    protected function getAvailableFieldsFromRecords(array $records): array
    {
        $availableFields = [];

        // Sample first 10 records to get field names (performance optimization)
        $sampleRecords = array_slice($records, 0, 10);

        foreach ($sampleRecords as $record) {
            if (is_array($record)) {
                $availableFields = array_merge($availableFields, array_keys($record));
            }
        }

        return array_unique($availableFields);
    }
}
