<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Event\ArticleCreated;
use App\Article\Message\EnrichArticleMessage;
use App\Article\MessageHandler\FetchSourceHandler;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\DeduplicationServiceInterface;
use App\Article\ValueObject\ArticleCollection;
use App\Article\ValueObject\ArticleFingerprint;
use App\Article\ValueObject\EnrichmentStatus;
use App\Article\ValueObject\FetchResult;
use App\Article\ValueObject\PersistItemResult;
use App\Enrichment\Service\RuleBasedEnrichmentServiceInterface;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\Exception\FeedFetchException;
use App\Source\Message\FetchSourceMessage;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedItemCollection;
use App\Source\Service\FeedParserServiceInterface;
use App\Source\ValueObject\SourceHealth;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(FetchSourceHandler::class)]
#[UsesClass(FetchSourceMessage::class)]
#[UsesClass(FeedItem::class)]
#[UsesClass(FeedItemCollection::class)]
#[UsesClass(FetchResult::class)]
#[UsesClass(PersistItemResult::class)]
#[UsesClass(ArticleCollection::class)]
#[UsesClass(ArticleFingerprint::class)]
#[UsesClass(ArticleCreated::class)]
#[UsesClass(FeedFetchException::class)]
#[UsesClass(EnrichArticleMessage::class)]
#[UsesClass(EnrichmentStatus::class)]
final class FetchSourceHandlerTest extends TestCase
{
    private MockClock $clock;

