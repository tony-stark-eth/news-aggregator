<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiKeywordExtractionService;
use App\Enrichment\Service\RuleBasedKeywordExtractionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

#[CoversClass(AiKeywordExtractionService::class)]
final class AiKeywordExtractionServiceTest extends TestCase
{
    public function testUsesAiWhenSuccessful(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(
            $this->makeDeferredResult('Google, Artificial Intelligence, Developers'),
        );

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

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract(
            'Microsoft Azure update released',
            'Microsoft released a major Azure cloud platform update.',
        );

        // Rule-based should extract proper nouns
        self::assertNotSame([], $keywords);
        self::assertContains('Microsoft', $keywords);
    }

    public function testFallsBackOnEmptyAiResponse(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult(''));

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract(
            'Apple launches new product',
            'Apple announced a new product at their Cupertino headquarters.',
        );

        // Empty AI response should fall back to rule-based
        self::assertContains('Apple', $keywords);
    }

    public function testTrimsKeywords(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(
            $this->makeDeferredResult(' Google ,  AI , Cloud '),
        );

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
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(
            $this->makeDeferredResult('A, B, C, D, E, F, G, H, I, J'),
        );

        $service = new AiKeywordExtractionService(
            $platform,
            new RuleBasedKeywordExtractionService(),
            new NullLogger(),
        );

        $keywords = $service->extract('Test', 'Content');

        self::assertLessThanOrEqual(8, \count($keywords));
    }

    private function makeDeferredResult(string $text): DeferredResult
    {
        $textResult = new TextResult($text);

        $rawResult = $this->createStub(RawResultInterface::class);

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);
        $converter->method('getTokenUsageExtractor')->willReturn(null);

        return new DeferredResult($converter, $rawResult);
    }
}
