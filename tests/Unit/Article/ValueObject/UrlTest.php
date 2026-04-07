<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\ValueObject;

use App\Article\ValueObject\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Url::class)]
final class UrlTest extends TestCase
{
    public function testConstructorAcceptsValidUrl(): void
    {
        $url = new Url('https://example.com/article');

        self::assertSame('https://example.com/article', $url->value);
    }

    public function testConstructorRejectsInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL: "not-a-url"');

        new Url('not-a-url');
    }

    public function testToString(): void
    {
        $url = new Url('https://example.com/path');

        self::assertSame('https://example.com/path', (string) $url);
    }

    #[DataProvider('sanitizeProvider')]
    public function testSanitize(string $input, string $expected): void
    {
        self::assertSame($expected, Url::sanitize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sanitizeProvider(): iterable
    {
        yield 'no query params' => [
            'https://example.com/article',
            'https://example.com/article',
        ];

        yield 'utm_source stripped' => [
            'https://example.com/article?utm_source=twitter',
            'https://example.com/article',
        ];

        yield 'utm_medium stripped' => [
            'https://example.com/article?utm_medium=social',
            'https://example.com/article',
        ];

        yield 'utm_campaign stripped' => [
            'https://example.com/article?utm_campaign=spring2026',
            'https://example.com/article',
        ];

        yield 'utm_content stripped' => [
            'https://example.com/article?utm_content=header',
            'https://example.com/article',
        ];

        yield 'utm_term stripped' => [
            'https://example.com/article?utm_term=keyword',
            'https://example.com/article',
        ];

        yield 'fbclid stripped' => [
            'https://example.com/article?fbclid=abc123',
            'https://example.com/article',
        ];

        yield 'gclid stripped' => [
            'https://example.com/article?gclid=def456',
            'https://example.com/article',
        ];

        yield 'mc_cid stripped' => [
            'https://example.com/article?mc_cid=mail1',
            'https://example.com/article',
        ];

        yield 'mc_eid stripped' => [
            'https://example.com/article?mc_eid=mail2',
            'https://example.com/article',
        ];

        yield 'all tracking params stripped together' => [
            'https://example.com/article?utm_source=twitter&utm_medium=social&utm_campaign=test&fbclid=abc&gclid=def',
            'https://example.com/article',
        ];

        yield 'preserves non-tracking params' => [
            'https://example.com/article?page=2&sort=date&utm_source=twitter',
            'https://example.com/article?page=2&sort=date',
        ];

        yield 'preserves fragment' => [
            'https://example.com/article?utm_source=twitter#section-2',
            'https://example.com/article#section-2',
        ];

        yield 'preserves fragment with kept params' => [
            'https://example.com/article?id=42&utm_campaign=test#heading',
            'https://example.com/article?id=42#heading',
        ];

        yield 'preserves path and port' => [
            'https://example.com:8080/path/to/article?utm_source=rss',
            'https://example.com:8080/path/to/article',
        ];

        yield 'url without query returns unchanged' => [
            'https://example.com/just-a-path',
            'https://example.com/just-a-path',
        ];

        yield 'url with only non-tracking params unchanged' => [
            'https://example.com/article?id=1&ref=homepage',
            'https://example.com/article?id=1&ref=homepage',
        ];

        yield 'mixed tracking and non-tracking' => [
            'https://example.com/article?ref=sidebar&utm_source=newsletter&page=3&mc_cid=abc',
            'https://example.com/article?ref=sidebar&page=3',
        ];
    }

    public function testSanitizeReturnsInvalidUrlUnchanged(): void
    {
        $invalid = 'not-a-url';

        self::assertSame($invalid, Url::sanitize($invalid));
    }

    public function testSanitizeReturnsSeverlyMalformedUrlUnchanged(): void
    {
        // parse_url returns false for severely malformed URLs
        $malformed = 'http:///example.com';

        self::assertSame($malformed, Url::sanitize($malformed));
    }

    public function testSanitizeReturnsUrlWithoutHostUnchanged(): void
    {
        // URL with scheme but no host — guard should catch missing host
        $noHost = 'file:///path/to/file?utm_source=test';

        self::assertSame($noHost, Url::sanitize($noHost));
    }

    public function testSanitizeHandlesUrlWithoutPath(): void
    {
        // parse_url on "https://example.com?utm_source=x" has no path
        $result = Url::sanitize('https://example.com?utm_source=test');

        self::assertSame('https://example.com/', $result);
    }

    public function testSanitizePreservesUserInfo(): void
    {
        $result = Url::sanitize('https://user:pass@example.com/path?utm_source=test&id=1');

        self::assertSame('https://user:pass@example.com/path?id=1', $result);
    }

    public function testSanitizePreservesUserWithoutPassword(): void
    {
        $result = Url::sanitize('https://user@example.com/path?utm_source=test');

        self::assertSame('https://user@example.com/path', $result);
    }
}
