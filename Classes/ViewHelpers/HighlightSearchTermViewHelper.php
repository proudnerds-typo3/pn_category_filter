<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\ViewHelpers;

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

        // Apply highlighting based on mode
        return $stripTags
            ? self::highlightInPlainText($text, $searchWords)
            : self::highlightPreservingHtml($text, $searchWords);
    }

    /**
     * Highlight search words in plain text (HTML tags already stripped)
     *
     * @param string $text The plain text to process
     * @param array<int, string> $searchWords Array of words to highlight
     * @return string Text with highlighted search terms
     */
    private static function highlightInPlainText(string $text, array $searchWords): string
    {
        foreach ($searchWords as $word) {
            $escapedWord = preg_quote($word, '/');
            $replacement = sprintf(self::HIGHLIGHT_WRAPPER, '$1');
            $text = (string)preg_replace(
                '/(' . $escapedWord . ')/iu',
                $replacement,
                $text
            );
        }

        return $text;
    }

    /**
     * Highlight search words while preserving HTML structure
     *
     * This method ensures that HTML tags and attributes are not affected
     * by the highlighting process. Only text content between tags is highlighted.
     *
     * @param string $text The HTML text to process
     * @param array<int, string> $searchWords Array of words to highlight
     * @return string HTML text with highlighted search terms
     */
    private static function highlightPreservingHtml(string $text, array $searchWords): string
    {
        foreach ($searchWords as $word) {
            // Escape the search word for safe use in regex
            $escapedWord = preg_quote($word, '/');
            $replacement = sprintf(self::HIGHLIGHT_WRAPPER, '$1');

            // Split content into HTML tags and text content
            // This regex matches either HTML tags (including attributes) OR text content between tags
            $text = (string)preg_replace_callback(
                '/(<[^>]*>)|([^<]+)/ius',
                static function (array $matches) use ($escapedWord, $replacement): string {
                    // $matches[1] contains HTML tags (including attributes) - leave unchanged
                    if (!empty($matches[1])) {
                        return $matches[1];
                    }

                    // $matches[2] contains text content - apply highlighting
                    if (!empty($matches[2])) {
                        return (string)preg_replace(
                            '/(' . $escapedWord . ')/iu',
                            $replacement,
                            $matches[2]
                        );
                    }

                    return $matches[0];
                },
                $text
            );
        }

        return $text;
    }
}
