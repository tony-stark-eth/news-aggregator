<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Store;

use App\Chat\Store\ConversationMessageStore;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ConversationMessageStore::class)]
final class ConversationMessageStoreTest extends TestCase
{
    public function testSaveDeletesExistingThenInserts(): void
    {
        $clock = new MockClock('2026-04-10 12:00:00');
        $connection = $this->createMock(Connection::class);

        $connection->expects(self::once())->method('executeStatement')
            ->with(
                'DELETE FROM chat_messages WHERE conversation_id = ?',
                ['conv-1'],
            );

        $connection->expects(self::once())->method('insert')
            ->with(
                'chat_messages',
                self::callback(static fn (array $data): bool => $data['conversation_id'] === 'conv-1'
                    && \is_string($data['messages'])
                    && \is_int($data['added_at'])),
            );

        $store = new ConversationMessageStore($connection, $clock);
        $store->setConversationId('conv-1');

        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages);
    }

    public function testLoadReturnsEmptyBagWhenNoRows(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')
            ->willReturn(false);

        $store = new ConversationMessageStore($connection, $clock);
        $store->setConversationId('conv-2');

        $result = $store->load();

        self::assertSame([], $result->getMessages());
    }

    public function testDropDeletesConversationMessages(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')
            ->with(
                'DELETE FROM chat_messages WHERE conversation_id = ?',
                ['conv-3'],
            );

        $store = new ConversationMessageStore($connection, $clock);
        $store->setConversationId('conv-3');
        $store->drop();
    }

    public function testSetupDoesNothing(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method(self::anything());

        $store = new ConversationMessageStore($connection, $clock);
        $store->setup();
    }
}
