<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedKeywordExtractionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedKeywordExtractionService::class)]
final class RuleBasedKeywordExtractionServiceTest extends TestCase
{
    private RuleBasedKeywordExtractionService $service;

    protected function setUp(): void
    {
        $this->service = new RuleBasedKeywordExtractionService();
    }

    public function testExtractsMultiWordProperNouns(): void
    {
        $keywords = $this->service->extract(
            'Google Cloud announces partnership with Deutsche Bank',
            'Google Cloud and Deutsche Bank signed a multi-year agreement.',
        );

        self::assertContains('Google Cloud', $keywords);
        self::assertContains('Deutsche Bank', $keywords);
    }

    public function testExtractsSingleProperNouns(): void
    {
        $keywords = $this->service->extract(
            'Microsoft releases new developer tools',
            'Microsoft announced new features for Azure developers.',
        );

        self::assertContains('Microsoft', $keywords);
        self::assertContains('Azure', $keywords);
    }

    public function testFiltersStopWords(): void
    {
        $keywords = $this->service->extract(
            'The announcement from Berlin',
            'However, the results were surprising. Berlin hosted the event.',
        );

        // "The" and "However" should be filtered
        foreach ($keywords as $keyword) {
            self::assertNotSame('The', explode(' ', $keyword)[0]);
            self::assertNotSame('However', $keyword);
        }
    }

    public function testReturnsMaxEightKeywords(): void
    {
        $keywords = $this->service->extract(
            'Apple Google Microsoft Amazon Meta Tesla Nvidia Intel Samsung',
            'Apple Google Microsoft Amazon Meta Tesla Nvidia Intel Samsung Sony.',
        );

        self::assertLessThanOrEqual(8, \count($keywords));
    }

    public function testHandlesNullContent(): void
    {
        $keywords = $this->service->extract('Apple announces new iPhone', null);

        self::assertNotSame([], $keywords);
        self::assertContains('Apple', $keywords);
    }

    public function testReturnsEmptyForNoProperNouns(): void
    {
        $keywords = $this->service->extract(
            'a simple lowercase title',
            'this content has no capitalized words at all.',
        );

        self::assertSame([], $keywords);
    }

    public function testDeduplicatesKeywords(): void
    {
        $keywords = $this->service->extract(
            'Berlin hosts summit',
            'Berlin was the venue. Berlin attracted many visitors.',
        );

        $berlinCount = \count(array_filter($keywords, static fn (string $k): bool => $k === 'Berlin'));
        self::assertSame(1, $berlinCount);
    }

    public function testContentKeywordsAreIncluded(): void
    {
        // Keywords only in content (not title) should appear
        $keywords = $this->service->extract(
            'a lowercase title only',
            'Microsoft announced a partnership with Amazon in Seattle.',
        );

        self::assertContains('Microsoft', $keywords);
        self::assertContains('Amazon', $keywords);
        self::assertContains('Seattle', $keywords);
    }

    public function testExactlyMaxKeywordsReturned(): void
    {
        // 9 unique proper nouns -> should return exactly 8
        $keywords = $this->service->extract(
            'Apple Google Microsoft Amazon Meta Tesla Nvidia Intel Samsung',
            '',
        );

        self::assertSame(8, \count($keywords));
    }

    public function testFilterMultiWordStartingWithStopWord(): void
    {
        // "The Company" should be filtered because "The" is a stop word
        $keywords = $this->service->extract(
            'Regular Title',
            'Something about Apple Inc and not about stop word phrases.',
        );

        foreach ($keywords as $keyword) {
            $firstWord = explode(' ', $keyword)[0];
            self::assertNotSame('The', $firstWord);
            self::assertNotSame('This', $firstWord);
        }
    }
}
