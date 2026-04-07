<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\ValueObject;

use App\Source\ValueObject\FeedPreview;
use App\Source\ValueObject\FeedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedPreview::class)]
final class FeedPreviewTest extends TestCase
{
    public function testConstructWithAllValues(): void
    {
        $feedUrl = new FeedUrl('https://example.com/feed.xml');
        $preview = new FeedPreview(
            title: 'My Feed',
            itemCount: 42,
            detectedLanguage: 'de',
            feedUrl: $feedUrl,
        );

        self::assertSame('My Feed', $preview->title);
        self::assertSame(42, $preview->itemCount);
        self::assertSame('de', $preview->detectedLanguage);
        self::assertSame($feedUrl, $preview->feedUrl);
    }

    public function testConstructWithNullLanguage(): void
    {
        $feedUrl = new FeedUrl('https://example.com/feed.xml');
        $preview = new FeedPreview(
            title: 'My Feed',
            itemCount: 0,
            detectedLanguage: null,
            feedUrl: $feedUrl,
        );

        self::assertNull($preview->detectedLanguage);
        self::assertSame(0, $preview->itemCount);
    }
}
