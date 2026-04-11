<?php

declare(strict_types=1);

namespace App\Chat\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class ConversationMessageStore implements ConversationMessageStoreInterface
{
    private string $conversationId = '';

    private readonly SerializerInterface $serializer;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
        $this->serializer = new Serializer(
            [new ArrayDenormalizer(), new MessageNormalizer()],
            [new JsonEncoder()],
        );
    }

    public function setup(array $options = []): void
    {
        // Table created via Doctrine migration
    }

    public function setConversationId(string $conversationId): void
    {
        $this->conversationId = $conversationId;
    }

    public function save(MessageBag $messages): void
    {
        $this->drop();
        $this->connection->insert('chat_messages', [
            'conversation_id' => $this->conversationId,
            'messages' => $this->serializer->serialize($messages->getMessages(), 'json'),
            'added_at' => $this->clock->now()->getTimestamp(),
        ]);
    }

    public function load(): MessageBag
    {
        $row = $this->connection->fetchAssociative(
            'SELECT messages FROM chat_messages WHERE conversation_id = ? ORDER BY added_at DESC LIMIT 1',
            [$this->conversationId],
        );

        if ($row === false) {
            return new MessageBag();
        }

        /** @var list<MessageInterface> $messages */
        $messages = $this->serializer->deserialize(
            $row['messages'],
            MessageInterface::class . '[]',
            'json',
        );

        return new MessageBag(...$messages);
    }

    public function listConversations(int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT conversation_id, added_at, messages FROM chat_messages ORDER BY added_at DESC LIMIT ?',
            [$limit],
            [ParameterType::INTEGER],
        );

        $result = [];

        foreach ($rows as $row) {
            /** @var array{conversation_id: string, added_at: int|string, messages: string} $row */
            $preview = $this->extractLastUserMessage($row['messages']);

            $result[] = [
                'conversationId' => $row['conversation_id'],
                'lastMessageAt' => (int) $row['added_at'],
                'preview' => $preview,
            ];
        }

        return $result;
    }

    public function drop(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM chat_messages WHERE conversation_id = ?',
            [$this->conversationId],
        );
    }

    private function extractLastUserMessage(string $messagesJson): string
    {
        try {
            /** @var list<array{type?: string, content?: string, contentAsBase64?: list<array{content?: string}>}> $messages */
            $messages = json_decode($messagesJson, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        $lastUserContent = '';

        foreach ($messages as $message) {
            if (! str_contains($message['type'] ?? '', 'UserMessage')) {
                continue;
            }

            $lastUserContent = $this->extractUserText($message);
        }

        return mb_strlen($lastUserContent) > 100
            ? mb_substr($lastUserContent, 0, 100) . '...'
            : $lastUserContent;
    }

    /**
     * @param array{content?: string, contentAsBase64?: list<array{content?: string}>} $message
     */
    private function extractUserText(array $message): string
    {
        // Symfony AI stores user text in contentAsBase64[].content
        $text = $message['contentAsBase64'][0]['content'] ?? '';

        return $text !== '' ? $text : ($message['content'] ?? '');
    }
}
