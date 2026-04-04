<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

use App\Source\Entity\Source;

final readonly class FetchResult
{
    public function __construct(
        public int $persistedCount,
        public ArticleCollection $newArticles,
        public ?Source $source,
    ) {
    }
}
