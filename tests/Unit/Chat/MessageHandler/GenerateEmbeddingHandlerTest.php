<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\ValueObject\Url;
use App\Chat\Message\GenerateEmbeddingMessage;
use App\Chat\MessageHandler\GenerateEmbeddingHandler;
use App\Chat\Service\EmbeddingServiceInterface;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(GenerateEmbeddingHandler::class)]
#[UsesClass(GenerateEmbeddingMessage::class)]
#[UsesClass(Article::class)]
#[UsesClass(Url::class)]
final class GenerateEmbeddingHandlerTest extends TestCase
{
    public function testHandlerStoresEmbeddingOnSuccess(): void
    {
        $article = $this->createArticle(42, 'Test Title', 'Test Summary', ['php', 'symfony']);
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with(42)->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article, flush: true);

        $embeddingService = $this->createMock(EmbeddingServiceInterface::class);
        $embeddingService->expects(self::once())->method('embed')
            ->with('Test Title Test Summary php symfony')
            ->willReturn([0.1, 0.2, 0.3]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with(
                self::stringContains('Stored embedding'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42),
            );

        $handler = new GenerateEmbeddingHandler($repository, $embeddingService, $logger);
        $handler(new GenerateEmbeddingMessage(42));

        self::assertSame('[0.1,0.2,0.3]', $article->getEmbedding());
    }

    public function testHandlerLogsWarningWhenArticleNotFound(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with(99)->willReturn(null);
        $repository->expects(self::never())->method('save');

        $embeddingService = $this->createMock(EmbeddingServiceInterface::class);
        $embeddingService->expects(self::never())->method('embed');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Article not found'),
                self::callback(static fn (array $ctx): bool => $ctx['article_id'] === 99),
            );

        $handler = new GenerateEmbeddingHandler($repository, $embeddingService, $logger);
        $handler(new GenerateEmbeddingMessage(99));
    }

    public function testHandlerDoesNotSaveWhenEmbeddingIsNull(): void
    {
        $article = $this->createArticle(42, 'Title Only', null, null);
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with(42)->willReturn($article);
        $repository->expects(self::never())->method('save');

        $embeddingService = $this->createMock(EmbeddingServiceInterface::class);
        $embeddingService->expects(self::once())->method('embed')
            ->with('Title Only')
            ->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with(
                self::stringContains('Embedding generation returned null'),
                self::callback(static fn (array $ctx): bool => $ctx['id'] === 42),
            );

        $handler = new GenerateEmbeddingHandler($repository, $embeddingService, $logger);
        $handler(new GenerateEmbeddingMessage(42));

        self::assertNull($article->getEmbedding());
    }

    public function testHandlerBuildsTextWithTitleOnly(): void
    {
        $article = $this->createArticle(1, 'Just a Title', null, null);
        $repository = $this->createStub(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);

        $embeddingService = $this->createMock(EmbeddingServiceInterface::class);
        $embeddingService->expects(self::once())->method('embed')
            ->with('Just a Title')
            ->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $handler = new GenerateEmbeddingHandler($repository, $embeddingService, $logger);
        $handler(new GenerateEmbeddingMessage(1));
    }

    public function testHandlerBuildsTextWithEmptySummary(): void
    {
        $article = $this->createArticle(1, 'Title', '', ['keyword']);
        $repository = $this->createStub(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);

        $embeddingService = $this->createMock(EmbeddingServiceInterface::class);
        $embeddingService->expects(self::once())->method('embed')
            ->with('Title keyword')
            ->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $handler = new GenerateEmbeddingHandler($repository, $embeddingService, $logger);
        $handler(new GenerateEmbeddingMessage(1));
    }

    public function testHandlerBuildsTextWithEmptyKeywords(): void
    {
        $article = $this->createArticle(1, 'Title', 'Summary', []);
        $repository = $this->createStub(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);

        $embeddingService = $this->createMock(EmbeddingServiceInterface::class);
        $embeddingService->expects(self::once())->method('embed')
            ->with('Title Summary')
            ->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $handler = new GenerateEmbeddingHandler($repository, $embeddingService, $logger);
        $handler(new GenerateEmbeddingMessage(1));
    }

    /**
     * @param list<string>|null $keywords
     */
    private function createArticle(int $id, string $title, ?string $summary, ?array $keywords): Article
    {
        $source = $this->createStub(Source::class);
        $article = new Article($title, 'https://example.com/article-' . $id, $source, new \DateTimeImmutable());
        $article->setSummary($summary);
        $article->setKeywords($keywords);

        // Set the private id field via reflection
        $reflection = new \ReflectionClass($article);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($article, $id);

        return $article;
    }
}
