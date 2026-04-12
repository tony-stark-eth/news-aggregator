<?php

declare(strict_types=1);

namespace App\Enrichment\Message;

final readonly class ScoreSentimentMessage
{
    public function __construct(
        public int $articleId,
    ) {
    }
}
