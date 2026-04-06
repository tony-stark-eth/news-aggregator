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

    public function testFetchThrowsOnStatus500(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $this->expectException(FeedFetchException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        $fetcher->fetch('https://example.com/error-feed.xml');
    }

    public function testFetchAcceptsStatus200(): void
    {
        $mockResponse = new MockResponse('<rss>ok</rss>', [
            'http_code' => 200,
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $result = $fetcher->fetch('https://example.com/ok.xml');

        self::assertSame('<rss>ok</rss>', $result);
    }

    public function testFetchThrowsOnStatus300(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 300,
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $this->expectException(FeedFetchException::class);
        $this->expectExceptionMessageMatches('/HTTP 300/');

        $fetcher->fetch('https://example.com/redirect.xml');
    }

    public function testFetchThrowsOnStatus199(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 199,
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $this->expectException(FeedFetchException::class);
        $this->expectExceptionMessageMatches('/HTTP 199/');

        $fetcher->fetch('https://example.com/informational.xml');
    }

    public function testFetchExceptionContainsUrl(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 404,
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        try {
            $fetcher->fetch('https://example.com/my-feed.xml');
            self::fail('Expected FeedFetchException');
        } catch (FeedFetchException $e) {
            self::assertStringContainsString('my-feed.xml', $e->getMessage());
            self::assertStringContainsString('HTTP 404', $e->getMessage());
        }
    }

    public function testNetworkErrorPreservesPreviousException(): void
    {
        $mockResponse = new MockResponse('', [
            'error' => 'DNS resolution failed',
        ]);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        try {
            $fetcher->fetch('https://example.com/dns-fail.xml');
            self::fail('Expected FeedFetchException');
        } catch (FeedFetchException $e) {
            self::assertStringContainsString('dns-fail.xml', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testFetchReturnsExactContent(): void
    {
        $xmlContent = '<?xml version="1.0"?><rss><channel><title>Test</title></channel></rss>';
        $mockResponse = new MockResponse($xmlContent);
        $client = new MockHttpClient($mockResponse);
        $fetcher = new HttpFeedFetcherService($client);

        $result = $fetcher->fetch('https://example.com/feed.xml');

        // Kills ReturnValue mutation on getContent()
        self::assertSame($xmlContent, $result);
    }
}
