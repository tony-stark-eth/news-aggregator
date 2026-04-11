<?php

declare(strict_types=1);

namespace App\Chat\Mercure;

interface ChatStreamPublisherInterface
{
    public function publishStatus(string $conversationId, string $text): void;

    public function publishToken(string $conversationId, string $text): void;

    /**
     * @param list<array{id: int, title: string, summary: string|null, url: string, searchSource?: string}> $citedArticles
     */
    public function publishDone(string $conversationId, array $citedArticles): void;

    public function publishError(string $conversationId, string $message): void;
}
