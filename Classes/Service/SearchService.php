<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

use ProudNerds\PnCategoryFilter\Utility\TextNormalizer;

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
     * A word starts where no letter or digit precedes it, so quotes, slashes and
     * punctuation of any script count as a boundary: "haven" matches 'Haven Leeft'.
     */
    private const string WORD_START = '(?<![\\p{L}\\p{N}])';

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

        // A field only has to exist in one of the queried tables; records without it skip it
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

        $compiledPatterns = $this->compilePatterns($searchTerm, $synonymMap);

        return array_filter($records, function ($record) use ($compiledPatterns, $searchFields): bool {
            $fieldTexts = $this->getNormalizedFieldTexts($record, $searchFields);
            if ($fieldTexts === []) {
                return false;
            }

            // Check if ALL word groups (word OR its synonyms) are present (AND logic across groups)
            $concatenated = implode(' ', $fieldTexts);
            foreach ($compiledPatterns as $pattern) {
                if (preg_match($pattern, $concatenated) !== 1) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Relevance per record: for every word group, the weight of the first search field it matches in.
     * The first configured field weighs most, so with "title,teaser,description" a title match wins.
     *
     * @param array<int|string,array<string,mixed>> $records Records that passed filterRecordsBySearch()
     * @param array<string,mixed> $settings Plugin settings containing searchFields
     * @param array<string,string[]> $synonymMap
     * @return array<string,int> "{table}_{uid}" => score; empty when the term is too short to search
     */
    public function scoreRecords(array $records, string $searchTerm, array $settings, array $synonymMap = []): array
    {
        $searchTerm = mb_substr(trim($searchTerm), 0, self::MAX_SEARCH_LENGTH, 'UTF-8');
        if ($searchTerm === '' || $records === [] || mb_strlen($searchTerm, 'UTF-8') < (int)($settings['searchMinChars'] ?? 3)) {
            return [];
        }

        $searchFields = array_values(array_filter(array_map('trim', explode(',', (string)($settings['searchFields'] ?? 'title,description')))));
        $compiledPatterns = $this->compilePatterns($searchTerm, $synonymMap);
        $fieldCount = count($searchFields);

        $scores = [];
        foreach ($records as $record) {
            $fieldTexts = $this->getNormalizedFieldTexts($record, $searchFields);
            $score = 0;
            foreach ($compiledPatterns as $pattern) {
                foreach ($searchFields as $position => $fieldName) {
                    if (isset($fieldTexts[$fieldName]) && preg_match($pattern, $fieldTexts[$fieldName]) === 1) {
                        $score += $fieldCount - $position;
                        break;
                    }
                }
            }
            $scores[($record['_tableName'] ?? '') . '_' . ($record['uid'] ?? 0)] = $score;
        }

        return $scores;
    }

    /**
     * Whether a single text (for example a category title) matches every word of the term,
     * with the same word-start, accent and entity rules as filterRecordsBySearch().
     */
    public function matchesText(string $text, string $searchTerm): bool
    {
        $searchTerm = mb_substr(trim($searchTerm), 0, self::MAX_SEARCH_LENGTH, 'UTF-8');
        if ($searchTerm === '') {
            return false;
        }

        $patterns = $this->compilePatterns($searchTerm, []);
        if ($patterns === []) {
            return false;
        }

        $normalized = $this->getNormalizedFieldTexts(['text' => $text], ['text'])['text'];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * One pattern per search word: the word OR any of its synonyms, at a word start.
     * AND logic across the patterns, OR logic within one.
     *
     * @param array<string,string[]> $synonymMap
     * @return array<int,string>
     */
    private function compilePatterns(string $searchTerm, array $synonymMap): array
    {
        // Quotes around a word are typed, not searched for: '"haven' still finds haven
        $searchWords = array_filter(
            array_map(
                static fn(string $word): string => (string)preg_replace('/^[\'"„‚\p{Pi}\p{Pf}]+|[\'"„‚\p{Pi}\p{Pf}]+$/u', '', trim($word)),
                explode(' ', $searchTerm)
            ),
            static fn($word) => $word !== ''
        );

        $compiledPatterns = [];
        foreach ($searchWords as $searchWord) {
            // Word and synonyms are normalized like the record text: case- and accent-insensitive.
            // The synonym map is keyed by the lowercased word as Solr spells it, accents included.
            $terms = [TextNormalizer::normalize($searchWord)];
            foreach ($synonymMap[mb_strtolower($searchWord, 'UTF-8')] ?? [] as $synonym) {
                $synonym = TextNormalizer::normalize(trim($synonym));
                if ($synonym !== '' && !in_array($synonym, $terms, true)) {
                    $terms[] = $synonym;
                }
            }

            $alternatives = implode('|', array_map(static fn($t) => preg_quote($t, '/'), $terms));
            $compiledPatterns[] = '/' . self::WORD_START . '(?:' . $alternatives . ')/u';
        }

        return $compiledPatterns;
    }

    /**
     * The searchable text of each configured field, as a visitor reads it: tags stripped,
     * entities decoded, case and accents normalized. Non-string fields are left out.
     *
     * @param array<string,mixed> $record
     * @param array<int,string> $searchFields
     * @return array<string,string> field name => normalized text
     */
    private function getNormalizedFieldTexts(array $record, array $searchFields): array
    {
        $texts = [];
        foreach ($searchFields as $fieldName) {
            if ($fieldName !== '' && isset($record[$fieldName]) && is_string($record[$fieldName])) {
                $texts[$fieldName] = TextNormalizer::normalize(
                    html_entity_decode(strip_tags($record[$fieldName]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                );
            }
        }

        return $texts;
    }

    /**
     * Check if search word matches text with word boundary detection
     *
     * NOTE: This method is kept for potential future use but is not used in the main
     * search flow anymore due to performance optimization (pre-compiled patterns with
     * synonym support are built directly in filterRecordsBySearch).
     *
     * A word starts where no letter or digit precedes it (see WORD_START).
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
        $escapedWord = preg_quote($searchWord, '/');
        $pattern = '/' . self::WORD_START . $escapedWord . '/u';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * Field names present in any record, so every queried table contributes its columns
     * (and added keys such as _pageContent).
     *
     * @param array $records
     * @return array List of unique field names
     */
    protected function getAvailableFieldsFromRecords(array $records): array
    {
        $availableFields = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $availableFields += array_flip(array_keys($record));
            }
        }

        return array_map('strval', array_keys($availableFields));
    }
}
