<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use App\Chat\Service\ArticleChatServiceInterface;
use App\Chat\Service\StreamingChatServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleChatServiceInterface $chatService,
        private readonly StreamingChatServiceInterface $streamingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/chat', name: 'app_chat', methods: ['GET'])]
    public function index(): Response
    {
        return $this->controller->render('chat/index.html.twig');
    }

    #[Route('/chat/message', name: 'app_chat_message', methods: ['POST'])]
    public function message(Request $request): JsonResponse
    {
        $data = $this->decodeRequest($request);
        if ($data === null) {
            return new JsonResponse([
                'error' => 'Invalid JSON request body',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userMessage = $this->extractMessage($data);
        if ($userMessage === null) {
            return new JsonResponse([
                'error' => 'Message is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $conversationId = $this->resolveConversationId($data, $request);

        try {
            $response = $this->chatService->chat($userMessage, $conversationId);
        } catch (\Throwable $e) {
            $this->logger->error('Chat request failed: {error}', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to process chat request',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'answer' => $response->answer,
            'citedArticleIds' => $response->citedArticleIds,
            'conversationId' => $response->conversationId,
        ]);
    }

    #[Route('/chat/stream', name: 'app_chat_stream', methods: ['POST'])]
    public function stream(Request $request): JsonResponse
    {
        set_time_limit(120);

        $data = $this->decodeRequest($request);
        if ($data === null) {
            return new JsonResponse([
                'error' => 'Invalid JSON request body',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userMessage = $this->extractMessage($data);
        if ($userMessage === null) {
            return new JsonResponse([
                'error' => 'Message is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $conversationId = $this->resolveConversationId($data, $request);

        try {
            $this->streamingService->stream($userMessage, $conversationId);
        } catch (\Throwable $e) {
            $this->logger->error('Chat stream failed: {error}', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to process chat request',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'conversationId' => $conversationId,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractMessage(array $data): ?string
    {
        /** @var string $rawMessage */
        $rawMessage = $data['message'] ?? '';
        $userMessage = trim($rawMessage);

        return $userMessage === '' ? null : $userMessage;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeRequest(Request $request): ?array
    {
        $content = $request->getContent();
        if ($content === '') {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! \is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveConversationId(array $data, Request $request): string
    {
        $conversationId = $data['conversationId'] ?? null;
        if (\is_string($conversationId) && $conversationId !== '') {
            return $conversationId;
        }

        $session = $request->getSession();
        $sessionKey = 'chat_conversation_id';
        $stored = $session->get($sessionKey);
        if (\is_string($stored) && $stored !== '') {
            return $stored;
        }

        $newId = bin2hex(random_bytes(16));
        $session->set($sessionKey, $newId);

        return $newId;
    }
}
