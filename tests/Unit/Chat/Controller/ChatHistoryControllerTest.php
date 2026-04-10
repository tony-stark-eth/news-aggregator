<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Controller;

use App\Chat\Controller\ChatHistoryController;
use App\Chat\Service\ArticleChatServiceInterface;
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

        $controller = new ChatHistoryController($chatService);
        $response = $controller->__invoke('conv-1');

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

        $controller = new ChatHistoryController($chatService);
        $response = $controller->__invoke('new-conv');

        /** @var array{messages: list<mixed>} $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $data['messages']);
    }
}
