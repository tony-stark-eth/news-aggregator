<?php

declare(strict_types=1);

namespace App\Article\Service;

use App\Article\Entity\Article;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\ValueObject\SourceHealth;
use Psr\Clock\ClockInterface;

final readonly class ScoringService implements ScoringServiceInterface
{
    private const float WEIGHT_CATEGORY = 0.3;

    private const float WEIGHT_RECENCY = 0.4;

    private const float WEIGHT_SOURCE = 0.2;

    private const float WEIGHT_ENRICHMENT = 0.1;

    private const int MAX_CATEGORY_WEIGHT = 10;

    private const int RECENCY_HALF_LIFE_HOURS = 12;

    private const int RECENCY_MAX_AGE_HOURS = 168; // 7 days

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function score(Article $article): float
    {
        $categoryScore = $this->scoreCategoryWeight($article);
        $recencyScore = $this->scoreRecency($article);
        $sourceScore = $this->scoreSourceReliability($article);
        $enrichmentScore = $this->scoreEnrichment($article);

        $combined = (self::WEIGHT_CATEGORY * $categoryScore)
            + (self::WEIGHT_RECENCY * $recencyScore)
            + (self::WEIGHT_SOURCE * $sourceScore)
            + (self::WEIGHT_ENRICHMENT * $enrichmentScore);

        return round(max(0.0, min(1.0, $combined)), 4);
    }

    private function scoreCategoryWeight(Article $article): float
    {
        $category = $article->getCategory();
        if (! $category instanceof \App\Shared\Entity\Category) {
            return 0.5; // default for uncategorized
        }

        return min(1.0, $category->getWeight() / self::MAX_CATEGORY_WEIGHT);
    }

    private function scoreRecency(Article $article): float
    {
        $publishedAt = $article->getPublishedAt() ?? $article->getFetchedAt();
        $now = $this->clock->now();

        $ageHours = ($now->getTimestamp() - $publishedAt->getTimestamp()) / 3600;

        if ($ageHours <= 0) {
            return 1.0;
        }

        if ($ageHours >= self::RECENCY_MAX_AGE_HOURS) {
            return 0.0;
        }

        // Exponential decay: score = 2^(-age/halfLife)
        return 2 ** (-$ageHours / self::RECENCY_HALF_LIFE_HOURS);
    }

    private function scoreSourceReliability(Article $article): float
    {
        $health = $article->getSource()->getHealthStatus();

        return match ($health) {
            SourceHealth::Healthy => 1.0,
            SourceHealth::Degraded => 0.7,
            SourceHealth::Failing => 0.4,
            SourceHealth::Disabled => 0.1,
        };
    }

    private function scoreEnrichment(Article $article): float
    {
        $method = $article->getEnrichmentMethod();

        return match ($method) {
            EnrichmentMethod::Ai => 1.0,
            EnrichmentMethod::RuleBased => 0.6,
            null => 0.3,
        };
    }
}
