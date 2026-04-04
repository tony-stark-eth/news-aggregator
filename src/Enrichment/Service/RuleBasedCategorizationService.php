<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\ValueObject\EnrichmentMethod;

final readonly class RuleBasedCategorizationService implements CategorizationServiceInterface
{
    /**
     * @var array<string, list<string>>
     */
    private const array CATEGORY_KEYWORDS = [
        'politics' => [
            'government', 'parliament', 'president', 'minister', 'election',
            'vote', 'policy', 'law', 'senate', 'congress', 'democrat',
            'republican', 'coalition', 'opposition', 'diplomat', 'sanction',
            'treaty', 'nato', 'un ', 'european union', 'bundestag',
            'regierung', 'politik', 'wahl', 'partei', 'gesetz',
        ],
        'business' => [
            'stock', 'market', 'economy', 'finance', 'bank', 'invest',
            'revenue', 'profit', 'startup', 'ipo', 'merger', 'acquisition',
            'gdp', 'inflation', 'interest rate', 'fed ', 'ecb', 'trade',
            'wirtschaft', 'aktie', 'unternehmen', 'handel', 'boerse',
        ],
        'tech' => [
            'software', 'hardware', 'app ', 'ai ', 'artificial intelligence',
            'machine learning', 'cloud', 'cyber', 'data', 'algorithm',
            'startup', 'silicon valley', 'apple', 'google', 'microsoft',
            'amazon', 'meta', 'linux', 'open source', 'programming',
            'developer', 'api', 'blockchain', 'chip', 'semiconductor',
        ],
        'science' => [
            'research', 'study', 'scientist', 'discovery', 'experiment',
            'nasa', 'space', 'planet', 'climate', 'species', 'genome',
            'quantum', 'physics', 'biology', 'chemistry', 'medicine',
            'vaccine', 'dna', 'evolution', 'fossil', 'telescope',
            'forschung', 'wissenschaft', 'studie', 'entdeckung',
        ],
        'sports' => [
            'goal', 'match', 'team', 'player', 'coach', 'league',
            'champion', 'tournament', 'olympic', 'fifa', 'uefa',
            'bundesliga', 'premier league', 'nba', 'nfl', 'tennis',
            'formula 1', 'transfer', 'stadium', 'score', 'win ',
            'fussball', 'spiel', 'meisterschaft', 'trainer', 'verein',
        ],
    ];

    public function categorize(string $title, ?string $contentText): EnrichmentResult
    {
        $text = mb_strtolower($title . ' ' . ($contentText ?? ''));

        $bestCategory = null;
        $bestScore = 0;

        foreach (self::CATEGORY_KEYWORDS as $slug => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCategory = $slug;
            }
        }

        // Require at least 2 keyword matches to classify
        if ($bestScore < 2) {
            return new EnrichmentResult(null, EnrichmentMethod::RuleBased);
        }

        return new EnrichmentResult($bestCategory, EnrichmentMethod::RuleBased);
    }
}
