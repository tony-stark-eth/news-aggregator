<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Mercure\MercurePublisherServiceInterface;
use App\Article\Message\EnrichArticleMessage;
use App\Article\MessageHandler\EnrichArticleHandler;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\ValueObject\EnrichmentStatus;
use App\Enrichment\Service\ArticleEnrichmentServiceInterface;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(EnrichArticleHandler::class)]
#[UsesClass(EnrichArticleMessage::class)]
#[UsesClass(EnrichmentStatus::class)]
#[UsesClass(FeedItem::class)]
final class EnrichArticleHandlerTest extends TestCase
{
    public function testEnrichesArticleAndSetsStatusComplete(): void
    {
        $article = $this->createArticleWithStatus(EnrichmentStatus::Pending);

        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepository->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($article);
        $articleRepository->expects(self::once())->method('flush');

        $enrichment = $this->createMock(ArticleEnrichmentServiceInterface::class);
        $enrichment->expects(self::once())
            ->method('enrich')
            ->with(
                $article,
                self::callback(static fn (FeedItem $item): bool => $item->title === 'Test Article' && $item->url === 'https://example.com/1'),
                $article->getSource(),
            );

        $mercure = $this->createMock(MercurePublisherServiceInterface::class);
        $mercure->expects(self::once())
            ->method('publishEnrichmentComplete')
            ->with($article);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('enrichment complete'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42),
            );

        $handler = new EnrichArticleHandler($articleRepository, $enrichment, $mercure, $logger);
        ($handler)(new EnrichArticleMessage(42));

        self::assertSame(EnrichmentStatus::Complete, $article->getEnrichmentStatus());
    }

    public function testSkipsWhenArticleNotFound(): void
    {
        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepository->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);
        $articleRepository->expects(self::never())->method('flush');

        $enrichment = $this->createMock(ArticleEnrichmentServiceInterface::class);
        $enrichment->expects(self::never())->method('enrich');

        $mercure = $this->createMock(MercurePublisherServiceInterface::class);
        $mercure->expects(self::never())->method('publishEnrichmentComplete');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('not found'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 99),
            );

        $handler = new EnrichArticleHandler($articleRepository, $enrichment, $mercure, $logger);
        ($handler)(new EnrichArticleMessage(99));
    }

    public function testSkipsWhenStatusIsAlreadyComplete(): void
    {
        $article = $this->createArticleWithStatus(EnrichmentStatus::Complete);

        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepository->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($article);
        $articleRepository->expects(self::never())->method('flush');

        $enrichment = $this->createMock(ArticleEnrichmentServiceInterface::class);
        $enrichment->expects(self::never())->method('enrich');

        $mercure = $this->createMock(MercurePublisherServiceInterface::class);
        $mercure->expects(self::never())->method('publishEnrichmentComplete');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with(
                self::stringContains('already complete'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42),
            );

        $handler = new EnrichArticleHandler($articleRepository, $enrichment, $mercure, $logger);
        ($handler)(new EnrichArticleMessage(42));
    }

    public function testSkipsWhenStatusIsNullLegacyComplete(): void
    {
        $article = $this->createArticleWithStatus(null);

        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepository->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($article);
        $articleRepository->expects(self::never())->method('flush');

        $enrichment = $this->createMock(ArticleEnrichmentServiceInterface::class);
        $enrichment->expects(self::never())->method('enrich');

        $mercure = $this->createMock(MercurePublisherServiceInterface::class);
        $mercure->expects(self::never())->method('publishEnrichmentComplete');

        $handler = new EnrichArticleHandler($articleRepository, $enrichment, $mercure, new NullLogger());
        ($handler)(new EnrichArticleMessage(42));
    }

    public function testReconstructsFeedItemWithOriginalTitle(): void
    {
        $article = $this->createArticleWithStatus(EnrichmentStatus::Pending);
        $article->setTitleOriginal('Original Title');

        $articleRepository = $this->createStub(ArticleRepositoryInterface::class);
        $articleRepository->method('findById')->willReturn($article);

        $enrichment = $this->createMock(ArticleEnrichmentServiceInterface::class);
        $enrichment->expects(self::once())
            ->method('enrich')
            ->with(
                $article,
                self::callback(static fn (FeedItem $item): bool => $item->title === 'Original Title'),
                $article->getSource(),
            );

        $mercure = $this->createStub(MercurePublisherServiceInterface::class);

        $handler = new EnrichArticleHandler($articleRepository, $enrichment, $mercure, new NullLogger());
        ($handler)(new EnrichArticleMessage(42));
    }

    private function createArticleWithStatus(?EnrichmentStatus $status): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Test Article', 'https://example.com/1', $source, new \DateTimeImmutable());
        $article->setContentRaw('<p>Content</p>');
        $article->setContentText('Content text');

        if ($status === EnrichmentStatus::Pending) {
            $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        } elseif ($status === EnrichmentStatus::Complete) {
            $article->setEnrichmentStatus(EnrichmentStatus::Pending);
            $article->setEnrichmentStatus(EnrichmentStatus::Complete);
        }

        // Set the article ID via reflection so findById can return it
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, 42);

        return $article;
    }
}
