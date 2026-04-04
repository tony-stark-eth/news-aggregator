<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Source\Exception\FeedFetchException;
use App\Source\Service\HttpFeedFetcherService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpFeedFetcherService::class)]
final class HttpFeedFetcherServiceTest extends TestCase
{
    public function testFetchReturnsContent(): void
    {
        $mockResponse = new MockResponse('<rss>feed content</rss>');
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $result = $fetcher->fetch('https://example.com/feed.xml');

        self::assertSame('<rss>feed content</rss>', $result);
    }

    public function testFetchThrowsOnHttpError(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 404,
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $this->expectException(FeedFetchException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');

        $fetcher->fetch('https://example.com/broken-feed.xml');
    }

    public function testFetchThrowsOnNetworkError(): void
    {
        $mockResponse = new MockResponse('', [
            'error' => 'Connection timeout',
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $this->expectException(FeedFetchException::class);

        $fetcher->fetch('https://example.com/timeout-feed.xml');
    }
}
