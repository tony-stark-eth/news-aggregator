<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Chat\ValueObject\ChatResponse;
use Symfony\AI\Platform\Message\MessageBag;

interface ArticleChatServiceInterface
{
    public function chat(string $userMessage, string $conversationId): ChatResponse;

    public function getHistory(string $conversationId): MessageBag;
}
