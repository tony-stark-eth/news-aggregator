<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Message\EnrichArticleMessage;
use App\Article\Message\FetchFullTextMessage;
use App\Article\MessageHandler\FetchFullTextHandler;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\ArticleContentFetcherServiceInterface;
use App\Article\Service\DomainRateLimiterServiceInterface;
use App\Article\Service\ReadabilityExtractorServiceInterface;
use App\Article\ValueObject\FullTextStatus;
use App\Article\ValueObject\ReadabilityResult;
use App\Article\ValueObject\Url;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(FetchFullTextHandler::class)]
#[UsesClass(FetchFullTextMessage::class)]
#[UsesClass(EnrichArticleMessage::class)]
#[UsesClass(FullTextStatus::class)]
#[UsesClass(ReadabilityResult::class)]
#[UsesClass(Url::class)]
final class FetchFullTextHandlerTest extends TestCase
{
    private Article $article;

    private Source $source;

    protected function setUp(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $this->source = new Source('Test Source', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $this->article = new Article('Test Article', 'https://example.com/article', $this->source, new \DateTimeImmutable());

        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($this->article, 42);

        $this->article->setFullTextStatus(FullTextStatus::Pending);
    }

    public function testFetchesFullTextAndUpdatesArticle(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);
        $articleRepo->expects(self::once())->method('flush');

        $contentFetcher = $this->createMock(ArticleContentFetcherServiceInterface::class);
        $contentFetcher->expects(self::once())
            ->method('fetch')
            ->with('https://example.com/article')
            ->willReturn('<html><body><p>Full article text content here</p></body></html>');

        $extractor = $this->createMock(ReadabilityExtractorServiceInterface::class);
        $extractor->expects(self::once())
            ->method('extract')
            ->willReturn(new ReadabilityResult('Full article text content here', '<p>Full article text content here</p>', true));

        $rateLimiter = $this->createMock(DomainRateLimiterServiceInterface::class);
        $rateLimiter->expects(self::once())
            ->method('waitForDomain')
            ->with('https://example.com/article');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (mixed $message): bool {
                self::assertInstanceOf(EnrichArticleMessage::class, $message);
                self::assertSame(42, $message->articleId);

                return true;
            }))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('Full-text fetched'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42 && $ctx['url'] === 'https://example.com/article'),
            );

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            contentFetcher: $contentFetcher,
            readabilityExtractor: $extractor,
            domainRateLimiter: $rateLimiter,
            messageBus: $messageBus,
            logger: $logger,
        );

        ($handler)(new FetchFullTextMessage(42));

        self::assertSame(FullTextStatus::Fetched, $this->article->getFullTextStatus());
        self::assertSame('Full article text content here', $this->article->getContentFullText());
        self::assertSame('<p>Full article text content here</p>', $this->article->getContentFullHtml());
        self::assertSame('Full article text content here', $this->article->getContentText());
    }

    public function testSkipsWhenArticleNotFound(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn(null);
        $articleRepo->expects(self::never())->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            messageBus: $messageBus,
        );

        ($handler)(new FetchFullTextMessage(999));
    }

    public function testSkipsWhenNotPendingStatus(): void
    {
        $this->article->setFullTextStatus(FullTextStatus::Fetched);

        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);
        $articleRepo->expects(self::never())->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            messageBus: $messageBus,
        );

        ($handler)(new FetchFullTextMessage(42));
    }

    public function testSkipsWhenGlobalFullTextDisabled(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);
        $articleRepo->expects(self::once())->method('flush');

        $contentFetcher = $this->createMock(ArticleContentFetcherServiceInterface::class);
        $contentFetcher->expects(self::never())->method('fetch');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $msg): bool => $msg instanceof EnrichArticleMessage && $msg->articleId === 42))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            contentFetcher: $contentFetcher,
            messageBus: $messageBus,
            fullTextEnabled: false,
        );

        ($handler)(new FetchFullTextMessage(42));

        self::assertSame(FullTextStatus::Skipped, $this->article->getFullTextStatus());
    }

    public function testSkipsWhenSourceFullTextDisabled(): void
    {
        $this->source->setFullTextEnabled(false);

        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);
        $articleRepo->expects(self::once())->method('flush');

        $contentFetcher = $this->createMock(ArticleContentFetcherServiceInterface::class);
        $contentFetcher->expects(self::never())->method('fetch');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $msg): bool => $msg instanceof EnrichArticleMessage && $msg->articleId === 42))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            contentFetcher: $contentFetcher,
            messageBus: $messageBus,
        );

        ($handler)(new FetchFullTextMessage(42));

        self::assertSame(FullTextStatus::Skipped, $this->article->getFullTextStatus());
    }

    public function testSetsFailedStatusOnExtractionFailure(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);
        $articleRepo->expects(self::once())->method('flush');

        $contentFetcher = $this->createMock(ArticleContentFetcherServiceInterface::class);
        $contentFetcher->expects(self::once())
            ->method('fetch')
            ->willReturn('<html><body>short</body></html>');

        $extractor = $this->createMock(ReadabilityExtractorServiceInterface::class);
        $extractor->expects(self::once())
            ->method('extract')
            ->willReturn(new ReadabilityResult(null, null, false));

        $rateLimiter = $this->createMock(DomainRateLimiterServiceInterface::class);
        $rateLimiter->expects(self::once())->method('waitForDomain');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $msg): bool => $msg instanceof EnrichArticleMessage))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('extraction failed'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42 && $ctx['url'] === 'https://example.com/article'),
            );

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            contentFetcher: $contentFetcher,
            readabilityExtractor: $extractor,
            domainRateLimiter: $rateLimiter,
            messageBus: $messageBus,
            logger: $logger,
        );

        ($handler)(new FetchFullTextMessage(42));

        self::assertSame(FullTextStatus::Failed, $this->article->getFullTextStatus());
        self::assertNull($this->article->getContentFullText());
    }

    public function testSetsFailedStatusOnFetchException(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);
        $articleRepo->expects(self::once())->method('flush');

        $contentFetcher = $this->createMock(ArticleContentFetcherServiceInterface::class);
        $contentFetcher->expects(self::once())
            ->method('fetch')
            ->willThrowException(new \RuntimeException('HTTP 403'));

        $rateLimiter = $this->createMock(DomainRateLimiterServiceInterface::class);
        $rateLimiter->expects(self::once())->method('waitForDomain');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $msg): bool => $msg instanceof EnrichArticleMessage))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('fetch error'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42
                    && $ctx['url'] === 'https://example.com/article'
                    && $ctx['error'] === 'HTTP 403'),
            );

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            contentFetcher: $contentFetcher,
            domainRateLimiter: $rateLimiter,
            messageBus: $messageBus,
            logger: $logger,
        );

        ($handler)(new FetchFullTextMessage(42));

        self::assertSame(FullTextStatus::Failed, $this->article->getFullTextStatus());
    }

    public function testAlwaysDispatchesEnrichEvenOnFailure(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->method('findById')->willReturn($this->article);

        $contentFetcher = $this->createMock(ArticleContentFetcherServiceInterface::class);
        $contentFetcher->method('fetch')
            ->willThrowException(new \RuntimeException('timeout'));

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (mixed $msg): bool => $msg instanceof EnrichArticleMessage && $msg->articleId === 42))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $handler = $this->createHandler(
            articleRepository: $articleRepo,
            contentFetcher: $contentFetcher,
            messageBus: $messageBus,
        );

        ($handler)(new FetchFullTextMessage(42));
    }

    private function createHandler(
        ?ArticleRepositoryInterface $articleRepository = null,
        ?ArticleContentFetcherServiceInterface $contentFetcher = null,
        ?ReadabilityExtractorServiceInterface $readabilityExtractor = null,
        ?DomainRateLimiterServiceInterface $domainRateLimiter = null,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null,
        bool $fullTextEnabled = true,
    ): FetchFullTextHandler {
        if (! $messageBus instanceof MessageBusInterface) {
            $messageBus = $this->createStub(MessageBusInterface::class);
            $messageBus->method('dispatch')->willReturnCallback(
                static fn (object $message): Envelope => new Envelope($message),
            );
        }

        return new FetchFullTextHandler(
            $articleRepository ?? $this->createStub(ArticleRepositoryInterface::class),
            $contentFetcher ?? $this->createStub(ArticleContentFetcherServiceInterface::class),
            $readabilityExtractor ?? $this->createStub(ReadabilityExtractorServiceInterface::class),
            $domainRateLimiter ?? $this->createStub(DomainRateLimiterServiceInterface::class),
            $messageBus,
            $logger ?? $this->createStub(LoggerInterface::class),
            $fullTextEnabled,
        );
    }
}
