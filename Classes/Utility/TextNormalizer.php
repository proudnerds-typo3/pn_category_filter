<?php

declare(strict_types=1);

namespace ProudNerds\PnCategoryFilter\Utility;

/**
 * Lowercases text and removes diacritics, so "Café" and "cafe" compare equal.
 * Search and highlighting must use the same rule, or a match is found but not marked.
 */
final class TextNormalizer
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
        if ($decomposed === false) {
            return $text;
        }

        return preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $text;
    }
}
