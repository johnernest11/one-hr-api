<?php

namespace App\Helpers;

class AbbreviationHelper
{
    public static function getAbbreviation(string $name): string
    {
        $stopWords = ['a', 'an', 'and', 'the', 'of', 'in', 'on', 'for', 'to', 'with'];

        $titleLower = strtolower($name);
        $words = explode(' ', $titleLower);
        $abbreviation = '';

        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-z0-9]/', '', $word);

            if (in_array($cleanWord, $stopWords) || empty($cleanWord)) {
                continue;
            }

            $abbreviation .= strtoupper($cleanWord[0]);
        }

        return $abbreviation;
    }
}
