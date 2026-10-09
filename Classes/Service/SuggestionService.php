<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

/**
 * Builds the search suggestions shown while typing: titles of the best matching records, and
 * categories and field filter values whose label matches the term, all from the result set
 * already computed.
 */
final class SuggestionService
{
    public const int MAX_TITLES = 5;
    public const int MAX_CATEGORIES = 3;
    public const int MAX_FIELD_VALUES = 3;

    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    /**
     * @param array<int,array<string,mixed>> $records All matching records, already in display order
     * @param array<int,array<string,mixed>> $categoryTree Filter tree as built for the menu
     * @param array<int,int> $categoryCounts categoryUid => search-aware result count
     * @param array<string,mixed> $settings Plugin settings (searchFields decides the title field)
     * @param list<array<string,mixed>> $fieldFacets Field filters as rendered in the menu, with counts
     * @return array{
     *     titles: list<string>,
     *     categories: list<array{uid: int, title: string, count: int}>,
     *     fieldValues: list<array{key: string, value: string, label: string, facet: string, count: int}>
     * }
     */
    public function build(
        array $records,
        array $categoryTree,
        array $categoryCounts,
        string $searchTerm,
        array $settings,
        array $fieldFacets = [],
    ): array {
        return [
            'titles' => $this->collectTitles($records, $settings, $searchTerm),
            'categories' => $this->collectCategories($categoryTree, $categoryCounts, $searchTerm),
            'fieldValues' => $this->collectFieldValues($fieldFacets, $searchTerm),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $records
     * Only titles that contain the term themselves, so choosing one keeps the search meaningful.
     *
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    private function collectTitles(array $records, array $settings, string $searchTerm): array
    {
        $fields = array_values(array_filter(array_map('trim', explode(',', (string)($settings['searchFields'] ?? 'title')))));
        $titleField = $fields[0] ?? 'title';

        $titles = [];
        foreach ($records as $record) {
            $title = trim(html_entity_decode(strip_tags((string)($record[$titleField] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== '' && !in_array($title, $titles, true) && $this->searchService->matchesText($title, $searchTerm)) {
                $titles[] = $title;
                if (count($titles) === self::MAX_TITLES) {
                    break;
                }
            }
        }

        return $titles;
    }

    /**
     * Selected values are left out: choosing one would change nothing.
     *
     * @param list<array<string,mixed>> $fieldFacets
     * @return list<array{key: string, value: string, label: string, facet: string, count: int}>
     */
    private function collectFieldValues(array $fieldFacets, string $searchTerm): array
    {
        $matches = [];
        foreach ($fieldFacets as $facet) {
            foreach ((array)($facet['values'] ?? []) as $value) {
                $label = (string)($value['label'] ?? '');
                $count = (int)($value['count'] ?? 0);
                if ($count > 0 && empty($value['selected']) && $this->searchService->matchesText($label, $searchTerm)) {
                    $matches[] = [
                        'key' => (string)($facet['key'] ?? ''),
                        'value' => (string)($value['value'] ?? ''),
                        'label' => $label,
                        'facet' => (string)($facet['label'] ?? ''),
                        'count' => $count,
                    ];
                }
            }
        }

        usort($matches, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($matches, 0, self::MAX_FIELD_VALUES);
    }

    /**
     * @param array<int,array<string,mixed>> $categoryTree
     * @param array<int,int> $categoryCounts
     * @return list<array{uid: int, title: string, count: int}>
     */
    private function collectCategories(array $categoryTree, array $categoryCounts, string $searchTerm): array
    {
        $matches = [];
        $walk = function (array $nodes) use (&$walk, &$matches, $categoryCounts, $searchTerm): void {
            foreach ($nodes as $node) {
                $uid = (int)($node['uid'] ?? 0);
                $title = (string)($node['title'] ?? '');
                $count = $categoryCounts[$uid] ?? 0;
                // Parent categories have no checkbox in the filter menu, so choosing one would do nothing
                $isLeaf = empty($node['childCategories']);
                if ($isLeaf && $count > 0 && $this->searchService->matchesText($title, $searchTerm)) {
                    $matches[] = ['uid' => $uid, 'title' => $title, 'count' => $count];
                }
                $walk((array)($node['childCategories'] ?? []));
            }
        };
        $walk($categoryTree);

        usort($matches, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($matches, 0, self::MAX_CATEGORIES);
    }
}
