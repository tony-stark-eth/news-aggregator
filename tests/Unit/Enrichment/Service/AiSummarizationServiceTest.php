<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiQualityGateServiceInterface;
use App\Enrichment\Service\AiSummarizationService;
use App\Enrichment\Service\RuleBasedSummarizationService;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiSummarizationService::class)]
#[UsesClass(EnrichmentResult::class)]
final class AiSummarizationServiceTest extends TestCase
{
    public function testUsesAiWhenSuccessful(): void
    {
        $aiSummary = 'The government announced new measures to combat rising inflation rates.';
        $platform = new InMemoryPlatform($aiSummary);

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::once())->method('recordAcceptance')->with('openrouter/free');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
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

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::never())->method('recordAcceptance');
        $qualityTracker->expects(self::never())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('AI summarization failed'),
                self::callback(static function (array $context): bool {
                    return isset($context['error'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            $logger,
        );

        $content = 'This is the first sentence of a long article. This is the second sentence with more detail. And a third one.';
        $result = $service->summarize($content, 'Test Title');

        self::assertStringContainsString('first sentence', $result->value ?? '');
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertNull($result->modelUsed);
    }

    public function testFallsBackOnTooShortAiResponse(): void
    {
        $platform = new InMemoryPlatform('Short.');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::never())->method('recordAcceptance');
        $qualityTracker->expects(self::once())->method('recordRejection')->with('openrouter/free');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('rejected by quality gate'),
                self::callback(static function (array $context): bool {
                    return isset($context['length'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            $logger,
        );

        $content = 'This is a sufficiently long article content for testing purposes. It should trigger the rule-based fallback.';
        $result = $service->summarize($content, 'Test Title');

        self::assertNotSame('Short.', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testRejectsSummaryThatMatchesTitle(): void
    {
        $title = 'Breaking News: Major Economic Policy Change';
        $platform = new InMemoryPlatform($title);

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. It should trigger the rule-based fallback.';
        $result = $service->summarize($content, $title);

        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFallbackCalledWithOriginalArgs(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('Fail'));

        $fallback = $this->createMock(SummarizationServiceInterface::class);
        $fallback->expects(self::once())->method('summarize')
            ->with('Content text', 'My Title')
            ->willReturn(new EnrichmentResult('Fallback summary', EnrichmentMethod::RuleBased));

        $service = new AiSummarizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->summarize('Content text', 'My Title');

        self::assertSame('Fallback summary', $result->value);
    }

    public function testTruncatesContentTo2000Chars(): void
    {
        // Extremely long content should still work
        $longContent = str_repeat('This is a sentence. ', 500);
        $platform = new InMemoryPlatform('A valid AI summary of sufficient length for the quality gate.');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->summarize($longContent, 'Title');

        self::assertSame(EnrichmentMethod::Ai, $result->method);
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
}
