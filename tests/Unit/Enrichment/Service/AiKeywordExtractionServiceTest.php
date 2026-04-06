<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiKeywordExtractionService;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\RuleBasedKeywordExtractionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiKeywordExtractionService::class)]
final class AiKeywordExtractionServiceTest extends TestCase
{
    public function testUsesAiWhenSuccessful(): void
    {
        $platform = new InMemoryPlatform('Google, Artificial Intelligence, Developers');

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract(
            'Google announces new AI model',
            'New artificial intelligence model for developers.',
        );

        self::assertSame(['Google', 'Artificial Intelligence', 'Developers'], $keywords);
    }

    public function testFallsBackToRuleBasedOnFailure(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('AI keyword extraction failed'),
                self::callback(static function (array $context): bool {
                    return isset($context['error'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            $logger,
        );

        $keywords = $service->extract(
            'Microsoft Azure update released',
            'Microsoft released a major Azure cloud platform update.',
        );

        self::assertNotSame([], $keywords);
        self::assertContains('Microsoft', $keywords);
    }

    public function testFallsBackOnEmptyAiResponse(): void
    {
        $platform = new InMemoryPlatform('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('no valid keywords'),
                self::callback(static function (array $context): bool {
                    return isset($context['response'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            $logger,
        );

        $keywords = $service->extract(
            'Apple launches new product',
            'Apple announced a new product at their Cupertino headquarters.',
        );

        self::assertContains('Apple', $keywords);
    }

    public function testTrimsKeywords(): void
    {
        $platform = new InMemoryPlatform(' Google ,  AI , Cloud ');

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test title', 'Test content');

        self::assertSame(['Google', 'AI', 'Cloud'], $keywords);
    }

    public function testLimitsToMaxKeywords(): void
    {
        $platform = new InMemoryPlatform('A, B, C, D, E, F, G, H, I, J');

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test', 'Content');

        self::assertCount(8, $keywords);
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], $keywords);
    }

    public function testFiltersEmptyKeywordsAfterTrim(): void
    {
        $platform = new InMemoryPlatform('Google, , , AI');

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test', 'Content');

        self::assertSame(['Google', 'AI'], $keywords);
    }

    public function testFiltersKeywordsOver100Chars(): void
    {
        $longKeyword = str_repeat('x', 101);
        $platform = new InMemoryPlatform("Google, {$longKeyword}, AI");

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test', 'Content');

        self::assertSame(['Google', 'AI'], $keywords);
    }

    public function testKeywordExactly100CharsIsAccepted(): void
    {
        $keyword100 = str_repeat('x', 100);
        $platform = new InMemoryPlatform("Google, {$keyword100}");

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test', 'Content');

        self::assertCount(2, $keywords);
        self::assertSame($keyword100, $keywords[1]);
    }

    public function testFallbackCalledOnEmptyResult(): void
    {
        $platform = new InMemoryPlatform(',,,'); // All empty after trim

        $fallback = $this->createMock(KeywordExtractionServiceInterface::class);
        $fallback->expects(self::once())->method('extract')
            ->with('My Title', 'My Content')
            ->willReturn(['Fallback']);

        $service = new AiKeywordExtractionService(
            $platform,
            $fallback,
            new NullLogger(),
        );

        $keywords = $service->extract('My Title', 'My Content');

        self::assertSame(['Fallback'], $keywords);
    }

    public function testKeywordsWithMbChars(): void
    {
        $platform = new InMemoryPlatform('München, São Paulo, Café');

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test', 'Content');

        self::assertCount(3, $keywords);
        self::assertSame('München', $keywords[0]);
        self::assertSame('São Paulo', $keywords[1]);
        self::assertSame('Café', $keywords[2]);
    }

    public function testHandlesNullContent(): void
    {
        $platform = new InMemoryPlatform('Keyword1, Keyword2');

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test Title', null);

        self::assertSame(['Keyword1', 'Keyword2'], $keywords);
    }
}