    private Source $source;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-04-04 12:00:00');
        $this->source = $this->createSource();
    }

    public function testHandlesFetchAndPersistsArticles(): void
    {
        // Put source into degraded state so recordSuccess() is observable
        $this->source->recordFailure('previous error');
        self::assertSame(SourceHealth::Degraded, $this->source->getHealthStatus());

        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $enrichment = $this->createMock(RuleBasedEnrichmentServiceInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $fetcher->method('fetch')->willReturn('<rss>...</rss>');

        $parser = $this->createStub(FeedParserServiceInterface::class);
        $parser->method('parse')->willReturn(new FeedItemCollection([
            new FeedItem('Article 1', 'https://example.com/1', '<p>Content</p>', 'Content text here for summarization', null),
            new FeedItem('Article 2', 'https://example.com/2', null, null, null),
        ]));

        $enrichment->expects(self::exactly(2))->method('enrich');

        $saved = [];
        $articleRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (Article $article, bool $flush) use (&$saved): void {
                self::assertTrue($flush, 'save() must be called with flush: true');
                $saved[] = $article;
            });

        $articleRepository->expects(self::once())->method('flush');

        $eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $event): bool => $event instanceof ArticleCreated));

        $logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('new articles'),
                self::callback(static function (array $context): bool {
                    return $context['source'] === 'Test'
                        && $context['count'] === 2
                        && $context['total'] === 2;
                }),
            );

        $handler = $this->createHandler(
            articleRepository: $articleRepository,
            fetcher: $fetcher,
            parser: $parser,
            enrichment: $enrichment,
            eventDispatcher: $eventDispatcher,
            logger: $logger,
        );

        ($handler)(new FetchSourceMessage(1));

        self::assertCount(2, $saved);
        self::assertSame('Article 1', $saved[0]->getTitle());
        self::assertSame('<p>Content</p>', $saved[0]->getContentRaw());
        self::assertSame('Content text here for summarization', $saved[0]->getContentText());
        self::assertNotNull($saved[0]->getFingerprint());
        self::assertNull($saved[1]->getContentRaw());
        self::assertNull($saved[1]->getFingerprint());
        self::assertSame(EnrichmentStatus::Pending, $saved[0]->getEnrichmentStatus());
        self::assertSame(EnrichmentStatus::Pending, $saved[1]->getEnrichmentStatus());
        self::assertSame(SourceHealth::Healthy, $this->source->getHealthStatus());
        self::assertSame(0, $this->source->getErrorCount());
    }

    public function testRecordsFailureOnFetchError(): void
    {
        $sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $sourceRepository->method('findById')->willReturn($this->source);
        $logger = $this->createMock(LoggerInterface::class);

        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $fetcher->method('fetch')->willThrowException(
            FeedFetchException::fromUrl('https://example.com/feed', 'timeout'),
        );

        $sourceRepository->expects(self::once())->method('flush');

        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('fetch failed'),
                self::callback(static function (array $context): bool {
                    return $context['source'] === 'Test'
                        && \is_string($context['error'])
                        && $context['error'] !== '';
                }),
            );

        $handler = $this->createHandler(
            sourceRepository: $sourceRepository,
            fetcher: $fetcher,
            logger: $logger,
        );

        ($handler)(new FetchSourceMessage(1));

        self::assertSame(SourceHealth::Degraded, $this->source->getHealthStatus());
        self::assertSame(1, $this->source->getErrorCount());
    }

    public function testSkipsDisabledSource(): void
    {
        $fetcher = $this->createMock(FeedFetcherServiceInterface::class);
        $fetcher->expects(self::never())->method('fetch');

        $this->source->setEnabled(false);

        $handler = $this->createHandler(fetcher: $fetcher);

        ($handler)(new FetchSourceMessage(1));

        self::assertFalse($this->source->isEnabled());
    }

    public function testSkipsDuplicateArticles(): void
    {
        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $enrichment = $this->createMock(RuleBasedEnrichmentServiceInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $fetcher->method('fetch')->willReturn('<rss>...</rss>');

        $parser = $this->createStub(FeedParserServiceInterface::class);
        $parser->method('parse')->willReturn(new FeedItemCollection([
            new FeedItem('Duplicate', 'https://example.com/dup', null, null, null),
            new FeedItem('Unique', 'https://example.com/new', null, null, null),
        ]));

        $dedup = $this->createStub(DeduplicationServiceInterface::class);
        $callCount = 0;
        $dedup->method('isDuplicate')->willReturnCallback(function () use (&$callCount): bool {
            return ++$callCount === 1;
        });

        $articleRepository->expects(self::once())->method('save');
        $enrichment->expects(self::once())->method('enrich');
        $articleRepository->expects(self::once())->method('flush');

        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $event): bool => $event instanceof ArticleCreated));

        $logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('new articles'),
                self::callback(static function (array $context): bool {
                    return $context['count'] === 1 && $context['total'] === 2;
                }),
            );

        $handler = $this->createHandler(
            articleRepository: $articleRepository,
            fetcher: $fetcher,
            parser: $parser,
            dedup: $dedup,
            enrichment: $enrichment,
            eventDispatcher: $eventDispatcher,
            logger: $logger,
        );

        ($handler)(new FetchSourceMessage(1));
    }

    public function testDispatchesArticleCreatedEventsForEachNewArticle(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $fetcher->method('fetch')->willReturn('<rss>...</rss>');

        $parser = $this->createStub(FeedParserServiceInterface::class);
        $parser->method('parse')->willReturn(new FeedItemCollection([
            new FeedItem('First', 'https://example.com/1', null, null, null),
            new FeedItem('Second', 'https://example.com/2', null, null, null),
            new FeedItem('Third', 'https://example.com/3', null, null, null),
        ]));

        $dispatched = [];
        $eventDispatcher->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (ArticleCreated $event) use (&$dispatched): ArticleCreated {
                $dispatched[] = $event->article->getTitle();

                return $event;
            });

        $handler = $this->createHandler(
            fetcher: $fetcher,
            parser: $parser,
            eventDispatcher: $eventDispatcher,
        );

        ($handler)(new FetchSourceMessage(1));

        self::assertSame(['First', 'Second', 'Third'], $dispatched);
    }

    public function testDispatchesEnrichArticleMessageForEachNewArticle(): void
    {
        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $fetcher->method('fetch')->willReturn('<rss>...</rss>');

        $parser = $this->createStub(FeedParserServiceInterface::class);
        $parser->method('parse')->willReturn(new FeedItemCollection([
            new FeedItem('First', 'https://example.com/1', null, null, null),
            new FeedItem('Second', 'https://example.com/2', null, null, null),
        ]));

        // Simulate DB save assigning IDs
        $nextId = 1;
        $articleRepository->method('save')
            ->willReturnCallback(function (Article $article) use (&$nextId): void {
                $ref = new \ReflectionProperty(Article::class, 'id');
                $ref->setValue($article, $nextId++);
            });
        $articleRepository->method('flush');

        $dispatched = [];
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(static function (mixed $message) use (&$dispatched): bool {
                self::assertInstanceOf(EnrichArticleMessage::class, $message);
                $dispatched[] = $message;

                return true;
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $handler = $this->createHandler(
            articleRepository: $articleRepository,
            fetcher: $fetcher,
            parser: $parser,
            messageBus: $messageBus,
        );

        ($handler)(new FetchSourceMessage(1));

        self::assertCount(2, $dispatched);
        self::assertSame(1, $dispatched[0]->articleId);
        self::assertSame(2, $dispatched[1]->articleId);
    }

    public function testSetsArticlePropertiesFromFeedItem(): void
    {
        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $publishedAt = new \DateTimeImmutable('2026-04-01 10:00:00');

        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $fetcher->method('fetch')->willReturn('<rss>...</rss>');

        $parser = $this->createStub(FeedParserServiceInterface::class);
        $parser->method('parse')->willReturn(new FeedItemCollection([
            new FeedItem('Title', 'https://example.com/1', '<p>Raw</p>', 'Plain text', $publishedAt),
        ]));

        $saved = [];
        $articleRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (Article $article) use (&$saved): void {
                $saved[] = $article;
            });

        $handler = $this->createHandler(
            articleRepository: $articleRepository,
            fetcher: $fetcher,
            parser: $parser,
        );

        ($handler)(new FetchSourceMessage(1));

        self::assertCount(1, $saved);
        $article = $saved[0];
        self::assertSame('<p>Raw</p>', $article->getContentRaw());
        self::assertSame('Plain text', $article->getContentText());
        self::assertSame($publishedAt, $article->getPublishedAt());
        self::assertNotNull($article->getFingerprint());
    }

    private function createSource(): Source
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');

        return new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
    }

    private function createHandler(
        ?ArticleRepositoryInterface $articleRepository = null,
        ?SourceRepositoryInterface $sourceRepository = null,
        ?FeedFetcherServiceInterface $fetcher = null,
        ?FeedParserServiceInterface $parser = null,
        ?DeduplicationServiceInterface $dedup = null,
        ?RuleBasedEnrichmentServiceInterface $enrichment = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null,
    ): FetchSourceHandler {
        if (! $sourceRepository instanceof SourceRepositoryInterface) {
            $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
            $sourceRepository->method('findById')->willReturn($this->source);
        }

        if (! $dedup instanceof DeduplicationServiceInterface) {
            $dedup = $this->createStub(DeduplicationServiceInterface::class);
            $dedup->method('isDuplicate')->willReturn(false);
        }

        if (! $messageBus instanceof MessageBusInterface) {
            $messageBus = $this->createStub(MessageBusInterface::class);
            $messageBus->method('dispatch')->willReturnCallback(
                static fn (object $message): Envelope => new Envelope($message),
            );
        }

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        return new FetchSourceHandler(
            $articleRepository ?? $this->createStub(ArticleRepositoryInterface::class),
            $sourceRepository,
            $em,
            $fetcher ?? $this->createStub(FeedFetcherServiceInterface::class),
            $parser ?? $this->createStub(FeedParserServiceInterface::class),
            $dedup,
            $enrichment ?? $this->createStub(RuleBasedEnrichmentServiceInterface::class),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $messageBus,
            $this->clock,
            $logger ?? new NullLogger(),
        );
    }
}
