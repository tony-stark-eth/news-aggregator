<?php

declare(strict_types=1);

namespace App\Shared\Search\Service;

use App\Article\Entity\Article;

interface ArticleSearchServiceInterface
{
    public function index(Article $article): void;

    public function remove(int $articleId): void;

    /**
     * @return list<int> Article IDs matching the query
     */
    public function search(string $query, ?string $categorySlug = null, int $limit = 50): array;
}
