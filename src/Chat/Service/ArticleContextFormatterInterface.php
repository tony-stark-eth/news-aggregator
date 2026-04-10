<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Article\Entity\Article;

interface ArticleContextFormatterInterface
{
    /**
     * Format articles as structured data for LLM context.
     *
     * @param list<Article> $articles
     * @param array<int, float> $scores article ID => combined search score
     *
     * @return list<array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float}>
     */
    public function format(array $articles, array $scores): array;
}
