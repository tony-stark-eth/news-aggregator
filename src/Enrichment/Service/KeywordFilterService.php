<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class KeywordFilterService
{
    private const int MIN_LENGTH = 3;

    private const int MAX_KEYWORDS = 5;

    /**
     * Lowercase stop words for case-insensitive matching.
     *
     * @var list<string>
     */
    private const array STOP_WORDS = [
        // English
        'the', 'a', 'an', 'in', 'on', 'at', 'for', 'and', 'or', 'but', 'with',
        // German
        'der', 'die', 'das', 'von', 'und', 'für', 'ist', 'ein', 'eine', 'wer', 'wie', 'was', 'zu',
    ];

    /**
     * Filter keywords by removing short tokens, stop words, and limiting count.
     *
     * @param list<string> $keywords
     *
     * @return list<string>
     */
    public function filter(array $keywords): array
    {
        $stopWordSet = array_flip(self::STOP_WORDS);

        $filtered = [];
        foreach ($keywords as $keyword) {
            $trimmed = trim($keyword);

            if (mb_strlen($trimmed) < self::MIN_LENGTH) {
                continue;
            }

            if (isset($stopWordSet[mb_strtolower($trimmed)])) {
                continue;
            }

            $filtered[] = $trimmed;
        }

        return array_slice($filtered, 0, self::MAX_KEYWORDS);
    }
}
