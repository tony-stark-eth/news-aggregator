<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\EventListener;

use App\Article\Entity\Article;
use App\Article\ValueObject\Url;
use App\Chat\EventListener\ArticleEmbeddingListener;
use App\Chat\Message\GenerateEmbeddingMessage;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ArticleEmbeddingListener::class)]
#[UsesClass(GenerateEmbeddingMessage::class)]
#[UsesClass(Article::class)]
#[UsesClass(Url::class)]
final class ArticleEmbeddingListenerTest extends TestCase
{
    public function testPostPersistDispatchesMessageForNewArticle(): void
    {
        $article = $this->createArticleWithId(42);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (GenerateEmbeddingMessage $msg): bool => $msg->articleId === 42))
            ->willReturn(new Envelope(new GenerateEmbeddingMessage(42)));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $listener = new ArticleEmbeddingListener($bus, $logger);
        $listener->postPersist($article);
    }

    public function testPostUpdateDispatchesMessageForArticleWithoutEmbedding(): void
    {
        $article = $this->createArticleWithId(42);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (GenerateEmbeddingMessage $msg): bool => $msg->articleId === 42))
            ->willReturn(new Envelope(new GenerateEmbeddingMessage(42)));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $listener = new ArticleEmbeddingListener($bus, $logger);
        $listener->postUpdate($article);
    }

    public function testSkipsDispatchWhenArticleAlreadyHasEmbedding(): void
    {
        $article = $this->createArticleWithId(42);
        $article->setEmbedding('[0.1,0.2,0.3]');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);

        $listener = new ArticleEmbeddingListener($bus, $logger);
        $listener->postPersist($article);
    }

    public function testSkipsDispatchWhenArticleHasNoId(): void
    {
        $source = $this->createStub(Source::class);
        $article = new Article('Test', 'https://example.com/test', $source, new \DateTimeImmutable());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);

        $listener = new ArticleEmbeddingListener($bus, $logger);
        $listener->postPersist($article);
    }

    public function testLogsWarningOnDispatchFailure(): void
    {
        $article = $this->createArticleWithId(42);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willThrowException(new \RuntimeException('Queue down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Failed to dispatch embedding message'),
                self::callback(static fn (array $ctx): bool => $ctx['event'] === 'postPersist'
                    && $ctx['id'] === 42
                    && $ctx['error'] === 'Queue down'),
            );

        $listener = new ArticleEmbeddingListener($bus, $logger);
        $listener->postPersist($article);
    }

    public function testPostUpdateLogsCorrectEventName(): void
    {
        $article = $this->createArticleWithId(42);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willThrowException(new \RuntimeException('fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::anything(),
                self::callback(static fn (array $ctx): bool => $ctx['event'] === 'postUpdate'),
            );

        $listener = new ArticleEmbeddingListener($bus, $logger);
        $listener->postUpdate($article);
    }

    private function createArticleWithId(int $id): Article
    {
        $source = $this->createStub(Source::class);
        $article = new Article('Test Article', 'https://example.com/article-' . $id, $source, new \DateTimeImmutable());

        $reflection = new \ReflectionClass($article);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($article, $id);

        return $article;
    }
}
