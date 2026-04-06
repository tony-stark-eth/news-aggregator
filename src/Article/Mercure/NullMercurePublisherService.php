<?php

declare(strict_types=1);

namespace App\Article\Mercure;

use App\Article\Entity\Article;

final readonly class NullMercurePublisherService implements MercurePublisherServiceInterface
{
    public function publishArticleCreated(Article $article): void
    {
        // No-op: Mercure not configured
    }

    public function publishEnrichmentComplete(Article $article): void
    {
        // No-op: Mercure not configured
    }
}
