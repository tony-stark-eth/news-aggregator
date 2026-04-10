<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\ValueObject;

use App\Source\Exception\InvalidFeedUrlException;
use App\Source\ValueObject\FeedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedUrl::class)]
final class FeedUrlTest extends TestCase
{
    public function testValidHttpsUrl(): void
    {
        $url = new FeedUrl('https://feeds.arstechnica.com/arstechnica/index');

        self::assertSame('https://feeds.arstechnica.com/arstechnica/index', $url->value);
        self::assertSame('https://feeds.arstechnica.com/arstechnica/index', (string) $url);
    }

    public function testValidHttpUrl(): void
    {
        $url = new FeedUrl('http://example.com/feed.xml');

        self::assertSame('http://example.com/feed.xml', $url->value);
    }

    public function testInvalidUrlThrows(): void
    {
        $this->expectException(InvalidFeedUrlException::class);

        new FeedUrl('not-a-url');
    }

    public function testFtpSchemeThrows(): void
    {
        $this->expectException(InvalidFeedUrlException::class);

        new FeedUrl('ftp://example.com/feed.xml');
    }

    public function testInvalidUrlWithValidSchemeThrows(): void
    {
        $this->expectException(InvalidFeedUrlException::class);

        new FeedUrl('http://a b');
    }
}
