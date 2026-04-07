<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Service\ArticleContentFetcherService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ArticleContentFetcherService::class)]
final class ArticleContentFetcherServiceTest extends TestCase
{
    public function testFetchReturnsHtmlContent(): void
    {
        $expectedHtml = '<html><body>Article content</body></html>';
        $httpClient = new MockHttpClient([
            new MockResponse($expectedHtml, [
                'http_code' => 200,
            ])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('debug');

        $service = new ArticleContentFetcherService($httpClient, $logger, 15);
        $result = $service->fetch('https://example.com/article');

        self::assertSame($expectedHtml, $result);
    }

    public function testFetchThrowsOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Not Found', [
                'http_code' => 404,
            ])]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with(
                self::stringContains('HTTP'),
                self::callback(static fn (array $ctx): bool => $ctx['status'] === 404
                    && $ctx['url'] === 'https://example.com/missing'),
            );

        $service = new ArticleContentFetcherService($httpClient, $logger, 15);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404 for https://example.com/missing');
        $service->fetch('https://example.com/missing');
    }

    public function testFetchSendsBrowserLikeHeaders(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('GET', $method);
            self::assertArrayHasKey('headers', $options);

            /** @var list<string> $headers */
            $headers = $options['headers'];
            $headerString = implode("\n", $headers);
            self::assertStringContainsString('User-Agent:', $headerString);
            self::assertStringContainsString('Accept:', $headerString);

            return new MockResponse('<html></html>', [
                'http_code' => 200,
            ]);
        });

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ArticleContentFetcherService($httpClient, $logger, 15);
        $service->fetch('https://example.com/article');
    }

    public function testFetchUsesConfiguredTimeout(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame(30.0, $options['timeout']);

            return new MockResponse('<html></html>', [
                'http_code' => 200,
            ]);
        });

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ArticleContentFetcherService($httpClient, $logger, 30);
        $service->fetch('https://example.com/article');
    }

    public function testFetchSucceedsWithStatus200(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('OK', [
                'http_code' => 200,
            ])]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('debug');

        $service = new ArticleContentFetcherService($httpClient, $logger, 15);
        $result = $service->fetch('https://example.com/article');

        self::assertSame('OK', $result);
    }

    public function testFetchFailsWithBoundaryStatusCode400(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Bad Request', [
                'http_code' => 400,
            ])]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $service = new ArticleContentFetcherService($httpClient, $logger, 15);

        $this->expectException(\RuntimeException::class);
        $service->fetch('https://example.com/article');
    }
}
