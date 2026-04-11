<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use App\Chat\Service\ArticleChatServiceInterface;
use App\Chat\Store\ConversationMessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ChatHistoryController
{
    public function __construct(
        private readonly ArticleChatServiceInterface $chatService,
        private readonly ConversationMessageStoreInterface $messageStore,
    ) {
    }

    #[Route('/chat/history/{conversationId}', name: 'app_chat_history', methods: ['GET'])]
    public function history(string $conversationId): JsonResponse
    {
        $messageBag = $this->chatService->getHistory($conversationId);
        $history = [];

        foreach ($messageBag->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                $history[] = [
                    'role' => 'user',
                    'content' => $message->asText() ?? '',
                ];
            } elseif ($message instanceof AssistantMessage) {
                $history[] = [
                    'role' => 'assistant',
                    'content' => $message->getContent() ?? '',
                ];
            }
        }

        return new JsonResponse([
            'conversationId' => $conversationId,
            'messages' => $history,
        ]);
    }

    #[Route('/chat/conversations', name: 'app_chat_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        return new JsonResponse($this->messageStore->listConversations());
    }
}
