<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Article\Entity\Article;
use App\Chat\ValueObject\SearchSource;

interface ArticleContextFormatterInterface
{
    /**
     * Format articles as structured data for LLM context.
     *
     * @param list<Article>              $articles
     * @param array<int, float>          $scores  article ID => combined search score
     * @param array<int, SearchSource>   $sources article ID => search source
     *
     * @return list<array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float, searchSource: string}>
     */
    public function format(array $articles, array $scores, array $sources = []): array;
}
