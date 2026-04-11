<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Mercure;

use App\Chat\Mercure\ChatStreamPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[CoversClass(ChatStreamPublisher::class)]
final class ChatStreamPublisherTest extends TestCase
{
    public function testPublishStatusSendsUpdateWithCorrectTopicAndEventType(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $data = self::decodePayload($update);
                self::assertSame('/chat/conv-1', self::extractTopic($update));
                self::assertSame('Searching...', $data['text']);
                self::assertSame('status', self::extractEventType($update));

                return true;
            }));

        $publisher = new ChatStreamPublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publishStatus('conv-1', 'Searching...');
    }

    public function testPublishTokenSendsUpdateWithCorrectEventType(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $data = self::decodePayload($update);
                self::assertSame('/chat/conv-2', self::extractTopic($update));
                self::assertSame('Hello', $data['text']);
                self::assertSame('token', self::extractEventType($update));

                return true;
            }));

        $publisher = new ChatStreamPublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publishToken('conv-2', 'Hello');
    }

    public function testPublishDoneSendsUpdateWithCitedArticles(): void
    {
        $articles = [
            [
                'id' => 42,
                'title' => 'Test',
                'summary' => 'A summary',
                'url' => 'https://example.com',
                'searchSource' => 'hybrid',
            ],
        ];

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update) use ($articles): bool {
                $data = self::decodePayload($update);
                self::assertSame('/chat/conv-3', self::extractTopic($update));
                self::assertSame($articles, $data['citedArticles']);
                self::assertSame('done', self::extractEventType($update));

                return true;
            }));

        $publisher = new ChatStreamPublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publishDone('conv-3', $articles);
    }

    public function testPublishDoneWithEmptyArticles(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $data = self::decodePayload($update);
                self::assertSame([], $data['citedArticles']);
                self::assertSame('done', self::extractEventType($update));

                return true;
            }));

        $publisher = new ChatStreamPublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publishDone('conv-empty', []);
    }

    public function testPublishErrorSendsUpdateWithStreamErrorEventType(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $data = self::decodePayload($update);
                self::assertSame('/chat/conv-4', self::extractTopic($update));
                self::assertSame('Something went wrong', $data['message']);
                self::assertSame('stream_error', self::extractEventType($update));

                return true;
            }));

        $publisher = new ChatStreamPublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publishError('conv-4', 'Something went wrong');
    }

    public function testPublishCatchesExceptionAndLogs(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Mercure chat publish failed'),
                self::callback(static function (array $ctx): bool {
                    return $ctx['conversationId'] === 'conv-fail'
                        && $ctx['error'] === 'Connection refused'
                        && $ctx['eventType'] === 'token';
                }),
            );

        $publisher = new ChatStreamPublisher($hub, $logger);
        $publisher->publishToken('conv-fail', 'chunk');
    }

    public function testPublishStatusLogsOnFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('Timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Mercure chat publish failed'),
                self::callback(static function (array $ctx): bool {
                    return $ctx['conversationId'] === 'conv-status-fail'
                        && $ctx['error'] === 'Timeout'
                        && $ctx['eventType'] === 'status';
                }),
            );

        $publisher = new ChatStreamPublisher($hub, $logger);
        $publisher->publishStatus('conv-status-fail', 'Searching...');
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePayload(Update $update): array
    {
        $decoded = json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function extractTopic(Update $update): string
    {
        $topics = $update->getTopics();
        self::assertArrayHasKey(0, $topics);
        $topic = $topics[0];
        self::assertIsString($topic);

        return $topic;
    }

    private static function extractEventType(Update $update): string
    {
        return $update->getType() ?? '';
    }
}
