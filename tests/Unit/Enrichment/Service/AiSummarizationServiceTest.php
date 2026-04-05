<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiQualityGateServiceInterface;
use App\Enrichment\Service\AiSummarizationService;
use App\Enrichment\Service\RuleBasedSummarizationService;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

#[CoversClass(AiSummarizationService::class)]
final class AiSummarizationServiceTest extends TestCase
{
    public function testUsesAiWhenSuccessful(): void
    {
        $aiSummary = 'The government announced new measures to combat rising inflation rates.';

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult($aiSummary));

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->summarize('Long article content about government economic measures and inflation...', 'Economy News');

        self::assertSame($aiSummary, $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('openrouter/free', $result->modelUsed);
    }

    public function testFallsBackToRuleBasedOnFailure(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API timeout'));

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $content = 'This is the first sentence of a long article. This is the second sentence with more detail. And a third one.';
        $result = $service->summarize($content, 'Test Title');

        self::assertStringContainsString('first sentence', $result->value ?? '');
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFallsBackOnTooShortAiResponse(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult('Short.'));

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. It should trigger the rule-based fallback.';
        $result = $service->summarize($content, 'Test Title');

        self::assertNotSame('Short.', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testRejectsSummaryThatMatchesTitle(): void
    {
        $title = 'Breaking News: Major Economic Policy Change';
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult($title));

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. It should trigger the rule-based fallback.';
        $result = $service->summarize($content, $title);

        // Summary that's just the title repeated should be rejected by quality gate
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    private function createQualityGateStub(): AiQualityGateServiceInterface
    {
        $stub = $this->createStub(AiQualityGateServiceInterface::class);
        $stub->method('validateSummary')->willReturnCallback(
            static function (string $summary, string $title): bool {
                $length = mb_strlen($summary);
                if ($length < 20 || $length > 500) {
                    return false;
                }
                similar_text(mb_strtolower($summary), mb_strtolower($title), $percent);

                return $percent < 90.0;
            },
        );

        return $stub;
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
