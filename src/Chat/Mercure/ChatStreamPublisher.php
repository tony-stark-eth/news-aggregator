<?php

declare(strict_types=1);

namespace App\Chat\Mercure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class ChatStreamPublisher implements ChatStreamPublisherInterface
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publishStatus(string $conversationId, string $text): void
    {
        $this->publish($conversationId, [
            'text' => $text,
        ], 'status');
    }

    public function publishToken(string $conversationId, string $text): void
    {
        $this->publish($conversationId, [
            'text' => $text,
        ], 'token');
    }

    /**
     * @param list<array{id: int, title: string, summary: string|null, url: string, searchSource?: string}> $citedArticles
     */
    public function publishDone(string $conversationId, array $citedArticles): void
    {
        $this->publish($conversationId, [
            'citedArticles' => $citedArticles,
        ], 'done');
    }

    public function publishError(string $conversationId, string $message): void
    {
        $this->publish($conversationId, [
            'message' => $message,
        ], 'stream_error');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publish(string $conversationId, array $data, string $eventType): void
    {
        $topic = '/chat/' . $conversationId;

        try {
            $json = json_encode($data, \JSON_THROW_ON_ERROR);
            $this->hub->publish(new Update($topic, $json, false, null, $eventType));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure chat publish failed for {conversationId}: {error}', [
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
                'eventType' => $eventType,
            ]);
        }
    }
}
