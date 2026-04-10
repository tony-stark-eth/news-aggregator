<?php

declare(strict_types=1);

namespace App\Chat\Store;

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;

interface ConversationMessageStoreInterface extends MessageStoreInterface, ManagedStoreInterface
{
    public function setConversationId(string $conversationId): void;
}
