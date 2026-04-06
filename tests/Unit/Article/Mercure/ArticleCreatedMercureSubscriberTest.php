<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Mercure;

use App\Article\Entity\Article;
use App\Article\Event\ArticleCreated;
use App\Article\Mercure\ArticleCreatedMercureSubscriber;
use App\Article\Mercure\MercurePublisherServiceInterface;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArticleCreatedMercureSubscriber::class)]
#[UsesClass(ArticleCreated::class)]
final class ArticleCreatedMercureSubscriberTest extends TestCase
{
    public function testSubscribesToArticleCreatedEvent(): void
    {
        $events = ArticleCreatedMercureSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ArticleCreated::class, $events);
        self::assertSame('onArticleCreated', $events[ArticleCreated::class]);
    }

    public function testOnArticleCreatedPublishesMercureUpdate(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Test', 'https://example.com/1', $source, new \DateTimeImmutable());

        $mercure = $this->createMock(MercurePublisherServiceInterface::class);
        $mercure->expects(self::once())
            ->method('publishArticleCreated')
            ->with($article);

        $subscriber = new ArticleCreatedMercureSubscriber($mercure);
        $subscriber->onArticleCreated(new ArticleCreated($article));
    }
}
