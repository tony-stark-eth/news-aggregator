<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Source\Service\FeedContentAnalyzerService;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedItemCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedContentAnalyzerService::class)]
#[UsesClass(FeedItem::class)]
#[UsesClass(FeedItemCollection::class)]
final class FeedContentAnalyzerServiceTest extends TestCase
{
    private FeedContentAnalyzerService $service;

    protected function setUp(): void
    {
        $this->service = new FeedContentAnalyzerService();
    }

    public function testReturnsTrueWhenItemsHaveFullContent(): void
    {
        $longText = str_repeat('This is a word in the full article content. ', 40);
        $items = new FeedItemCollection([
            new FeedItem('Article 1', 'https://example.com/1', null, $longText, null),
            new FeedItem('Article 2', 'https://example.com/2', null, $longText, null),
            new FeedItem('Article 3', 'https://example.com/3', null, $longText, null),
        ]);

        self::assertTrue($this->service->hasFullContent($items));
    }

    public function testReturnsFalseWhenItemsHaveShortContent(): void
    {
        $items = new FeedItemCollection([
            new FeedItem('Article 1', 'https://example.com/1', null, 'Short snippet', null),
            new FeedItem('Article 2', 'https://example.com/2', null, 'Another short one', null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testReturnsFalseForEmptyCollection(): void
    {
        $items = new FeedItemCollection([]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testReturnsFalseWhenContentHasTruncationMarkers(): void
    {
        $text = str_repeat('Word here ', 200) . '...read more';
        $items = new FeedItemCollection([
            new FeedItem('Article', 'https://example.com/1', null, $text, null),
            new FeedItem('Article 2', 'https://example.com/2', null, $text, null),
            new FeedItem('Article 3', 'https://example.com/3', null, $text, null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testDetectsEllipsisTruncationMarker(): void
    {
        $text = str_repeat('Word here ', 200) . '[...]';
        $items = new FeedItemCollection([
            new FeedItem('Article', 'https://example.com/1', null, $text, null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testFallsBackToRawHtmlWhenTextIsNull(): void
    {
        $longHtml = '<p>' . str_repeat('This is a word in the full article content. ', 40) . '</p>';
        $items = new FeedItemCollection([
            new FeedItem('Article', 'https://example.com/1', $longHtml, null, null),
        ]);

        self::assertTrue($this->service->hasFullContent($items));
    }

    public function testReturnsFalseWhenBothContentFieldsAreNull(): void
    {
        $items = new FeedItemCollection([
            new FeedItem('Article', 'https://example.com/1', null, null, null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testSamplesMaxFiveItems(): void
    {
        $longText = str_repeat('This is a word in the full article content. ', 40);
        $shortText = 'Too short';

        // 5 full + 5 short. With 60% threshold and 5 sample max, should return true
        $feedItems = [];
        for ($i = 0; $i < 5; $i++) {
            $feedItems[] = new FeedItem("Full {$i}", "https://example.com/{$i}", null, $longText, null);
        }
        for ($i = 5; $i < 10; $i++) {
            $feedItems[] = new FeedItem("Short {$i}", "https://example.com/{$i}", null, $shortText, null);
        }

        $items = new FeedItemCollection($feedItems);

        self::assertTrue($this->service->hasFullContent($items));
    }

    public function testBoundaryRatioExactly60Percent(): void
    {
        $longText = str_repeat('This is a word in the full article content. ', 40);
        $shortText = 'Too short';

        // 3 full out of 5 = 60% = passes threshold
        $items = new FeedItemCollection([
            new FeedItem('Full 1', 'https://example.com/1', null, $longText, null),
            new FeedItem('Full 2', 'https://example.com/2', null, $longText, null),
            new FeedItem('Full 3', 'https://example.com/3', null, $longText, null),
            new FeedItem('Short 1', 'https://example.com/4', null, $shortText, null),
            new FeedItem('Short 2', 'https://example.com/5', null, $shortText, null),
        ]);

        self::assertTrue($this->service->hasFullContent($items));
    }

    public function testBelowBoundaryRatioJustUnder60Percent(): void
    {
        $longText = str_repeat('This is a word in the full article content. ', 40);
        $shortText = 'Too short';

        // 2 full out of 5 = 40% = below threshold
        $items = new FeedItemCollection([
            new FeedItem('Full 1', 'https://example.com/1', null, $longText, null),
            new FeedItem('Full 2', 'https://example.com/2', null, $longText, null),
            new FeedItem('Short 1', 'https://example.com/3', null, $shortText, null),
            new FeedItem('Short 2', 'https://example.com/4', null, $shortText, null),
            new FeedItem('Short 3', 'https://example.com/5', null, $shortText, null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testDetectsMultibyteTruncationMarker(): void
    {
        $text = str_repeat('Ein Wort hier. ', 200) . 'Read More';
        $items = new FeedItemCollection([
            new FeedItem('Artikel', 'https://example.com/1', null, $text, null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }

    public function testExactly150WordsIsFullContent(): void
    {
        $text = implode(' ', array_fill(0, 150, 'word'));
        $items = new FeedItemCollection([
            new FeedItem('Article', 'https://example.com/1', null, $text, null),
        ]);

        self::assertTrue($this->service->hasFullContent($items));
    }

    public function testExactly149WordsIsNotFullContent(): void
    {
        $text = implode(' ', array_fill(0, 149, 'word'));
        $items = new FeedItemCollection([
            new FeedItem('Article', 'https://example.com/1', null, $text, null),
        ]);

        self::assertFalse($this->service->hasFullContent($items));
    }
}
