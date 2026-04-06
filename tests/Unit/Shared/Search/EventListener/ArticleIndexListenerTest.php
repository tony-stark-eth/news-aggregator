<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Search\EventListener;

use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Shared\Search\EventListener\ArticleIndexListener;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(ArticleIndexListener::class)]
final class ArticleIndexListenerTest extends TestCase
{
    public function testPostPersistIndexesArticle(): void
    {
        $article = $this->createArticle();

        $searchService = $this->createMock(ArticleSearchServiceInterface::class);
        $searchService->expects(self::once())
            ->method('index')
            ->with($article);

        $listener = new ArticleIndexListener($searchService, new NullLogger());
        $listener->postPersist($article);
    }

    public function testPostUpdateIndexesArticle(): void
    {
        $article = $this->createArticle();

        $searchService = $this->createMock(ArticleSearchServiceInterface::class);
        $searchService->expects(self::once())
            ->method('index')
            ->with($article);

        $listener = new ArticleIndexListener($searchService, new NullLogger());
        $listener->postUpdate($article);
    }

    public function testPreRemoveRemovesFromIndex(): void
    {
        $article = $this->createArticle(42);

        $searchService = $this->createMock(ArticleSearchServiceInterface::class);
        $searchService->expects(self::once())
            ->method('remove')
            ->with(42);

        $listener = new ArticleIndexListener($searchService, new NullLogger());
        $listener->preRemove($article);
    }

    public function testPreRemoveSkipsArticleWithoutId(): void
    {
        $article = $this->createArticle(null);

        $searchService = $this->createMock(ArticleSearchServiceInterface::class);
        $searchService->expects(self::never())
            ->method('remove');

        $listener = new ArticleIndexListener($searchService, new NullLogger());
        $listener->preRemove($article);
    }

    public function testPostPersistLogsWarningOnFailure(): void
    {
        $article = $this->createArticle();

        $searchService = $this->createStub(ArticleSearchServiceInterface::class);
        $searchService->method('index')
            ->willThrowException(new \RuntimeException('Index failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $listener = new ArticleIndexListener($searchService, $logger);
        $listener->postPersist($article);
    }

    public function testPreRemoveLogsWarningOnFailure(): void
    {
        $article = $this->createArticle(42);

        $searchService = $this->createStub(ArticleSearchServiceInterface::class);
        $searchService->method('remove')
            ->willThrowException(new \RuntimeException('Remove failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('de-index failed'),
                self::callback(static function (array $ctx): bool {
                    return array_key_exists('id', $ctx)
                        && array_key_exists('error', $ctx)
                        && $ctx['id'] === 42
                        && $ctx['error'] === 'Remove failed';
                }),
            );

        $listener = new ArticleIndexListener($searchService, $logger);
        $listener->preRemove($article);
    }

    public function testPostPersistLogsWarningWithContext(): void
    {
        $article = $this->createArticle();

        $searchService = $this->createStub(ArticleSearchServiceInterface::class);
        $searchService->method('index')
            ->willThrowException(new \RuntimeException('Index failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Search index failed'),
                self::callback(static function (array $ctx): bool {
                    return array_key_exists('event', $ctx)
                        && array_key_exists('title', $ctx)
                        && array_key_exists('error', $ctx)
                        && $ctx['title'] === 'Test Article'
                        && $ctx['error'] === 'Index failed';
                }),
            );

        $listener = new ArticleIndexListener($searchService, $logger);
        $listener->postPersist($article);
    }

    public function testPostUpdateLogsWarningWithContext(): void
    {
        $article = $this->createArticle();

        $searchService = $this->createStub(ArticleSearchServiceInterface::class);
        $searchService->method('index')
            ->willThrowException(new \RuntimeException('Update index failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Search index failed'),
                self::callback(static function (array $ctx): bool {
                    return array_key_exists('event', $ctx)
                        && array_key_exists('title', $ctx)
                        && array_key_exists('error', $ctx)
                        && $ctx['error'] === 'Update index failed';
                }),
            );

        $listener = new ArticleIndexListener($searchService, $logger);
        $listener->postUpdate($article);
    }

    public function testPreRemoveWithNullIdReturnsEarly(): void
    {
        // Kills ReturnRemoval on the null id check
        $article = $this->createArticle(null);

        $searchService = $this->createMock(ArticleSearchServiceInterface::class);
        $searchService->expects(self::never())->method('remove');
        $searchService->expects(self::never())->method('index');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $listener = new ArticleIndexListener($searchService, $logger);
        $listener->preRemove($article);
    }

    private function createArticle(?int $id = 1): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test Source', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Test Article', 'https://example.com/1', $source, new \DateTimeImmutable());

        if ($id !== null) {
            $ref = new \ReflectionProperty(Article::class, 'id');
            $ref->setValue($article, $id);
        }

        return $article;
    }
}
