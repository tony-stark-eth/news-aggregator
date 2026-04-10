<?php

declare(strict_types=1);

namespace App\Chat\Service;

interface StreamingChatServiceInterface
{
    /**
     * Stream a chat response token by token.
     *
     * Phase 1: searches articles (non-streaming).
     * Phase 2: streams synthesis via platform SSE.
     *
     * @return \Generator<int, string> yields SSE-formatted lines
     */
    public function stream(string $userMessage, string $conversationId): \Generator;
}
