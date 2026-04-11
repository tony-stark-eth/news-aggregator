<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Service;

use App\Chat\Service\ChatModelResolverInterface;
use App\Chat\Service\StreamingChatService;
use App\Chat\Store\ConversationMessageStoreInterface;
use App\Chat\Tool\ArticleSearchToolInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\AI\ValueObject\ModelQualityCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

#[CoversClass(StreamingChatService::class)]
final class StreamingChatServiceTest extends TestCase
{
    public function testStreamYieldsTokenAndDoneEvents(): void
    {
        $articles = $this->sampleArticles();

        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('setConversationId')->with('conv-1');
        $store->expects(self::once())->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save')
            ->with(self::callback(static function (MessageBag $bag): bool {
                $messages = $bag->getMessages();
                // Must persist user + assistant messages
                return \count($messages) >= 2;
            }));

        $searchTool = $this->createMock(ArticleSearchToolInterface::class);
        $searchTool->expects(self::once())->method('search')
            ->with('What happened?')
            ->willReturn($articles);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with(
                'test/model',
                self::callback(static function (MessageBag $bag): bool {
                    // Must include system prompt + user message
                    $sys = $bag->getSystemMessage();

                    return $sys instanceof SystemMessage
                        && str_contains((string) $sys->getContent(), 'news assistant')
                        && str_contains((string) $sys->getContent(), 'Article #42');
                }),
                self::callback(static fn (array $opts): bool => ($opts['stream'] ?? false) === true),
            )
            ->willReturn($this->createDeferredForStream(
                new StreamResult($this->textGenerator(['Hello', ' world'])),
            ));

        $resolver = $this->createMock(ChatModelResolverInterface::class);
        $resolver->expects(self::once())->method('resolveModelChain')->willReturn(['test/model']);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance')
            ->with('test/model', ModelQualityCategory::Chat);

        $service = $this->buildService($store, $platform, $searchTool, $resolver, $tracker);
        $chunks = iterator_to_array($service->stream('What happened?', 'conv-1'), false);

        $statusChunks = $this->filterByEvent($chunks, 'status');
        $tokenChunks = $this->filterByEvent($chunks, 'token');
        $doneChunks = $this->filterByEvent($chunks, 'done');

        self::assertCount(3, $statusChunks);
        self::assertStringContainsString('Searching articles', $statusChunks[0]);
        self::assertStringContainsString('Found 1 relevant article', $statusChunks[1]);
        self::assertStringContainsString('Trying model 1 of 1', $statusChunks[2]);

        self::assertCount(2, $tokenChunks);
        self::assertStringContainsString('"text":"Hello"', $tokenChunks[0]);
        self::assertStringContainsString('"text":" world"', $tokenChunks[1]);

        self::assertCount(1, $doneChunks);
        self::assertStringContainsString('"conversationId":"conv-1"', $doneChunks[0]);
        self::assertStringContainsString('"citedArticles":', $doneChunks[0]);
        self::assertStringContainsString('"id":42', $doneChunks[0]);
        self::assertStringContainsString('"searchSource":"hybrid"', $doneChunks[0]);
    }

    public function testStreamHandlesSingleChunkResponse(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredForStream(
                new StreamResult($this->textGenerator(['Full answer'])),
            ));

        $service = $this->buildService($store, $platform, $searchTool);
        $chunks = iterator_to_array($service->stream('Hello', 'conv-2'), false);

        $tokenChunks = $this->filterByEvent($chunks, 'token');
        $doneChunks = $this->filterByEvent($chunks, 'done');

