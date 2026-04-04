<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiCategorizationService;
use App\Enrichment\Service\AiQualityGateService;
use App\Enrichment\Service\RuleBasedCategorizationService;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

#[CoversClass(AiCategorizationService::class)]
final class AiCategorizationServiceTest extends TestCase
{
    public function testUsesAiWhenSuccessful(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult('tech'));

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            new AiQualityGateService(),
            new NullLogger(),
        );

        $result = $service->categorize('Google AI announcement', 'New AI model for developers');

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('openrouter/auto', $result->modelUsed);
    }

    public function testFallsBackToRuleBasedOnFailure(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            new AiQualityGateService(),
            new NullLogger(),
        );

        // Rule-based should still categorize based on keywords
        $result = $service->categorize(
            'Parliament votes on new election law',
            'The government coalition passed the new policy with opposition support.',
        );

        self::assertSame('politics', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFallsBackOnInvalidAiResponse(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult('invalid_category'));

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            new AiQualityGateService(),
            new NullLogger(),
        );

        // AI returns invalid slug, should fall back to rule-based
        $result = $service->categorize(
            'Scientists discover new quantum physics breakthrough',
            'The research team published their experiment results.',
        );

        self::assertSame('science', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
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
