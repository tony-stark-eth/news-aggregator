<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Source\Exception\FeedFetchException;
use App\Source\Exception\InvalidFeedUrlException;
use App\Source\Service\FeedContentAnalyzerServiceInterface;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedItemCollection;
use App\Source\Service\FeedLanguageDetectorInterface;
use App\Source\Service\FeedParserServiceInterface;
use App\Source\Service\FeedValidationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedValidationService::class)]
final class FeedValidationServiceTest extends TestCase
{
    private const string VALID_URL = 'https://example.com/feed.xml';

    private const string SAMPLE_RSS = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
            <channel>
                <title>Test Feed</title>
                <item>
                    <title>Article 1</title>
                    <link>https://example.com/1</link>
                </item>
                <item>
                    <title>Article 2</title>
                    <link>https://example.com/2</link>
                </item>
            </channel>
        </rss>
        XML;

    public function testValidateReturnsPreviewOnSuccess(): void
    {
        $fetcher = $this->createMock(FeedFetcherServiceInterface::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->with(self::VALID_URL)
            ->willReturn(self::SAMPLE_RSS);

        $parser = $this->createMock(FeedParserServiceInterface::class);
        $parser->expects(self::once())
            ->method('parse')
            ->with(self::SAMPLE_RSS)
            ->willReturn(new FeedItemCollection([
                new FeedItem('Article 1', 'https://example.com/1', null, null, null),
                new FeedItem('Article 2', 'https://example.com/2', null, null, null),
            ]));

        $languageDetector = $this->createMock(FeedLanguageDetectorInterface::class);
        $languageDetector->expects(self::once())
            ->method('detect')
            ->with(self::SAMPLE_RSS)
            ->willReturn('en');

        $contentAnalyzer = $this->createStub(FeedContentAnalyzerServiceInterface::class);
        $service = new FeedValidationService($fetcher, $parser, $languageDetector, $contentAnalyzer);
        $preview = $service->validate(self::VALID_URL);

        self::assertSame('Test Feed', $preview->title);
        self::assertSame(2, $preview->itemCount);
        self::assertSame('en', $preview->detectedLanguage);
        self::assertSame(self::VALID_URL, $preview->feedUrl->value);
    }

    public function testValidateWithNullLanguage(): void
    {
        $fetcher = $this->createMock(FeedFetcherServiceInterface::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->willReturn(self::SAMPLE_RSS);

        $parser = $this->createMock(FeedParserServiceInterface::class);
        $parser->expects(self::once())
            ->method('parse')
            ->willReturn(new FeedItemCollection([]));

        $languageDetector = $this->createMock(FeedLanguageDetectorInterface::class);
        $languageDetector->expects(self::once())
            ->method('detect')
            ->willReturn(null);

        $contentAnalyzer = $this->createStub(FeedContentAnalyzerServiceInterface::class);
        $service = new FeedValidationService($fetcher, $parser, $languageDetector, $contentAnalyzer);
        $preview = $service->validate(self::VALID_URL);

        self::assertNull($preview->detectedLanguage);
        self::assertSame(0, $preview->itemCount);
    }

    public function testValidateThrowsOnInvalidUrl(): void
    {
        $fetcher = $this->createStub(FeedFetcherServiceInterface::class);
        $parser = $this->createStub(FeedParserServiceInterface::class);
        $languageDetector = $this->createStub(FeedLanguageDetectorInterface::class);

        $contentAnalyzer = $this->createStub(FeedContentAnalyzerServiceInterface::class);
        $service = new FeedValidationService($fetcher, $parser, $languageDetector, $contentAnalyzer);

        $this->expectException(InvalidFeedUrlException::class);
        $service->validate('not-a-url');
    }

    public function testValidateThrowsOnFetchError(): void
    {
        $fetcher = $this->createMock(FeedFetcherServiceInterface::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->willThrowException(FeedFetchException::fromUrl(self::VALID_URL, 'HTTP 404'));

        $parser = $this->createStub(FeedParserServiceInterface::class);
        $languageDetector = $this->createStub(FeedLanguageDetectorInterface::class);

        $contentAnalyzer = $this->createStub(FeedContentAnalyzerServiceInterface::class);
        $service = new FeedValidationService($fetcher, $parser, $languageDetector, $contentAnalyzer);

        $this->expectException(FeedFetchException::class);
        $service->validate(self::VALID_URL);
    }

    public function testValidateExtractsTitleFromFeed(): void
    {
        $rssWithTitle = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Ars Technica</title>
                    <item>
                        <title>Article</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        $fetcher = $this->createMock(FeedFetcherServiceInterface::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->willReturn($rssWithTitle);

        $parser = $this->createMock(FeedParserServiceInterface::class);
        $parser->expects(self::once())
            ->method('parse')
            ->willReturn(new FeedItemCollection([
                new FeedItem('Article', 'https://example.com/1', null, null, null),
            ]));

        $languageDetector = $this->createStub(FeedLanguageDetectorInterface::class);

        $contentAnalyzer = $this->createStub(FeedContentAnalyzerServiceInterface::class);
        $service = new FeedValidationService($fetcher, $parser, $languageDetector, $contentAnalyzer);
        $preview = $service->validate(self::VALID_URL);

        self::assertSame('Ars Technica', $preview->title);
        self::assertSame(1, $preview->itemCount);
    }
}
