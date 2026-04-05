<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;

interface ArticleEnrichmentServiceInterface
{
    public function enrich(Article $article, FeedItem $item, Source $source): void;
}
