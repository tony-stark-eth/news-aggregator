<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Entity;

use App\Article\Entity\Article;
use App\Article\ValueObject\EnrichmentStatus;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Article::class)]
final class ArticleTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $article = $this->createArticle();

        self::assertNull($article->getId());
        self::assertSame('Test Article Title', $article->getTitle());
        self::assertSame('https://example.com/article/1', $article->getUrl());
        self::assertSame('Ars Technica', $article->getSource()->getName());
        self::assertNull($article->getContentRaw());
        self::assertNull($article->getContentText());
        self::assertNull($article->getSummary());
        self::assertNull($article->getFingerprint());
        self::assertNull($article->getScore());
        self::assertNull($article->getCategory());
        self::assertNull($article->getEnrichmentMethod());
        self::assertNull($article->getAiModelUsed());
        self::assertNull($article->getPublishedAt());
    }

    public function testSetContent(): void
    {
        $article = $this->createArticle();

        $article->setContentRaw('<p>Hello <b>World</b></p>');
        $article->setContentText('Hello World');

        self::assertSame('<p>Hello <b>World</b></p>', $article->getContentRaw());
        self::assertSame('Hello World', $article->getContentText());
    }

    public function testSetEnrichment(): void
    {
        $article = $this->createArticle();

        $article->setEnrichmentMethod(EnrichmentMethod::Ai);
        $article->setAiModelUsed('openrouter/free');
        $article->setSummary('A brief summary.');

        self::assertSame(EnrichmentMethod::Ai, $article->getEnrichmentMethod());
        self::assertSame('openrouter/free', $article->getAiModelUsed());
        self::assertSame('A brief summary.', $article->getSummary());
    }

    public function testSetCategory(): void
    {
        $article = $this->createArticle();
        $category = new Category('Science', 'science', 8, '#10B981');

        $article->setCategory($category);

        self::assertSame('Science', $article->getCategory()?->getName());
    }

    public function testSetScoreValidRange(): void
    {
        $article = $this->createArticle();

        $article->setScore(0.85);
        self::assertSame(0.85, $article->getScore());
    }

    public function testSetScoreAcceptsNull(): void
    {
        $article = $this->createArticle();

        $article->setScore(null);
        self::assertNull($article->getScore());
    }

    public function testSetScoreAcceptsBoundaryZero(): void
    {
        $article = $this->createArticle();

        $article->setScore(0.0);
        self::assertSame(0.0, $article->getScore());
    }

    public function testSetScoreAcceptsBoundaryOne(): void
    {
        $article = $this->createArticle();

        $article->setScore(1.0);
        self::assertSame(1.0, $article->getScore());
    }

    public function testSetScoreRejectsAboveOne(): void
    {
        $article = $this->createArticle();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 0.0 and 1.0');
        $article->setScore(1.01);
    }

    public function testSetScoreRejectsBelowZero(): void
    {
        $article = $this->createArticle();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 0.0 and 1.0');
        $article->setScore(-0.1);
    }

    public function testEnrichmentStatusTransitionNullToPending(): void
    {
        $article = $this->createArticle();

        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        self::assertSame(EnrichmentStatus::Pending, $article->getEnrichmentStatus());
    }

    public function testEnrichmentStatusTransitionPendingToComplete(): void
    {
        $article = $this->createArticle();
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);

        $article->setEnrichmentStatus(EnrichmentStatus::Complete);
        self::assertSame(EnrichmentStatus::Complete, $article->getEnrichmentStatus());
    }

    public function testEnrichmentStatusRejectsNullToComplete(): void
    {
        $article = $this->createArticle();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid enrichment status transition from null to complete');
        $article->setEnrichmentStatus(EnrichmentStatus::Complete);
    }

    public function testEnrichmentStatusRejectsCompleteToPending(): void
    {
        $article = $this->createArticle();
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        $article->setEnrichmentStatus(EnrichmentStatus::Complete);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid enrichment status transition from complete to pending');
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
    }

    public function testEnrichmentStatusRejectsPendingToPending(): void
    {
        $article = $this->createArticle();
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid enrichment status transition from pending to pending');
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
    }

    public function testResetEnrichmentStatusAllowsReEnqueue(): void
    {
        $article = $this->createArticle();
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        $article->setEnrichmentStatus(EnrichmentStatus::Complete);

        $article->resetEnrichmentStatus();
        self::assertNull($article->getEnrichmentStatus());

        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        self::assertSame(EnrichmentStatus::Pending, $article->getEnrichmentStatus());
    }

    public function testSetFingerprint(): void
    {
        $article = $this->createArticle();

        $article->setFingerprint('abc123def456');

        self::assertSame('abc123def456', $article->getFingerprint());
    }

    private function createArticle(): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Ars Technica', 'https://example.com/feed', $category, new \DateTimeImmutable());

        return new Article(
            'Test Article Title',
            'https://example.com/article/1',
            $source,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
    }
}
