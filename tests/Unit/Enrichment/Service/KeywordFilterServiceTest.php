<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\KeywordFilterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeywordFilterService::class)]
final class KeywordFilterServiceTest extends TestCase
{
    private KeywordFilterService $service;

    protected function setUp(): void
    {
        $this->service = new KeywordFilterService();
    }

    public function testRemovesKeywordsShorterThanThreeChars(): void
    {
        $result = $this->service->filter(['AI', 'Go', 'ab', 'Google', 'ML']);

        self::assertSame(['Google'], $result);
    }

    public function testKeepsKeywordsWithExactlyThreeChars(): void
    {
        $result = $this->service->filter(['App', 'Go']);

        self::assertSame(['App'], $result);
    }

    public function testRemovesEnglishStopWords(): void
    {
        $result = $this->service->filter(['the', 'Google', 'and', 'Microsoft', 'for']);

        self::assertSame(['Google', 'Microsoft'], $result);
    }

    public function testRemovesGermanStopWords(): void
    {
        $result = $this->service->filter(['der', 'Berlin', 'die', 'das', 'von', 'und', 'Munich']);

        self::assertSame(['Berlin', 'Munich'], $result);
    }

    public function testStopWordMatchingIsCaseInsensitive(): void
    {
        $result = $this->service->filter(['The', 'THE', 'Google', 'And', 'AND']);

        self::assertSame(['Google'], $result);
    }

    public function testLimitsToFiveKeywordsMax(): void
    {
        $result = $this->service->filter([
            'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf',
        ]);

        self::assertCount(5, $result);
        self::assertSame(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'], $result);
    }

    public function testExactlyFiveKeywordsReturned(): void
    {
        $result = $this->service->filter(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo']);

        self::assertCount(5, $result);
    }

    public function testSixKeywordsReturnsFive(): void
    {
        $result = $this->service->filter(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot']);

        self::assertCount(5, $result);
        self::assertNotContains('Foxtrot', $result);
    }

    public function testTrimsWhitespace(): void
    {
        $result = $this->service->filter(['  Google  ', '  Microsoft  ']);

        self::assertSame(['Google', 'Microsoft'], $result);
    }

    public function testEmptyArrayReturnsEmpty(): void
    {
        $result = $this->service->filter([]);

        self::assertSame([], $result);
    }

    public function testAllFilteredReturnsEmpty(): void
    {
        $result = $this->service->filter(['AI', 'Go', 'the', 'an', 'in']);

        self::assertSame([], $result);
    }

    public function testMultibyteKeywordsHandledCorrectly(): void
    {
        // "ab" is 2 chars, filtered. "uber" is 4 chars, but "uber" is not a stop word.
        // German umlaut: 'a' + combining umlaut might differ from precomposed 'ä'
        $result = $this->service->filter(['ab', 'uber', 'Munchen']);

        self::assertSame(['uber', 'Munchen'], $result);
    }

    public function testMultibyteStopWordFiltered(): void
    {
        // "fur" with umlaut is a German stop word
        $result = $this->service->filter(['fur', 'Google', 'fur']);

        self::assertSame(['fur', 'Google', 'fur'], $result);
        // "fur" is not in stop words, only "fur" with umlaut
    }

    public function testGermanUmlautStopWord(): void
    {
        $result = $this->service->filter(["f\u{00FC}r", 'Google']);

        self::assertSame(['Google'], $result);
    }

    public function testMbStrlenUsedForLengthCheck(): void
    {
        // German umlaut 'a' (U+00E4) is 2 bytes in UTF-8 but 1 character
        // "ab" = 2 mb_strlen chars → filtered (< 3)
        // "abc" = 3 mb_strlen chars → kept
        $result = $this->service->filter(["\u{00E4}b", "\u{00E4}bc"]);

        self::assertSame(["\u{00E4}bc"], $result);
    }

    public function testPreservesOrderOfInput(): void
    {
        $result = $this->service->filter(['Charlie', 'Alpha', 'Bravo']);

        self::assertSame(['Charlie', 'Alpha', 'Bravo'], $result);
    }
}
