<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Controller;

use App\Chat\Controller\ChatHistoryController;
use App\Chat\Service\ArticleChatServiceInterface;
use App\Chat\Store\ConversationMessageStoreInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

#[CoversNothing]
final class ChatHistoryControllerTest extends TestCase
{
    public function testReturnsConversationHistory(): void
    {
        $messages = new MessageBag(
            Message::ofUser('Hello'),
            Message::ofAssistant('Hi there'),
        );

        $chatService = $this->createMock(ArticleChatServiceInterface::class);
        $chatService->expects(self::once())->method('getHistory')
            ->with('conv-1')
            ->willReturn($messages);

        $messageStore = $this->createStub(ConversationMessageStoreInterface::class);

        $controller = new ChatHistoryController($chatService, $messageStore);
        $response = $controller->history('conv-1');

        /** @var array{conversationId: string, messages: list<array{role: string, content: string}>} $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('conv-1', $data['conversationId']);
        self::assertCount(2, $data['messages']);
        self::assertSame('user', $data['messages'][0]['role']);
        self::assertSame('Hello', $data['messages'][0]['content']);
        self::assertSame('assistant', $data['messages'][1]['role']);
        self::assertSame('Hi there', $data['messages'][1]['content']);
    }

    public function testReturnsEmptyHistoryForNewConversation(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $chatService->method('getHistory')->willReturn(new MessageBag());

        $messageStore = $this->createStub(ConversationMessageStoreInterface::class);

        $controller = new ChatHistoryController($chatService, $messageStore);
        $response = $controller->history('new-conv');

        /** @var array{messages: list<mixed>} $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $data['messages']);
    }

    public function testConversationsEndpointReturnsListFromStore(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $conversations = [
            [
                'conversationId' => 'conv-1',
                'lastMessageAt' => 1712764800,
                'preview' => 'What is AI?',
            ],
        ];

        $messageStore = $this->createMock(ConversationMessageStoreInterface::class);
        $messageStore->expects(self::once())->method('listConversations')
            ->willReturn($conversations);

        $controller = new ChatHistoryController($chatService, $messageStore);
        $response = $controller->conversations();

        /** @var list<array{conversationId: string, lastMessageAt: int, preview: string}> $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
        self::assertSame('conv-1', $data[0]['conversationId']);
        self::assertSame('What is AI?', $data[0]['preview']);
    }
}
