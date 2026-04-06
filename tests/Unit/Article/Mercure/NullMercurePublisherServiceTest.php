<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Mercure;

use App\Article\Entity\Article;
use App\Article\Mercure\NullMercurePublisherService;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullMercurePublisherService::class)]
final class NullMercurePublisherServiceTest extends TestCase
{
    /**
     * No-op methods must complete without error. The expectation
     * is tested by the mock framework: no method call expected, none made.
     */
    public function testPublishArticleCreatedCompletesWithoutSideEffects(): void
    {
        $article = $this->createArticle();
        $service = new NullMercurePublisherService();

        $service->publishArticleCreated($article);

        // Reaching this point proves no exception was thrown.
        $this->expectNotToPerformAssertions();
    }

    public function testPublishEnrichmentCompleteCompletesWithoutSideEffects(): void
    {
        $article = $this->createArticle();
        $service = new NullMercurePublisherService();

        $service->publishEnrichmentComplete($article);

        $this->expectNotToPerformAssertions();
    }

    private function createArticle(): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        return new Article('Test', 'https://example.com/1', $source, new \DateTimeImmutable());
    }
}
