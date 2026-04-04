<?php

declare(strict_types=1);

namespace App\Digest\ValueObject;

use App\Article\ValueObject\ArticleCollection;

final readonly class GroupedArticles
{
    /**
     * @param array<string, ArticleCollection> $byCategory
     */
    public function __construct(
        public array $byCategory,
    ) {
    }

    public function totalCount(): int
    {
        return array_sum(array_map('count', $this->byCategory));
    }

    public function isEmpty(): bool
    {
        return $this->byCategory === [];
    }
}
