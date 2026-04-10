<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Controller;

use App\Chat\Controller\ChatController;
use App\Chat\Service\ArticleChatServiceInterface;
use App\Chat\ValueObject\ChatResponse;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversNothing]
final class ChatControllerTest extends TestCase
{
    public function testMessageReturnsJsonResponse(): void
    {
        $chatResponse = new ChatResponse('AI answer', [1, 2], 'conv-1');

        $chatService = $this->createMock(ArticleChatServiceInterface::class);
        $chatService->expects(self::once())->method('chat')
            ->with('Hello AI', 'conv-1')
            ->willReturn($chatResponse);

        $controller = $this->buildController($chatService);

        $request = Request::create('/chat/message', 'POST', content: json_encode([
            'message' => 'Hello AI',
            'conversationId' => 'conv-1',
        ], \JSON_THROW_ON_ERROR));
        $request->headers->set('Content-Type', 'application/json');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->message($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{answer: string, citedArticleIds: list<int>, conversationId: string} $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('AI answer', $data['answer']);
        self::assertSame([1, 2], $data['citedArticleIds']);
        self::assertSame('conv-1', $data['conversationId']);
    }

    public function testMessageReturnsBadRequestForEmptyBody(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $controller = $this->buildController($chatService);

        $request = Request::create('/chat/message', 'POST', content: '');
        $response = $controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testMessageReturnsBadRequestForInvalidJson(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $controller = $this->buildController($chatService);

        $request = Request::create('/chat/message', 'POST', content: '{invalid');
        $response = $controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testMessageReturnsBadRequestForEmptyMessage(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $controller = $this->buildController($chatService);

        $request = Request::create('/chat/message', 'POST', content: json_encode([
            'message' => '   ',
        ], \JSON_THROW_ON_ERROR));
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testMessageReturnsServerErrorOnException(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $chatService->method('chat')->willThrowException(new \RuntimeException('AI broken'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with(
                self::stringContains('Chat request failed'),
                self::callback(static fn (array $ctx): bool => $ctx['error'] === 'AI broken'),
            );

        $controller = $this->buildController($chatService, $logger);

        $request = Request::create('/chat/message', 'POST', content: json_encode([
            'message' => 'Hello',
            'conversationId' => 'conv-x',
        ], \JSON_THROW_ON_ERROR));
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->message($request);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function testMessageGeneratesConversationIdFromSession(): void
    {
        $chatService = $this->createMock(ArticleChatServiceInterface::class);
        $chatService->expects(self::once())->method('chat')
            ->with('Hello', self::callback(static fn (string $id): bool => $id !== ''))
            ->willReturn(new ChatResponse('Hi', [], 'generated-id'));

        $controller = $this->buildController($chatService);

        $request = Request::create('/chat/message', 'POST', content: json_encode([
            'message' => 'Hello',
        ], \JSON_THROW_ON_ERROR));
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->message($request);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testMessageReturnsBadRequestForNonArrayJson(): void
    {
        $chatService = $this->createStub(ArticleChatServiceInterface::class);
        $controller = $this->buildController($chatService);

        $request = Request::create('/chat/message', 'POST', content: '"just a string"');
        $response = $controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function buildController(
        ArticleChatServiceInterface $chatService,
        ?LoggerInterface $logger = null,
    ): ChatController {
        return new ChatController(
            $this->createStub(ControllerHelper::class),
            $chatService,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
