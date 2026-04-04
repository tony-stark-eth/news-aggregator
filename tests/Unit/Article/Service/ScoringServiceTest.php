<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringService;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ScoringService::class)]
final class ScoringServiceTest extends TestCase
{
    private MockClock $clock;

    private ScoringService $service;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-04-04 12:00:00');
        $this->service = new ScoringService($this->clock);
    }

    public function testScoreReturnsBetweenZeroAndOne(): void
    {
        $article = $this->createArticle();
        $score = $this->service->score($article);

        self::assertGreaterThanOrEqual(0.0, $score);
        self::assertLessThanOrEqual(1.0, $score);
    }

    public function testRecentArticleScoresHigherThanOld(): void
    {
        $recent = $this->createArticle(publishedAt: '2026-04-04 11:30:00');
        $old = $this->createArticle(publishedAt: '2026-04-01 12:00:00');

        self::assertGreaterThan(
            $this->service->score($old),
            $this->service->score($recent),
        );
    }

    public function testHighWeightCategoryScoresHigher(): void
    {
        $high = $this->createArticle(categoryWeight: 10);
        $low = $this->createArticle(categoryWeight: 2);

        self::assertGreaterThan(
            $this->service->score($low),
            $this->service->score($high),
        );
    }

    public function testAiEnrichedArticleScoresHigher(): void
    {
        $ai = $this->createArticle(enrichment: EnrichmentMethod::Ai);
        $ruleBased = $this->createArticle(enrichment: EnrichmentMethod::RuleBased);

        self::assertGreaterThan(
            $this->service->score($ruleBased),
            $this->service->score($ai),
        );
    }

    public function testVeryOldArticleGetsLowScore(): void
    {
        $article = $this->createArticle(publishedAt: '2026-03-20 12:00:00');
        $score = $this->service->score($article);

        // 15 days old, should have very low recency contribution
        self::assertLessThan(0.6, $score);
    }

    public function testUncategorizedArticleGetsMidScore(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article(
            'Test',
            'https://example.com/uncategorized',
            $source,
            new \DateTimeImmutable('2026-04-04 11:00:00'),
        );
        $article->setPublishedAt(new \DateTimeImmutable('2026-04-04 11:00:00'));
        // No category set, no enrichment

        $score = $this->service->score($article);
        self::assertGreaterThan(0.0, $score);
        self::assertLessThan(1.0, $score);
    }

    private function createArticle(
        int $categoryWeight = 10,
        string $publishedAt = '2026-04-04 11:00:00',
        ?EnrichmentMethod $enrichment = EnrichmentMethod::RuleBased,
    ): Article {
        $category = new Category('Tech', 'tech', $categoryWeight, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article(
            'Test Article',
            'https://example.com/article/' . random_int(1, 99999),
            $source,
            new \DateTimeImmutable($publishedAt),
        );
        $article->setPublishedAt(new \DateTimeImmutable($publishedAt));
        $article->setCategory($category);
        $article->setEnrichmentMethod($enrichment);

        return $article;
    }
}
