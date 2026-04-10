<?php

declare(strict_types=1);

namespace App\Chat\Tool;

interface ArticleSearchToolInterface
{
    /**
     * Search articles using hybrid semantic + keyword search.
     *
     * @return list<array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float}>
     */
    public function search(string $query, ?int $daysBack = null, int $limit = 8): array;
}
