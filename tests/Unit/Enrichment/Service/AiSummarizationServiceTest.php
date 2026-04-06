<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiQualityGateServiceInterface;
use App\Enrichment\Service\AiSummarizationService;
use App\Enrichment\Service\AiTextCleanupService;
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
            new AiTextCleanupService(),
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
                        && isset($context['attempt'])
                        && isset($context['max'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            $logger,
            new AiTextCleanupService(),
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
                self::stringContains('AI summary rejected'),
                self::callback(static function (array $context): bool {
                    return isset($context['length'])
                        && isset($context['model'])
                        && isset($context['attempt'])
                        && isset($context['max'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            $logger,
            new AiTextCleanupService(),
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
            new AiTextCleanupService(),
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
            new AiTextCleanupService(),
        );

        $result = $service->summarize('Content text', 'My Title');

        self::assertSame('Fallback summary', $result->value);
    }

    public function testTruncatesContentTo2000Chars(): void
    {
        $longContent = str_repeat('This is a sentence. ', 500);
        $platform = new InMemoryPlatform('A valid AI summary of sufficient length for the quality gate.');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $result = $service->summarize($longContent, 'Title');

        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testMbSubstrUsedForContentTruncationWithMultibyte(): void
    {
        $multibyteContent = str_repeat('日', 2001);
        $platform = new InMemoryPlatform('A valid AI summary that passes quality gate.');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $result = $service->summarize($multibyteContent, 'Title');

        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('A valid AI summary that passes quality gate.', $result->value);
    }

    public function testMbStrlenUsedInRejectionLogWithMultibyte(): void
    {
        $platform = new InMemoryPlatform('Short.');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('AI summary rejected'),
                self::callback(static function (array $context): bool {
                    return $context['length'] === 6
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            $logger,
            new AiTextCleanupService(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. Second sentence here for rule based.';
        $service->summarize($content, 'Test Title');
    }

    public function testQualityTrackerRecordRejectionCalledWithModel(): void
    {
        $platform = new InMemoryPlatform('Short.');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::once())->method('recordRejection')
            ->with('openrouter/free');
        $qualityTracker->expects(self::never())->method('recordAcceptance');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. Second sentence here.';
        $service->summarize($content, 'Title');
    }

    public function testPlatformInvokeCalledOnceOnFailure(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with('openrouter/free', self::anything())
            ->willThrowException(new \RuntimeException('Expected'));

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. Second sentence here.';
        $service->summarize($content, 'Title');
    }

    public function testMbSubstrStartPositionZero(): void
    {
        $platform = new InMemoryPlatform('A valid summary of sufficient length for the quality gate check.');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $result = $service->summarize('Zcontent text here', 'Title');
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testMbStrlenInRejectionLogWithMultibyteChars(): void
    {
        $multibyteSummary = str_repeat('日', 5); // 5 mb chars, 15 bytes
        $platform = new InMemoryPlatform($multibyteSummary);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('AI summary rejected'),
                self::callback(static function (array $context): bool {
                    // mb_strlen("日日日日日") = 5, strlen would give 15
                    return $context['length'] === 5
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            $logger,
            new AiTextCleanupService(),
        );

        $content = 'This is long enough content for the summarization service to process properly.';
        $service->summarize($content, 'Title');
    }

    public function testTrimCalledOnAiResponse(): void
    {
        $platform = new InMemoryPlatform('  A valid AI summary of sufficient length for the quality gate.  ');

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $result = $service->summarize('Long content text here', 'Title');

        self::assertSame('A valid AI summary of sufficient length for the quality gate.', $result->value);
    }

    public function testRejectedAttemptFallsBackToRuleBased(): void
    {
        $platform = new InMemoryPlatform('Short.');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::once())->method('recordRejection');
        $qualityTracker->expects(self::never())->method('recordAcceptance');

        $fallback = $this->createMock(SummarizationServiceInterface::class);
        $fallback->expects(self::once())->method('summarize')
            ->with('Content text', 'Title')
            ->willReturn(new EnrichmentResult('Rule-based summary', EnrichmentMethod::RuleBased));

        $service = new AiSummarizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $result = $service->summarize('Content text', 'Title');

        self::assertSame('Rule-based summary', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testExceptionFallsBackToRuleBased(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::never())->method('recordAcceptance');
        $qualityTracker->expects(self::never())->method('recordRejection');

        $fallback = $this->createMock(SummarizationServiceInterface::class);
        $fallback->expects(self::once())->method('summarize')
            ->with('Content text', 'Title')
            ->willReturn(new EnrichmentResult('Rule-based summary', EnrichmentMethod::RuleBased));

        $service = new AiSummarizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $result = $service->summarize('Content text', 'Title');

        self::assertSame('Rule-based summary', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFirstAttemptSucceedsReturnsImmediately(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->willReturnCallback(
                static fn (): mixed => new InMemoryPlatform('A valid AI summary of sufficient length for the quality gate.')->invoke('openrouter/free', []),
            );

        $service = new AiSummarizationService(
            $platform,
            new RuleBasedSummarizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
            new AiTextCleanupService(),
        );

        $content = 'This is a sufficiently long article content for testing purposes. Second sentence here.';
        $result = $service->summarize($content, 'Title');

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
