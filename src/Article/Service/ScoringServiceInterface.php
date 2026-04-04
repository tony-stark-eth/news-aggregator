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
}
