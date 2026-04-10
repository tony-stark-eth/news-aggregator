<?php

declare(strict_types=1);

namespace App\Chat\Message;

final readonly class GenerateEmbeddingMessage
{
    public function __construct(
        public int $articleId,
    ) {
    }
}
