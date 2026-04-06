<?php

declare(strict_types=1);

namespace App\Article\Mercure;

use App\Article\Entity\Article;

interface MercurePublisherServiceInterface
{
    public function publishArticleCreated(Article $article): void;

    public function publishEnrichmentComplete(Article $article): void;
}
