<?php

declare(strict_types=1);

namespace App\Chat\Service;

interface StreamingChatServiceInterface
{
    /**
     * Stream a chat response, publishing tokens to Mercure as they arrive.
     *
     * Phase 1: searches articles (non-streaming).
     * Phase 2: streams synthesis via platform, publishing tokens to Mercure.
     */
    public function stream(string $userMessage, string $conversationId): void;
}
