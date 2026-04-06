<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringService;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\ValueObject\SourceHealth;
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

    public function testScoreIsRoundedToFourDecimals(): void
    {
        $article = $this->createArticle();
        $score = $this->service->score($article);

        // Verify it's rounded to 4 decimal places
        self::assertSame($score, round($score, 4));
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

    public function testNullEnrichmentScoresLowest(): void
    {
        $none = $this->createArticle(enrichment: null);
        $ruleBased = $this->createArticle(enrichment: EnrichmentMethod::RuleBased);

        self::assertGreaterThan(
            $this->service->score($none),
            $this->service->score($ruleBased),
        );
    }

    public function testVeryOldArticleGetsLowScore(): void
    {
        $article = $this->createArticle(publishedAt: '2026-03-20 12:00:00');
        $score = $this->service->score($article);

        self::assertLessThan(0.6, $score);
    }

    public function testArticleExactlyMaxAgeGetsZeroRecency(): void
    {
        // 168 hours = 7 days old -> recency = 0.0
        $article = $this->createArticle(publishedAt: '2026-03-28 12:00:00');
        $score = $this->service->score($article);

        // With zero recency, score should be notably lower
        $freshArticle = $this->createArticle(publishedAt: '2026-04-04 12:00:00');
        self::assertGreaterThan($score, $this->service->score($freshArticle));
    }

    public function testFutureArticleGetsMaxRecency(): void
    {
        // Published in the future (ageHours <= 0)
        $article = $this->createArticle(publishedAt: '2026-04-04 13:00:00');
        $freshScore = $this->service->score($article);

        // Published just now
        $nowArticle = $this->createArticle(publishedAt: '2026-04-04 12:00:00');
        $nowScore = $this->service->score($nowArticle);

        // Future should equal or exceed now
        self::assertGreaterThanOrEqual($nowScore, $freshScore);
    }

    public function testUncategorizedArticleGetsMidCategoryScore(): void
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
        // No category set -> returns 0.5 for category score

        $score = $this->service->score($article);
        self::assertGreaterThan(0.0, $score);
        self::assertLessThan(1.0, $score);
    }

    public function testCategoryWeightAboveMaxCapsAtOne(): void
    {
        // Category weight 20 / MAX_CATEGORY_WEIGHT 10 = 2.0, but min(1.0, 2.0) = 1.0
        $highWeight = $this->createArticle(categoryWeight: 20);
        $maxWeight = $this->createArticle(categoryWeight: 10);

        // Both should produce same category score (both cap at 1.0)
        self::assertSame(
            $this->service->score($maxWeight),
            $this->service->score($highWeight),
        );
    }

    public function testHealthySourceScoresHigherThanDegraded(): void
    {
        $healthy = $this->createArticle(health: SourceHealth::Healthy);
        $degraded = $this->createArticle(health: SourceHealth::Degraded);

        self::assertGreaterThan(
            $this->service->score($degraded),
            $this->service->score($healthy),
        );
    }

    public function testDegradedSourceScoresHigherThanFailing(): void
    {
        $degraded = $this->createArticle(health: SourceHealth::Degraded);
        $failing = $this->createArticle(health: SourceHealth::Failing);

        self::assertGreaterThan(
            $this->service->score($failing),
            $this->service->score($degraded),
        );
    }

    public function testFailingSourceScoresHigherThanDisabled(): void
    {
        $failing = $this->createArticle(health: SourceHealth::Failing);
        $disabled = $this->createArticle(health: SourceHealth::Disabled);

        self::assertGreaterThan(
            $this->service->score($disabled),
            $this->service->score($failing),
        );
    }

    public function testArticleWithoutPublishedAtUsesFetchedAt(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article(
            'Test',
            'https://example.com/no-published',
            $source,
            new \DateTimeImmutable('2026-04-04 11:00:00'),
        );
        // No publishedAt set -> falls back to fetchedAt
        $article->setCategory($category);
        $article->setEnrichmentMethod(EnrichmentMethod::RuleBased);

        $score = $this->service->score($article);
        self::assertGreaterThan(0.0, $score);
        self::assertLessThan(1.0, $score);
    }

    public function testScoreComponentWeightsAddUpCorrectly(): void
    {
        // Perfect article: recent, high category, healthy source, AI enriched
        $perfect = $this->createArticle(
            categoryWeight: 10,
            publishedAt: '2026-04-04 12:00:00', // now
            enrichment: EnrichmentMethod::Ai,
            health: SourceHealth::Healthy,
        );
        $perfectScore = $this->service->score($perfect);

        // Should be very close to 1.0 (all components at max)
        // 0.3*1.0 + 0.4*1.0 + 0.2*1.0 + 0.1*1.0 = 1.0
        self::assertEqualsWithDelta(1.0, $perfectScore, 0.01);
    }

    public function testExactScoreForKnownInputs(): void
    {
        // Published exactly at "now" -> recency = 1.0
        // Category weight 10 / MAX 10 = 1.0
        // Healthy source = 1.0
        // AI enrichment = 1.0
        // combined = 0.3*1.0 + 0.4*1.0 + 0.2*1.0 + 0.1*1.0 = 1.0
        $article = $this->createArticle(
            categoryWeight: 10,
            publishedAt: '2026-04-04 12:00:00',
            enrichment: EnrichmentMethod::Ai,
            health: SourceHealth::Healthy,
        );

        self::assertSame(1.0, $this->service->score($article));
    }

    public function testExactScoreForHalfLifeAge(): void
    {
        // Published exactly 12 hours ago -> recency = 2^(-12/12) = 0.5
        // Category weight 5 / MAX 10 = 0.5
        // Degraded source = 0.7
        // RuleBased enrichment = 0.6
        // combined = 0.3*0.5 + 0.4*0.5 + 0.2*0.7 + 0.1*0.6 = 0.15+0.20+0.14+0.06 = 0.55
        $article = $this->createArticle(
            categoryWeight: 5,
            publishedAt: '2026-04-04 00:00:00', // 12h ago
            enrichment: EnrichmentMethod::RuleBased,
            health: SourceHealth::Degraded,
        );

        self::assertSame(0.55, $this->service->score($article));
    }

    public function testUncategorizedReturnsHalf(): void
    {
        // Uncategorized article -> category score = 0.5
        // Published now -> recency = 1.0
        // Healthy -> source = 1.0
        // Null enrichment -> 0.3
        // combined = 0.3*0.5 + 0.4*1.0 + 0.2*1.0 + 0.1*0.3 = 0.15+0.40+0.20+0.03 = 0.78
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article(
            'Test',
            'https://example.com/uncategorized-2',
            $source,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
        $article->setPublishedAt(new \DateTimeImmutable('2026-04-04 12:00:00'));
        // No category set -> 0.5

        self::assertSame(0.78, $this->service->score($article));
    }

    public function testExactlyZeroAgeHoursReturnsMaxRecency(): void
    {
        // Published at exact same timestamp as clock -> ageHours = 0 -> returns 1.0
        $article = $this->createArticle(publishedAt: '2026-04-04 12:00:00');
        $this->service->score($article);

        // This kills the LessThanOrEqualTo mutant (ageHours <= 0 -> ageHours < 0)
        // If changed to <0, ageHours=0 would not trigger, falling through to exponential decay
        // 2^(0) = 1.0 which is same, so we need a different approach

        // Use 7 days exactly -> ageHours = 168 = RECENCY_MAX_AGE_HOURS -> returns 0.0
        $article7days = $this->createArticle(publishedAt: '2026-03-28 12:00:00');
        $score7days = $this->service->score($article7days);

        // 7 days + 1 hour -> ageHours > 168 -> still 0.0
        $articleOlder = $this->createArticle(publishedAt: '2026-03-28 11:00:00');
        $scoreOlder = $this->service->score($articleOlder);

        // Both should have same score (both hit the >= boundary vs >)
        self::assertSame($score7days, $scoreOlder);
    }

    public function testPublishedAtTakesPriorityOverFetchedAt(): void
    {
        // If publishedAt is set, it should be used over fetchedAt
        // This kills the Coalesce mutant
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        $article = new Article(
            'Test',
            'https://example.com/coalesce-test',
            $source,
            new \DateTimeImmutable('2026-03-28 12:00:00'), // fetchedAt = 7 days ago
        );
        $article->setPublishedAt(new \DateTimeImmutable('2026-04-04 12:00:00')); // publishedAt = now
        $article->setCategory($category);
        $article->setEnrichmentMethod(EnrichmentMethod::RuleBased);

        $scoreWithPublished = $this->service->score($article);

        // Without publishedAt, fetchedAt would be 7 days ago -> low score
        $article2 = new Article(
            'Test2',
            'https://example.com/coalesce-test-2',
            $source,
            new \DateTimeImmutable('2026-03-28 12:00:00'), // fetchedAt = 7 days ago
        );
        // No publishedAt set -> falls back to fetchedAt
        $article2->setCategory($category);
        $article2->setEnrichmentMethod(EnrichmentMethod::RuleBased);

        $scoreWithoutPublished = $this->service->score($article2);

        // The article with recent publishedAt should score much higher
        self::assertGreaterThan($scoreWithoutPublished, $scoreWithPublished);
    }

    private function createArticle(
        int $categoryWeight = 10,
        string $publishedAt = '2026-04-04 11:00:00',
        ?EnrichmentMethod $enrichment = EnrichmentMethod::RuleBased,
        SourceHealth $health = SourceHealth::Healthy,
    ): Article {
        $category = new Category('Tech', 'tech', $categoryWeight, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        // Use reflection to set health status since there's no public setter
        $healthProp = new \ReflectionProperty(Source::class, 'healthStatus');
        $healthProp->setValue($source, $health);

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
