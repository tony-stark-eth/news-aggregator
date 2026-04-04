<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Entity;

use App\Article\Entity\Article;
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

    public function testSetScore(): void
    {
        $article = $this->createArticle();

        $article->setScore(0.85);

        self::assertSame(0.85, $article->getScore());
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
