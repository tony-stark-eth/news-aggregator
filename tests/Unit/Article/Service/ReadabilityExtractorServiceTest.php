<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Service\ReadabilityExtractorService;
use App\Article\ValueObject\ReadabilityResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

#[CoversClass(ReadabilityExtractorService::class)]
#[UsesClass(ReadabilityResult::class)]
final class ReadabilityExtractorServiceTest extends TestCase
{
    public function testExtractsContentFromValidHtml(): void
    {
        $html = $this->buildArticleHtml(str_repeat('This is a word. ', 60));

        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('debug');

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/article');

        self::assertTrue($result->success);
        self::assertNotNull($result->textContent);
        self::assertNotNull($result->htmlContent);
        self::assertGreaterThanOrEqual(50, str_word_count($result->textContent));
    }

    public function testReturnsFalseForTooFewWords(): void
    {
        $html = $this->buildArticleHtml('Short text only');

        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with(
                self::stringContains('too few words'),
                self::callback(static fn (array $ctx): bool => $ctx['url'] === 'https://example.com/short'
                    && \is_int($ctx['wordCount'])),
            );

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/short');

        self::assertFalse($result->success);
        self::assertNull($result->textContent);
        self::assertNull($result->htmlContent);
    }

    public function testReturnsFalseForInvalidHtml(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with(
                self::stringContains('parse failed'),
                self::callback(static fn (array $ctx): bool => $ctx['url'] === 'https://example.com/bad'
                    && \is_string($ctx['error'])),
            );

        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract('', 'https://example.com/bad');

        self::assertFalse($result->success);
        self::assertNull($result->textContent);
    }

    public function testSanitizesHtmlOutput(): void
    {
        $html = $this->buildArticleHtml(str_repeat('Word here now. ', 60));

        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->expects(self::once())
            ->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => strip_tags($input));

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/article');

        self::assertTrue($result->success);
        self::assertNotNull($result->htmlContent);
        self::assertStringNotContainsString('<script>', $result->htmlContent);
    }

    public function testTooFewWordsReturnsFalseResult(): void
    {
        // 5 words - clearly too few regardless of title extraction
        $html = $this->buildArticleHtml('Five words in article body');

        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/short');

        self::assertFalse($result->success);
    }

    public function testEnoughWordsReturnsSuccessResult(): void
    {
        // 80 words — plenty above the 50-word threshold even with extraction variance
        $html = $this->buildArticleHtml(implode(' ', array_fill(0, 80, 'interesting')));

        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('debug');

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/long');

        self::assertTrue($result->success);
        self::assertNotNull($result->textContent);
    }

    public function testHandlesHtmlEntitiesInOutput(): void
    {
        $bodyWithEntities = str_repeat('Word with &amp; entity here. ', 20);
        $html = $this->buildArticleHtml($bodyWithEntities);

        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/entities');

        self::assertTrue($result->success);
        self::assertNotNull($result->textContent);
        self::assertStringContainsString('&', $result->textContent);
        self::assertStringNotContainsString('&amp;', $result->textContent);
    }

    public function testTrimsWhitespaceFromOutput(): void
    {
        $bodyWithWhitespace = str_repeat("  Word  here  now.  \n  ", 20);
        $html = $this->buildArticleHtml($bodyWithWhitespace);

        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/whitespace');

        self::assertTrue($result->success);
        self::assertNotNull($result->textContent);
        // Verify whitespace is normalized — no leading/trailing, no double spaces
        self::assertSame($result->textContent, trim($result->textContent));
        self::assertStringNotContainsString('  ', $result->textContent);
    }

    public function testRelativeUrlsAreFixed(): void
    {
        $bodyWithLink = str_repeat('Content word here. ', 20) . '<a href="/page">link</a>' . str_repeat(' More words here.', 30);
        $html = $this->buildArticleHtml($bodyWithLink);

        $sanitizer = $this->createStub(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')
            ->willReturnCallback(static fn (string $input): string => $input);

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ReadabilityExtractorService($sanitizer, $logger);
        $result = $service->extract($html, 'https://example.com/article');

        self::assertTrue($result->success);
        // The FixRelativeURLs=true config should convert /page to absolute URL
        if ($result->htmlContent !== null) {
            self::assertStringContainsString('https://example.com/page', $result->htmlContent);
        }
    }

    private function buildArticleHtml(string $bodyText): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head><title>Test Article</title></head>
            <body>
                <article>
                    <h1>Test Article Title</h1>
                    <p>{$bodyText}</p>
                </article>
            </body>
            </html>
            HTML;
    }
}
