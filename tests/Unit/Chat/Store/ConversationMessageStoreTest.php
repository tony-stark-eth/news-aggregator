<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Store;

use App\Chat\Store\ConversationMessageStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
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

    public function testListConversationsReturnsFormattedResults(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->with(
                'SELECT conversation_id, added_at, messages FROM chat_messages ORDER BY added_at DESC LIMIT ?',
                [20],
                [ParameterType::INTEGER],
            )
            ->willReturn([
                [
                    'conversation_id' => 'conv-1',
                    'added_at' => 1712764800,
                    'messages' => json_encode([
                        [
                            'type' => 'Symfony\\AI\\Platform\\Message\\UserMessage',
                            'content' => '',
                            'contentAsBase64' => [[
                                'content' => 'First question',
                            ]],
                        ],
                        [
                            'type' => 'Symfony\\AI\\Platform\\Message\\AssistantMessage',
                            'content' => 'First answer',
                        ],
                        [
                            'type' => 'Symfony\\AI\\Platform\\Message\\UserMessage',
                            'content' => '',
                            'contentAsBase64' => [[
                                'content' => 'Second question',
                            ]],
                        ],
                    ]),
                ],
            ]);

        $store = new ConversationMessageStore($connection, $clock);
        $result = $store->listConversations();

        self::assertCount(1, $result);
        self::assertSame('conv-1', $result[0]['conversationId']);
        self::assertSame(1712764800, $result[0]['lastMessageAt']);
        self::assertSame('Second question', $result[0]['preview']);
    }

    public function testListConversationsReturnsEmptyForNoRows(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->willReturn([]);

        $store = new ConversationMessageStore($connection, $clock);
        $result = $store->listConversations();

        self::assertSame([], $result);
    }

    public function testListConversationsTruncatesLongPreview(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $longMessage = str_repeat('a', 150);
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->willReturn([
                [
                    'conversation_id' => 'conv-long',
                    'added_at' => 1712764800,
                    'messages' => json_encode([
                        [
                            'type' => 'Symfony\\AI\\Platform\\Message\\UserMessage',
                            'content' => '',
                            'contentAsBase64' => [[
                                'content' => $longMessage,
                            ]],
                        ],
                    ]),
                ],
            ]);

        $store = new ConversationMessageStore($connection, $clock);
        $result = $store->listConversations();

        self::assertSame(103, mb_strlen($result[0]['preview']));
        self::assertStringEndsWith('...', $result[0]['preview']);
    }

    public function testListConversationsHandlesInvalidJson(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->willReturn([
                [
                    'conversation_id' => 'conv-bad',
                    'added_at' => 1712764800,
                    'messages' => 'not json',
                ],
            ]);

        $store = new ConversationMessageStore($connection, $clock);
        $result = $store->listConversations();

        self::assertSame('', $result[0]['preview']);
    }

    public function testListConversationsWithCustomLimit(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->with(
                self::anything(),
                [5],
                [ParameterType::INTEGER],
            )
            ->willReturn([]);

        $store = new ConversationMessageStore($connection, $clock);
        $store->listConversations(5);
    }

    public function testListConversationsHandlesMultibytePreview(): void
    {
        $clock = new MockClock();
        $connection = $this->createMock(Connection::class);
        $longMb = str_repeat("\u{00FC}", 110); // u-umlaut, 2 bytes each
        $connection->expects(self::once())->method('fetchAllAssociative')
            ->willReturn([
                [
                    'conversation_id' => 'conv-mb',
                    'added_at' => 1712764800,
                    'messages' => json_encode([
                        [
                            'type' => 'Symfony\\AI\\Platform\\Message\\UserMessage',
                            'content' => '',
                            'contentAsBase64' => [[
                                'content' => $longMb,
                            ]],
                        ],
                    ]),
                ],
            ]);

        $store = new ConversationMessageStore($connection, $clock);
        $result = $store->listConversations();

        self::assertSame(103, mb_strlen($result[0]['preview']));
        self::assertStringEndsWith('...', $result[0]['preview']);
    }
}
