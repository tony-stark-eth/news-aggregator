<?php

declare(strict_types=1);

namespace App\Chat\Mercure;

final readonly class NullChatStreamPublisher implements ChatStreamPublisherInterface
{
    public function publishStatus(string $conversationId, string $text): void
    {
        // No-op: Mercure not configured
    }

    public function publishToken(string $conversationId, string $text): void
    {
        // No-op: Mercure not configured
    }

    public function publishDone(string $conversationId, array $citedArticles): void
    {
        // No-op: Mercure not configured
    }

    public function publishError(string $conversationId, string $message): void
    {
        // No-op: Mercure not configured
    }
}