        self::assertCount(3, $this->filterByEvent($chunks, 'status'));
        self::assertCount(1, $tokenChunks);
        self::assertStringContainsString('"text":"Full answer"', $tokenChunks[0]);
        self::assertCount(1, $doneChunks);
        self::assertStringContainsString('"citedArticles":[]', $doneChunks[0]);
    }

    public function testStreamHandlesPlatformException(): void
    {
        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection')
            ->with('test/model', ModelQualityCategory::Chat);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with(
                self::stringContains('all models exhausted'),
                self::callback(static fn (array $ctx): bool => $ctx['error'] === 'API down'
                    && $ctx['model'] === 'test/model'),
            );

        $service = $this->buildService($store, $platform, $searchTool, tracker: $tracker, logger: $logger);
        $chunks = iterator_to_array($service->stream('Hello', 'conv-3'), false);

        $errorChunks = $this->filterByEvent($chunks, 'error');

        self::assertCount(3, $this->filterByEvent($chunks, 'status'));
        self::assertCount(1, $errorChunks);
        self::assertStringContainsString('"message":"Failed to generate response"', $errorChunks[0]);
    }

    public function testStreamFailsOverToNextModelOnError(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::exactly(2))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('Rate limited')),
                $this->createDeferredForText(new TextResult('Fallback response')),
            );

        $resolver = $this->createMock(ChatModelResolverInterface::class);
        $resolver->method('resolveModelChain')->willReturn(['model/first', 'model/second']);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection')
            ->with('model/first', ModelQualityCategory::Chat);
        $tracker->expects(self::once())->method('recordAcceptance')
            ->with('model/second', ModelQualityCategory::Chat);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('failed, trying next'),
                self::callback(static fn (array $ctx): bool => $ctx['model'] === 'model/first'
                    && $ctx['error'] === 'Rate limited'),
            );

        $service = $this->buildService($store, $platform, $searchTool, $resolver, $tracker, $logger);
        $chunks = iterator_to_array($service->stream('Hello', 'conv-failover'), false);

        $statusChunks = $this->filterByEvent($chunks, 'status');
        $tokenChunks = $this->filterByEvent($chunks, 'token');
        $doneChunks = $this->filterByEvent($chunks, 'done');

        // 2 from stream() + 2 from streamFromPlatform() (one per model attempt)
        self::assertCount(4, $statusChunks);
        self::assertStringContainsString('Trying model 1 of 2', $statusChunks[2]);
        self::assertStringContainsString('Trying model 2 of 2', $statusChunks[3]);

        self::assertCount(1, $tokenChunks);
        self::assertStringContainsString('"text":"Fallback response"', $tokenChunks[0]);
        self::assertCount(1, $doneChunks);
    }

    public function testStreamFailoverExhaustsAllModels(): void
    {
        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('All broken'));

        $resolver = $this->createMock(ChatModelResolverInterface::class);
        $resolver->method('resolveModelChain')->willReturn(['model/a', 'model/b', 'model/c']);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::exactly(3))->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');
        $logger->expects(self::once())->method('error')
            ->with(
                self::stringContains('all models exhausted'),
                self::callback(static fn (array $ctx): bool => $ctx['model'] === 'model/c'),
            );

        $service = $this->buildService($store, $platform, $searchTool, $resolver, $tracker, $logger);
        $chunks = iterator_to_array($service->stream('Hello', 'conv-exhaust'), false);

        $statusChunks = $this->filterByEvent($chunks, 'status');
        $errorChunks = $this->filterByEvent($chunks, 'error');

        // 2 from stream() + 3 from streamFromPlatform() (one per model)
        self::assertCount(5, $statusChunks);
        self::assertCount(1, $errorChunks);
        self::assertStringContainsString('event: error', $errorChunks[0]);
    }

    public function testStreamHandlesSearchFailure(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $searchTool = $this->createMock(ArticleSearchToolInterface::class);
        $searchTool->expects(self::once())->method('search')
            ->willThrowException(new \RuntimeException('Search broken'));

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredForText(new TextResult('No articles found')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Article search failed'),
                self::callback(static fn (array $ctx): bool => $ctx['error'] === 'Search broken'),
            );

        $service = $this->buildService($store, $platform, $searchTool, logger: $logger);
        $chunks = iterator_to_array($service->stream('Query', 'conv-4'), false);

        $statusChunks = $this->filterByEvent($chunks, 'status');
        $tokenChunks = $this->filterByEvent($chunks, 'token');
        $doneChunks = $this->filterByEvent($chunks, 'done');

        self::assertCount(3, $statusChunks);
        self::assertStringContainsString('Found 0 relevant articles', $statusChunks[1]);
        self::assertCount(1, $tokenChunks);
        self::assertCount(1, $doneChunks);
        self::assertStringContainsString('"citedArticles":[]', $doneChunks[0]);
    }

    public function testStreamIncludesHistoryInMessages(): void
    {
        $history = new MessageBag();
        $history->add(Message::ofUser('Previous question'));
        $history->add(Message::ofAssistant('Previous answer'));

        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('load')->willReturn($history);
        $store->expects(self::once())->method('save')
            ->with(self::callback(static function (MessageBag $bag): bool {
                // History (2) + new user + new assistant = 4
                return \count($bag->getMessages()) === 4;
            }));

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with(
                self::anything(),
                self::callback(static function (MessageBag $bag): bool {
                    // System + 2 history + 1 new user = 4
                    return \count($bag->getMessages()) === 4
                        && $bag->getSystemMessage() instanceof SystemMessage;
                }),
                self::anything(),
            )
            ->willReturn($this->createDeferredForText(new TextResult('Reply')));

        $service = $this->buildService($store, $platform, $searchTool);
        $chunks = iterator_to_array($service->stream('Follow up', 'conv-5'), false);

        self::assertCount(3, $this->filterByEvent($chunks, 'status'));
        self::assertCount(1, $this->filterByEvent($chunks, 'token'));
        self::assertCount(1, $this->filterByEvent($chunks, 'done'));
    }

    public function testStreamSystemPromptIncludesArticleContext(): void
    {
        $articles = $this->sampleArticles();

        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn($articles);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with(
                self::anything(),
                self::callback(static function (MessageBag $bag): bool {
                    $sys = $bag->getSystemMessage();
                    if (! $sys instanceof SystemMessage) {
                        return false;
                    }
                    $content = (string) $sys->getContent();

                    return str_contains($content, 'Relevant articles')
                        && str_contains($content, '[Article #42]')
                        && str_contains($content, 'Test Article')
                        && str_contains($content, 'https://example.com/42')
                        && str_contains($content, 'A summary')
                        && str_contains($content, '[Found via: hybrid]');
                }),
                self::anything(),
            )
            ->willReturn($this->createDeferredForText(new TextResult('Answer')));

        $service = $this->buildService($store, $platform, $searchTool);
        iterator_to_array($service->stream('Query', 'conv-6'), false);
    }

    public function testStreamSystemPromptWithoutArticles(): void
    {
        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with(
                self::anything(),
                self::callback(static function (MessageBag $bag): bool {
                    $sys = $bag->getSystemMessage();
                    if (! $sys instanceof SystemMessage) {
                        return false;
                    }

                    return str_contains((string) $sys->getContent(), 'No articles were found');
                }),
                self::anything(),
            )
            ->willReturn($this->createDeferredForText(new TextResult('No data')));

        $service = $this->buildService($store, $platform, $searchTool);
        iterator_to_array($service->stream('Query', 'conv-7'), false);
    }

    public function testStreamWithNullSummaryArticle(): void
    {
        $articles = [
            [
                'id' => 99,
                'title' => 'No Summary Article',
                'summary' => null,
                'keywords' => [],
                'publishedAt' => null,
                'url' => 'https://example.com/99',
                'score' => 0.5,
                'searchSource' => 'keyword',
            ],
        ];

        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn($articles);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with(
                self::anything(),
                self::callback(static function (MessageBag $bag): bool {
                    $sys = $bag->getSystemMessage();

                    return $sys instanceof SystemMessage
                        && str_contains((string) $sys->getContent(), 'No summary available');
                }),
                self::anything(),
            )
            ->willReturn($this->createDeferredForText(new TextResult('Response')));

        $service = $this->buildService($store, $platform, $searchTool);
        iterator_to_array($service->stream('Query', 'conv-8'), false);
    }

    public function testStreamCollectsFullAnswerForPersistence(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save')
            ->with(self::callback(static function (MessageBag $bag): bool {
                $messages = $bag->getMessages();
                $lastMessage = $messages[\count($messages) - 1];

                // The assistant message should contain the full concatenated answer
                return $lastMessage instanceof AssistantMessage
                    && $lastMessage->getContent() === 'Hello world';
            }));

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredForStream(
                new StreamResult($this->textGenerator(['Hello', ' world'])),
            ));

        $service = $this->buildService($store, $platform, $searchTool);
        iterator_to_array($service->stream('Test', 'conv-9'), false);
    }

    public function testStreamSkipsNonStringChunks(): void
    {
        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn([]);

        $generator = (static function (): \Generator {
            yield 'Text chunk';
            yield 42;          // non-string, should be skipped
            yield ' more text';
        })();

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredForStream(new StreamResult($generator)));

        $service = $this->buildService($store, $platform, $searchTool);
        $chunks = iterator_to_array($service->stream('Test', 'conv-10'), false);

        $tokenChunks = $this->filterByEvent($chunks, 'token');

        // 2 token events (the int chunk is skipped)
        self::assertCount(2, $tokenChunks);
        self::assertStringContainsString('"text":"Text chunk"', $tokenChunks[0]);
        self::assertStringContainsString('"text":" more text"', $tokenChunks[1]);
    }

    public function testStreamYieldsStatusEventsWithCorrectPluralization(): void
    {
        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn($this->sampleArticles());

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredForText(new TextResult('Answer')));

        $service = $this->buildService($store, $platform, $searchTool);
        $chunks = iterator_to_array($service->stream('Query', 'conv-plural'), false);

        $statusChunks = $this->filterByEvent($chunks, 'status');

        // 1 article = singular
        self::assertStringContainsString('Found 1 relevant article.', $statusChunks[1]);
        self::assertStringNotContainsString('articles', $statusChunks[1]);
    }

    public function testStreamYieldsStatusEventsWithPluralArticles(): void
    {
        $articles = [
            [
                'id' => 1,
                'title' => 'A',
                'summary' => 's',
                'keywords' => [],
                'publishedAt' => null,
                'url' => 'https://a.com',
                'score' => 0.5,
                'searchSource' => 'keyword',
            ],
            [
                'id' => 2,
                'title' => 'B',
                'summary' => 's',
                'keywords' => [],
                'publishedAt' => null,
                'url' => 'https://b.com',
                'score' => 0.5,
                'searchSource' => 'semantic',
            ],
        ];

        $store = $this->createStub(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $searchTool = $this->createStub(ArticleSearchToolInterface::class);
        $searchTool->method('search')->willReturn($articles);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredForText(new TextResult('Answer')));

        $service = $this->buildService($store, $platform, $searchTool);
        $chunks = iterator_to_array($service->stream('Query', 'conv-plural2'), false);

        $statusChunks = $this->filterByEvent($chunks, 'status');

        self::assertStringContainsString('Found 2 relevant articles.', $statusChunks[1]);
    }

    /**
     * @param list<string> $chunks
     *
     * @return list<string>
     */
    private function filterByEvent(array $chunks, string $event): array
    {
        return array_values(array_filter(
            $chunks,
            static fn (string $chunk): bool => str_contains($chunk, \sprintf('event: %s', $event)),
        ));
    }

    /**
     * @return list<array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float, searchSource: string}>
     */
    private function sampleArticles(): array
    {
        return [
            [
                'id' => 42,
                'title' => 'Test Article',
                'summary' => 'A summary',
                'keywords' => ['test'],
                'publishedAt' => '2026-01-01T00:00:00+00:00',
                'url' => 'https://example.com/42',
                'score' => 0.95,
                'searchSource' => 'hybrid',
            ],
        ];
    }

    private function buildService(
        ConversationMessageStoreInterface $store,
        PlatformInterface $platform,
        ArticleSearchToolInterface $searchTool,
        ?ChatModelResolverInterface $resolver = null,
        ?ModelQualityTrackerInterface $tracker = null,
        ?LoggerInterface $logger = null,
    ): StreamingChatService {
        return new StreamingChatService(
            $store,
            $platform,
            $searchTool,
            $resolver ?? $this->defaultResolver(),
            $tracker ?? $this->createStub(ModelQualityTrackerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function defaultResolver(): ChatModelResolverInterface
    {
        $resolver = $this->createStub(ChatModelResolverInterface::class);
        $resolver->method('resolveModel')->willReturn('test/model');
        $resolver->method('resolveModelChain')->willReturn(['test/model']);

        return $resolver;
    }

    /**
     * @param list<string> $texts
     *
     * @return \Generator<int, string>
     */
    private function textGenerator(array $texts): \Generator
    {
        foreach ($texts as $text) {
            yield $text;
        }
    }

    private function createDeferredForStream(StreamResult $streamResult): DeferredResult
    {
        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($streamResult);
        $converter->method('getTokenUsageExtractor')->willReturn(null);

        return new DeferredResult($converter, new InMemoryRawResult([]));
    }

    private function createDeferredForText(TextResult $textResult): DeferredResult
    {
        $text = $textResult->getContent();
        $generator = (static function () use ($text): \Generator {
            yield $text;
        })();

        return $this->createDeferredForStream(new StreamResult($generator));
    }
}
