<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Service;

use App\Article\Entity\Article;
use App\Chat\Service\ArticleContextFormatter;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArticleContextFormatter::class)]
final class ArticleContextFormatterTest extends TestCase
{
    private ArticleContextFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ArticleContextFormatter();
    }

    public function testFormatSortsByScoreDescending(): void
    {
        $articles = [
            $this->createArticle(1, 'Low', 'https://example.com/1'),
            $this->createArticle(2, 'High', 'https://example.com/2'),
        ];

        $scores = [
            1 => 0.5,
            2 => 0.9,
        ];
        $results = $this->formatter->format($articles, $scores);

        self::assertCount(2, $results);
        self::assertSame(2, $results[0]['id']);
        self::assertSame(0.9, $results[0]['score']);
        self::assertSame(1, $results[1]['id']);
        self::assertSame(0.5, $results[1]['score']);
    }

    public function testFormatSkipsArticlesWithNullId(): void
    {
        $source = $this->createStub(Source::class);
        $source->method('getName')->willReturn('Source');
        $article = new Article('Title', 'https://example.com/x', $source, new \DateTimeImmutable());

        $results = $this->formatter->format([$article], []);

        self::assertSame([], $results);
    }

    public function testFormatUsesDefaultScoreWhenNotInMap(): void
    {
        $article = $this->createArticle(1, 'Test', 'https://example.com/1');
        $results = $this->formatter->format([$article], []);

        self::assertCount(1, $results);
        self::assertSame(0.0, $results[0]['score']);
    }

    public function testFormatIncludesAllFields(): void
    {
        $article = $this->createArticle(1, 'Title', 'https://example.com/1');
        $article->setSummary('Summary text');
        $article->setKeywords(['k1', 'k2']);
        $article->setPublishedAt(new \DateTimeImmutable('2026-04-09T08:00:00+00:00'));

        $results = $this->formatter->format([$article], [
            1 => 0.85,
        ]);

        self::assertCount(1, $results);
        $r = $results[0];
        self::assertSame(1, $r['id']);
        self::assertSame('Title', $r['title']);
        self::assertSame('Summary text', $r['summary']);
        self::assertSame(['k1', 'k2'], $r['keywords']);
        self::assertSame('2026-04-09T08:00:00+00:00', $r['publishedAt']);
        self::assertSame('https://example.com/1', $r['url']);
        self::assertSame(0.85, $r['score']);
    }

    public function testFormatWithNullSummaryKeywordsPublishedAt(): void
    {
        $article = $this->createArticle(1, 'Title', 'https://example.com/1');

        $results = $this->formatter->format([$article], [
            1 => 0.5,
        ]);

        self::assertNull($results[0]['summary']);
        self::assertSame([], $results[0]['keywords']);
        self::assertNull($results[0]['publishedAt']);
    }

    public function testFormatEmptyArticlesReturnsEmpty(): void
    {
        self::assertSame([], $this->formatter->format([], []));
    }

    public function testFormatRoundsScoreToFourDecimals(): void
    {
        $article = $this->createArticle(1, 'T', 'https://example.com/1');
        $results = $this->formatter->format([$article], [
            1 => 0.123456789,
        ]);

        self::assertSame(0.1235, $results[0]['score']);
    }

    private function createArticle(int $id, string $title, string $url): Article
    {
        $source = $this->createStub(Source::class);
        $source->method('getName')->willReturn('Source');
        $article = new Article($title, $url, $source, new \DateTimeImmutable('2026-04-09'));

        $reflection = new \ReflectionProperty(Article::class, 'id');
        $reflection->setValue($article, $id);

        return $article;
    }
}
