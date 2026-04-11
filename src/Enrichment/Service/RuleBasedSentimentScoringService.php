<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class RuleBasedSentimentScoringService implements SentimentScoringServiceInterface
{
    private const float MAX_SCORE = 0.8;

    private const int TITLE_WEIGHT = 2;

    private const array POSITIVE_KEYWORDS = [
        'breakthrough', 'success', 'win', 'growth', 'improve', 'gain', 'boost',
        'advance', 'achieve', 'celebrate', 'thrive', 'prosper', 'benefit', 'progress',
        'innovation', 'optimism', 'recovery', 'upgrade', 'milestone', 'soar',
        'record high', 'surge', 'positive', 'hope', 'opportunity', 'triumph',
        'solution', 'award', 'praise', 'hero',
    ];

    private const array NEGATIVE_KEYWORDS = [
        'crisis', 'crash', 'fail', 'loss', 'decline', 'drop', 'threat', 'attack',
        'disaster', 'collapse', 'scandal', 'fraud', 'death', 'kill', 'war',
        'recession', 'layoff', 'bankrupt', 'shutdown', 'breach', 'exploit',
        'record low', 'plunge', 'negative', 'fear', 'danger', 'tragedy',
        'devastation', 'victim', 'condemn', 'riot',
    ];

    public function score(string $title, ?string $contentText): ?float
    {
        $titleLower = mb_strtolower($title);
        $contentLower = $contentText !== null ? mb_strtolower($contentText) : '';

        $positiveCount = $this->countMatches($titleLower, self::POSITIVE_KEYWORDS) * self::TITLE_WEIGHT
            + $this->countMatches($contentLower, self::POSITIVE_KEYWORDS);

        $negativeCount = $this->countMatches($titleLower, self::NEGATIVE_KEYWORDS) * self::TITLE_WEIGHT
            + $this->countMatches($contentLower, self::NEGATIVE_KEYWORDS);

        $total = $positiveCount + $negativeCount;

        if ($total === 0) {
            return null;
        }

        $raw = ($positiveCount - $negativeCount) / $total;

        return max(-self::MAX_SCORE, min(self::MAX_SCORE, $raw));
    }

    /**
     * @param list<string> $keywords
     */
    private function countMatches(string $text, array $keywords): int
    {
        $count = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                ++$count;
            }
        }

        return $count;
    }
}
