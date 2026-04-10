<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class RuleBasedKeywordExtractionService implements KeywordExtractionServiceInterface
{
    private const int MAX_KEYWORDS = 8;

    /**
     * Common words that start sentences but are not proper nouns.
     *
     * @var list<string>
     */
    private const array STOP_WORDS = [
        'The', 'This', 'That', 'These', 'Those', 'There',
        'Here', 'Where', 'When', 'What', 'Which', 'Who',
        'How', 'Why', 'But', 'And', 'For', 'Not', 'Yet',
        'Also', 'Some', 'Any', 'All', 'Each', 'Every',
        'Many', 'Much', 'More', 'Most', 'Other', 'Another',
        'Such', 'Both', 'Few', 'Several', 'After', 'Before',
        'While', 'Since', 'Until', 'During', 'About', 'From',
        'Into', 'With', 'Without', 'Between', 'Through',
        'Under', 'Over', 'Above', 'Below', 'Its', 'Our',
        'Their', 'His', 'Her', 'Your', 'According', 'However',
        'Meanwhile', 'Furthermore', 'Moreover', 'Although',
        'Despite', 'Instead', 'Rather', 'Therefore', 'Still',
        'Indeed', 'Perhaps', 'Certainly', 'Likely', 'Recently',
    ];

    public function __construct(
        private KeywordFilterService $keywordFilter,
    ) {
    }

    public function extract(string $title, ?string $contentText): array
    {
        $text = $title . ' ' . ($contentText ?? '');
        $properNouns = $this->extractProperNouns($text);
        $unique = array_values(array_unique($properNouns));

        $capped = \array_slice($unique, 0, self::MAX_KEYWORDS);

        return $this->keywordFilter->filter($capped);
    }

    /**
     * @return list<string>
     */
    private function extractProperNouns(string $text): array
    {
        // Match sequences of capitalized words (2+ chars each)
        $matches = [];
        preg_match_all(
            '/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\b/',
            $text,
            $matches,
        );

        $multiWord = $matches[1];

        // Also match single capitalized words (proper nouns)
        $singleMatches = [];
        preg_match_all(
            '/\b([A-Z][a-z]{2,})\b/',
            $text,
            $singleMatches,
        );

        $singleWords = $singleMatches[1];

        return $this->filterStopWords([...$multiWord, ...$singleWords]);
    }

    /**
     * @param list<string> $words
     *
     * @return list<string>
     */
    private function filterStopWords(array $words): array
    {
        $stopSet = array_flip(self::STOP_WORDS);

        return array_values(array_filter(
            $words,
            static function (string $word) use ($stopSet): bool {
                // Filter multi-word phrases where first word is a stop word
                $firstWord = explode(' ', $word)[0];

                return ! isset($stopSet[$firstWord]);
            },
        ));
    }
}
