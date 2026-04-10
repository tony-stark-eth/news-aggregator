<?php

declare(strict_types=1);

namespace App\Chat\ValueObject;

final readonly class ChatResponse
{
    /**
     * @param list<int> $citedArticleIds
     */
    public function __construct(
        public string $answer,
        public array $citedArticleIds,
        public string $conversationId,
    ) {
    }
}
