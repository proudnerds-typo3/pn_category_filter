<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\ViewHelpers;

use ProudNerds\PnCategoryFilter\Utility\TextNormalizer;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ViewHelper to highlight search terms in text
 *
 * This ViewHelper can work in two modes:
 * 1. HTML-preserving mode (default): Highlights search terms while keeping HTML tags intact
 * 2. Strip tags mode: Removes HTML tags first, then highlights search terms in plain text
 *
 * Words of 3 characters or shorter are not highlighted.
 *
 * Usage:
 * <pn:highlightSearchTerm searchTerm="{activeSearchTerm}">{record.title}</pn:highlightSearchTerm>
 * <pn:highlightSearchTerm searchTerm="{activeSearchTerm}" stripTags="1">{record.description -> f:format.html()}</pn:highlightSearchTerm>
 */
class HighlightSearchTermViewHelper extends AbstractViewHelper
{
    /**
     * Disable output escaping to allow HTML highlighting
     */
    protected $escapeOutput = false;

    /**
     * Enable children escaping for security
     */
    protected $escapeChildren = true;

    private const string HIGHLIGHT_WRAPPER = '<span class="result-highlight">%s</span>';
    private const int MINIMUM_WORD_LENGTH = 3;

    /**
     * Initialize ViewHelper arguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'searchTerm',
            'string',
            'The search term to highlight (supports multiple words separated by spaces)',
            false,
            ''
        );
        $this->registerArgument(
            'stripTags',
            'bool',
            'Whether to strip HTML tags before highlighting (useful for RTE content)',
            false,
            false
        );
    }

    /**
     * Highlight search terms in text while optionally preserving HTML markup
     *
     * @param array<string, mixed> $arguments ViewHelper arguments
     * @param \Closure $renderChildrenClosure Closure to render children
     * @param RenderingContextInterface $renderingContext Rendering context
     * @return string The text with highlighted search terms
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        $text = (string)$renderChildrenClosure();
        $searchTerm = trim((string)($arguments['searchTerm'] ?? ''));
        $stripTags = (bool)($arguments['stripTags'] ?? false);

        // Early return if empty text
        if ($text === '') {
            return $text;
        }

        // Strip HTML tags if requested (always, even without search term)
        if ($stripTags) {
            $text = strip_tags($text);
        }

        // If no search term, return text as is (potentially with tags stripped)
        if ($searchTerm === '') {
            return $text;
        }

        // Split search term into individual words for multi-word highlighting
        // Words of 2 characters or shorter are excluded from highlighting
        $searchWords = array_filter(
            array_map('trim', explode(' ', $searchTerm)),
            static fn(string $word): bool => mb_strlen($word) > self::MINIMUM_WORD_LENGTH
        );

        // Early return if no valid search words found
        if ($searchWords === []) {
            return $text;
        }

        $searchWords = array_map(static fn(string $word): string => TextNormalizer::normalize($word), $searchWords);

        // Apply highlighting based on mode
        return $stripTags
            ? self::highlightSegment($text, $searchWords)
            : self::highlightPreservingHtml($text, $searchWords);
    }

    /**
     * Highlight search words in a piece of text without tags.
     *
     * Matching runs on a normalized copy (case and accents ignored) while the original characters
     * are kept; an entity such as &eacute; counts as one character, so no span can split it.
     *
     * @param array<int, string> $searchWords Normalized words to highlight
     */
    private static function highlightSegment(string $text, array $searchWords): string
    {
        if (preg_match_all('/&#?[a-z0-9]+;|./isu', $text, $matches) === false) {
            return $text;
        }
        $units = $matches[0];

        // Normalized text plus, per normalized character, the index of the unit it came from.
        $normalized = '';
        $unitByPosition = [];
        foreach ($units as $index => $unit) {
            $plain = $unit[0] === '&' && strlen($unit) > 1
                ? html_entity_decode($unit, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : $unit;
            $normalizedUnit = TextNormalizer::normalize($plain);
            $normalized .= $normalizedUnit;
            for ($i = mb_strlen($normalizedUnit, 'UTF-8'); $i > 0; $i--) {
                $unitByPosition[] = $index;
            }
        }

        $marked = [];
        foreach ($searchWords as $word) {
            $length = mb_strlen($word, 'UTF-8');
            $offset = 0;
            while ($length > 0 && ($position = mb_strpos($normalized, $word, $offset, 'UTF-8')) !== false) {
                for ($i = $position; $i < $position + $length; $i++) {
                    $marked[$unitByPosition[$i]] = true;
                }
                $offset = $position + $length;
            }
        }
        if ($marked === []) {
            return $text;
        }

        $result = '';
        $run = '';
        foreach ($units as $index => $unit) {
            if (isset($marked[$index])) {
                $run .= $unit;
                continue;
            }
            if ($run !== '') {
                $result .= sprintf(self::HIGHLIGHT_WRAPPER, $run);
                $run = '';
            }
            $result .= $unit;
        }
        if ($run !== '') {
            $result .= sprintf(self::HIGHLIGHT_WRAPPER, $run);
        }

        return $result;
    }

    /**
     * Highlight search words while preserving HTML structure
     *
     * This method ensures that HTML tags and attributes are not affected
     * by the highlighting process. Only text content between tags is highlighted.
     *
     * @param string $text The HTML text to process
     * @param array<int, string> $searchWords Normalized words to highlight
     * @return string HTML text with highlighted search terms
     */
    private static function highlightPreservingHtml(string $text, array $searchWords): string
    {
        // Split content into HTML tags (left unchanged) and the text between them
        return (string)preg_replace_callback(
            '/(<[^>]*>)|([^<]+)/ius',
            static function (array $matches) use ($searchWords): string {
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                if (!empty($matches[2])) {
                    return self::highlightSegment($matches[2], $searchWords);
                }

                return $matches[0];
            },
            $text
        );
    }
}
