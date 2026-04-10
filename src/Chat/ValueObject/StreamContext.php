<?php

declare(strict_types=1);

namespace App\Chat\ValueObject;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Carries state through the streaming chat pipeline.
 *
 * @internal
 */
final readonly class StreamContext
{
    /**
     * @param list<array{id: int, title: string, summary: string|null, url: string}> $articles
     */
    public function __construct(
        public string $conversationId,
        public string $userMessage,
        public MessageBag $history,
        public array $articles,
    ) {
    }
}
