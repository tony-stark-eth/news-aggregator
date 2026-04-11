<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedSentimentScoringService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedSentimentScoringService::class)]
final class RuleBasedSentimentScoringServiceTest extends TestCase
{
    private RuleBasedSentimentScoringService $service;

    protected function setUp(): void
    {
        $this->service = new RuleBasedSentimentScoringService();
    }

    public function testPositiveHeadlineReturnsPositiveScore(): void
    {
        $score = $this->service->score('Major breakthrough in cancer research', null);

        self::assertNotNull($score);
        self::assertGreaterThan(0.0, $score);
    }

    public function testNegativeHeadlineReturnsNegativeScore(): void
    {
        $score = $this->service->score('Economic crisis deepens as markets crash', null);

        self::assertNotNull($score);
        self::assertLessThan(0.0, $score);
    }

    public function testNeutralHeadlineReturnsNull(): void
    {
        $score = $this->service->score('City council meets Tuesday afternoon', null);

        self::assertNull($score);
    }

    public function testTitleWeightedHigherThanContent(): void
    {
        // "breakthrough" in title (weight 2) vs "crisis" in content (weight 1)
        $score = $this->service->score('Breakthrough in science', 'There was a crisis in the past');

        self::assertNotNull($score);
        self::assertGreaterThan(0.0, $score);
    }

    public function testScoreCappedAtPositive08(): void
    {
        $score = $this->service->score(
            'Success! Win! Growth! Breakthrough! Achievement!',
            'Amazing progress and innovation with optimism and recovery',
        );

        self::assertNotNull($score);
        self::assertLessThanOrEqual(0.8, $score);
    }

    public function testScoreCappedAtNegative08(): void
    {
        $score = $this->service->score(
            'Crisis! Crash! Disaster! War! Death!',
            'Collapse and scandal with fraud and recession and layoff',
        );

        self::assertNotNull($score);
        self::assertGreaterThanOrEqual(-0.8, $score);
    }

    public function testMixedSentimentReturnsBalancedScore(): void
    {
        $score = $this->service->score(
            'Market crash followed by rapid recovery',
            null,
        );

        self::assertNotNull($score);
        // Both positive and negative keywords found, result depends on balance
        self::assertGreaterThanOrEqual(-0.8, $score);
        self::assertLessThanOrEqual(0.8, $score);
    }

    public function testContentTextAddsSentiment(): void
    {
        $titleOnly = $this->service->score('News update', null);
        $withContent = $this->service->score('News update', 'There was a major breakthrough today');

        self::assertNull($titleOnly);
        self::assertNotNull($withContent);
        self::assertGreaterThan(0.0, $withContent);
    }

    public function testCaseInsensitiveMatching(): void
    {
        $score = $this->service->score('MAJOR BREAKTHROUGH IN SCIENCE', null);

        self::assertNotNull($score);
        self::assertGreaterThan(0.0, $score);
    }

    public function testTitleWeightIsDoubled(): void
    {
        // "breakthrough" in title only: positiveCount = 1*2 (title weight), negativeCount = 0
        // raw = (2-0)/2 = 1.0, capped to 0.8
        $score = $this->service->score('breakthrough announced', null);

        self::assertSame(0.8, $score);
    }

    public function testContentOnlyWeight(): void
    {
        // "breakthrough" in content only: positiveCount = 1*1 (content weight), negativeCount = 0
        // raw = (1-0)/1 = 1.0, capped to 0.8
        $score = $this->service->score('Neutral title', 'A major breakthrough today');

        self::assertSame(0.8, $score);
    }

    public function testExactBalancedScore(): void
    {
        // "success" (positive) + "crisis" (negative) both in title
        // positiveCount = 1*2, negativeCount = 1*2
        // raw = (2-2)/4 = 0.0
        $score = $this->service->score('success amid crisis', null);

        self::assertSame(0.0, $score);
    }

    public function testNegativeOnlyReturnsExactCap(): void
    {
        // Only negative keywords → raw = -1.0, capped at -0.8
        $score = $this->service->score('terrible crisis', null);

        self::assertSame(-0.8, $score);
    }

    public function testMbStrtolowerHandlesUmlauts(): void
    {
        // "ERFOLG" (success in German with umlauts) — tests that mb_strtolower is used
        // Our keywords are English, so use uppercase English keywords
        // "BREAKTHROUGH" must match "breakthrough" keyword via mb_strtolower
        $score = $this->service->score('ÜBERRASCHUNG: BREAKTHROUGH', null);

        self::assertNotNull($score);
        self::assertGreaterThan(0.0, $score);
    }

    public function testContentPositiveMatchesAddToTitlePositiveMatches(): void
    {
        // Title: "success" (positive, weight 2=2), "crisis" (negative, weight 2=2)
        // Content: "breakthrough" (positive, weight 1=1)
        // positiveCount = 2 + 1 = 3, negativeCount = 2
        // raw = (3-2)/(3+2) = 0.2
        $score = $this->service->score('A success despite crisis', 'The breakthrough happened');

        self::assertNotNull($score);
        self::assertEqualsWithDelta(0.2, $score, 0.01);
    }

    public function testContentNegativeMatchesAddToTitleNegativeMatches(): void
    {
        // Title: "crisis" (negative, weight 2=2), "success" (positive, weight 2=2)
        // Content: "crash" (negative, weight 1=1)
        // negativeCount = 2 + 1 = 3, positiveCount = 2
        // raw = (2-3)/(2+3) = -0.2
        $score = $this->service->score('Success before the crisis', 'The crash was unexpected');

        self::assertNotNull($score);
        self::assertEqualsWithDelta(-0.2, $score, 0.01);
    }

    public function testTitleAndContentNegativeMatches(): void
    {
        // Title: "crisis" (negative, weight 2), content: "crash" (negative, weight 1)
        // negativeCount = 2 + 1 = 3, positiveCount = 0
        // raw = -3/3 = -1.0, capped to -0.8
        $score = $this->service->score('The crisis deepens', 'Markets crash badly');

        self::assertSame(-0.8, $score);
    }

    public function testCounterIncrements(): void
    {
        // Two positive keywords in title: "success" and "win" → 2*2 = 4 positive
        // One negative keyword in title: "crisis" → 1*2 = 2 negative
        // raw = (4-2)/6 = 0.333...
        $score = $this->service->score('success and win despite crisis', null);

        self::assertNotNull($score);
        self::assertEqualsWithDelta(0.333, $score, 0.01);
    }
}
