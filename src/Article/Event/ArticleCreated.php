<?php

declare(strict_types=1);

namespace App\Article\Event;

use App\Article\Entity\Article;

final readonly class ArticleCreated
{
    public function __construct(
        public Article $article,
    ) {
    }
}
