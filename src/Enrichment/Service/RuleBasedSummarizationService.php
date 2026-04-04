<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class RuleBasedSummarizationService implements SummarizationServiceInterface
{
    private const int MIN_CONTENT_LENGTH = 50;

    private const int MAX_SUMMARY_LENGTH = 500;

    public function summarize(string $contentText): ?string
    {
        $text = trim($contentText);

        if (mb_strlen($text) < self::MIN_CONTENT_LENGTH) {
            return null;
        }

        $sentences = $this->extractSentences($text);

        if ($sentences === []) {
            return null;
        }

        $summary = implode(' ', array_slice($sentences, 0, 2));

        if (mb_strlen($summary) > self::MAX_SUMMARY_LENGTH) {
            return mb_substr($summary, 0, self::MAX_SUMMARY_LENGTH - 3) . '...';
        }

        return $summary;
    }

    /**
     * @return list<string>
     */
    private function extractSentences(string $text): array
    {
        // Split on sentence-ending punctuation followed by space or end-of-string
        $parts = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        // Filter out very short fragments (likely abbreviations)
        return array_values(array_filter(
            $parts,
            static fn (string $s): bool => mb_strlen(trim($s)) >= 10,
        ));
    }
}
