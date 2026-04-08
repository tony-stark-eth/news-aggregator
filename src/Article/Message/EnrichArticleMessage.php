<?php

declare(strict_types=1);

namespace App\Article\Message;

final readonly class EnrichArticleMessage
{
    public function __construct(
        public int $articleId,
        public string $correlationId = '',
    ) {
    }
}
