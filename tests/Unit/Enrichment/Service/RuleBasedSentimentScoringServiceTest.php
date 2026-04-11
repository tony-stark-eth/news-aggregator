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
}
