<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Service;

/**
 * Builds the search suggestions shown while typing: titles of the best matching records and
 * categories whose title matches the term, both taken from the result set already computed.
 */
final class SuggestionService
{
    public const int MAX_TITLES = 5;
    public const int MAX_CATEGORIES = 3;

    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    /**
     * @param array<int,array<string,mixed>> $records All matching records, already in display order
     * @param array<int,array<string,mixed>> $categoryTree Filter tree as built for the menu
     * @param array<int,int> $categoryCounts categoryUid => search-aware result count
     * @param array<string,mixed> $settings Plugin settings (searchFields decides the title field)
     * @return array{titles: list<string>, categories: list<array{uid: int, title: string, count: int}>}
     */
    public function build(array $records, array $categoryTree, array $categoryCounts, string $searchTerm, array $settings): array
    {
        return [
            'titles' => $this->collectTitles($records, $settings),
            'categories' => $this->collectCategories($categoryTree, $categoryCounts, $searchTerm),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $records
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    private function collectTitles(array $records, array $settings): array
    {
        $fields = array_values(array_filter(array_map('trim', explode(',', (string)($settings['searchFields'] ?? 'title')))));
        $titleField = $fields[0] ?? 'title';

        $titles = [];
        foreach ($records as $record) {
            $title = trim(html_entity_decode(strip_tags((string)($record[$titleField] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== '' && !in_array($title, $titles, true)) {
                $titles[] = $title;
                if (count($titles) === self::MAX_TITLES) {
                    break;
                }
            }
        }

        return $titles;
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
                if ($count > 0 && $this->searchService->matchesText($title, $searchTerm)) {
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
