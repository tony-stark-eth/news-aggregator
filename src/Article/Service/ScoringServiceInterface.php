<?php

declare(strict_types=1);

namespace App\Article\Service;

use App\Article\Entity\Article;

interface ScoringServiceInterface
{
    /**
     * Calculate a score for an article (0.0 to 1.0).
     */
    public function score(Article $article): float;

    /**
     * Return individual sub-scores (each 0.0 to 1.0) used to compute the overall score.
     *
     * @return array{recency: float, category: float, source: float, enrichment: float}
     */
    public function breakdown(Article $article): array;
}
