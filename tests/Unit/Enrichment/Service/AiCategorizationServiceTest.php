<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiCategorizationService;
use App\Enrichment\Service\AiQualityGateServiceInterface;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\RuleBasedCategorizationService;
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

#[CoversClass(AiCategorizationService::class)]
#[UsesClass(EnrichmentResult::class)]
final class AiCategorizationServiceTest extends TestCase
{
    public function testUsesAiWhenSuccessful(): void
    {
        $platform = new InMemoryPlatform('tech');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::once())->method('recordAcceptance')->with('openrouter/free');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
        );

        $result = $service->categorize('Google AI announcement', 'New AI model for developers');

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('openrouter/free', $result->modelUsed);
    }

    public function testFallsBackToRuleBasedOnFailure(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::never())->method('recordAcceptance');
        $qualityTracker->expects(self::never())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('AI categorization failed'),
                self::callback(static function (array $context): bool {
                    return isset($context['error'])
                        && isset($context['model'])
                        && isset($context['attempt'])
                        && isset($context['max'])
                        && $context['error'] === 'API down'
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            $logger,
        );

        $result = $service->categorize(
            'Parliament votes on new election law',
            'The government coalition passed the new policy with opposition support.',
        );

        self::assertSame('politics', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertNull($result->modelUsed);
    }

    public function testFallsBackOnInvalidAiResponse(): void
    {
        $platform = new InMemoryPlatform('invalid_category');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::never())->method('recordAcceptance');
        $qualityTracker->expects(self::once())->method('recordRejection')->with('openrouter/free');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('AI categorization rejected'),
                self::callback(static function (array $context): bool {
                    return isset($context['slug'])
                        && isset($context['model'])
                        && isset($context['attempt'])
                        && isset($context['max'])
                        && $context['slug'] === 'invalid_category'
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            $logger,
        );

        $result = $service->categorize(
            'Scientists discover new quantum physics breakthrough',
            'The research team published their experiment results.',
        );

        self::assertSame('science', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testTruncatesContentTo1000Chars(): void
    {
        $longContent = str_repeat('The government passed a new policy. ', 100);
        $platform = new InMemoryPlatform('politics');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Policy Update', $longContent);

        self::assertSame('politics', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testHandlesNullContent(): void
    {
        $platform = new InMemoryPlatform('tech');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Tech News Title', null);

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testFallbackServiceCalledOnRejection(): void
    {
        $platform = new InMemoryPlatform('invalid_slug');

        $fallback = $this->createMock(CategorizationServiceInterface::class);
        $fallback->expects(self::once())->method('categorize')
            ->with('Title', 'Content')
            ->willReturn(new EnrichmentResult('tech', EnrichmentMethod::RuleBased));

        $service = new AiCategorizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Title', 'Content');

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testTrimsAndLowercasesAiResponse(): void
    {
        $platform = new InMemoryPlatform('  TECH  ');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Test', 'Content');

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testMbStrtolowerUsedOnAiResponseWithUmlauts(): void
    {
        $platform = new InMemoryPlatform('  Science  ');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Test', 'Content');

        self::assertSame('science', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testMbSubstrUsedForContentTruncationWithMultibyte(): void
    {
        $multibyteContent = str_repeat('日', 1001);
        $platform = new InMemoryPlatform('tech');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Tech Title', $multibyteContent);

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testQualityTrackerRecordRejectionOnInvalidSlug(): void
    {
        $platform = new InMemoryPlatform('nonsense');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::once())->method('recordRejection')
            ->with('openrouter/free');
        $qualityTracker->expects(self::never())->method('recordAcceptance');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
        );

        $service->categorize('Test', 'Content');
    }

    public function testLoggerInfoOnRejectionWithContext(): void
    {
        $platform = new InMemoryPlatform('invalid');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('AI categorization rejected'),
                self::callback(static function (array $context): bool {
                    return $context['slug'] === 'invalid'
                        && $context['model'] === 'openrouter/free'
                        && isset($context['attempt'])
                        && isset($context['max']);
                }),
            );

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            $logger,
        );

        $service->categorize('Test', 'Content');
    }

    public function testPlatformInvokeCalledOnceOnFailure(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with('openrouter/free', self::anything())
            ->willThrowException(new \RuntimeException('Expected'));

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $service->categorize('Test', 'Content');
    }

    public function testMbSubstrContentStartsAtZero(): void
    {
        $platform = new InMemoryPlatform('tech');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Title', 'Zcontent here software developer cloud');

        self::assertSame('tech', $result->value);
    }

    public function testMbSubstrContentTruncatesExactly1000(): void
    {
        $platform = new InMemoryPlatform('tech');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Title', str_repeat('a', 1001));
        self::assertSame('tech', $result->value);
    }

    public function testCoalesceOnNullContent(): void
    {
        $platform = new InMemoryPlatform('tech');

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Title', 'software developer cloud api');
        self::assertSame('tech', $result->value);
    }

    public function testFallbackReceivesOriginalTitleAndContent(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('fail'));

        $fallback = $this->createMock(CategorizationServiceInterface::class);
        $fallback->expects(self::once())->method('categorize')
            ->with('My Title', 'My Content')
            ->willReturn(new EnrichmentResult('tech', EnrichmentMethod::RuleBased));

        $service = new AiCategorizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('My Title', 'My Content');

        self::assertSame('tech', $result->value);
    }

    public function testRejectedAttemptFallsBackToRuleBased(): void
    {
        $platform = new InMemoryPlatform('invalid_slug');

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::once())->method('recordRejection');
        $qualityTracker->expects(self::never())->method('recordAcceptance');

        $fallback = $this->createMock(CategorizationServiceInterface::class);
        $fallback->expects(self::once())->method('categorize')
            ->with('Title', 'Content')
            ->willReturn(new EnrichmentResult('tech', EnrichmentMethod::RuleBased));

        $service = new AiCategorizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
        );

        $result = $service->categorize('Title', 'Content');

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testExceptionFallsBackToRuleBased(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $qualityTracker = $this->createMock(ModelQualityTrackerInterface::class);
        $qualityTracker->expects(self::never())->method('recordAcceptance');
        $qualityTracker->expects(self::never())->method('recordRejection');

        $fallback = $this->createMock(CategorizationServiceInterface::class);
        $fallback->expects(self::once())->method('categorize')
            ->with('Title', 'Content')
            ->willReturn(new EnrichmentResult('tech', EnrichmentMethod::RuleBased));

        $service = new AiCategorizationService(
            $platform,
            $fallback,
            $this->createQualityGateStub(),
            $qualityTracker,
            new NullLogger(),
        );

        $result = $service->categorize('Title', 'Content');

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFirstAttemptSucceedsReturnsImmediately(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->willReturnCallback(
                static fn (): mixed => new InMemoryPlatform('tech')->invoke('openrouter/free', []),
            );

        $service = new AiCategorizationService(
            $platform,
            new RuleBasedCategorizationService(),
            $this->createQualityGateStub(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );

        $result = $service->categorize('Test', 'Content');

        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    private function createQualityGateStub(): AiQualityGateServiceInterface
    {
        $stub = $this->createStub(AiQualityGateServiceInterface::class);
        $stub->method('validateCategorization')->willReturnCallback(
            static fn (string $slug): bool => in_array($slug, ['politics', 'business', 'tech', 'science', 'sports'], true),
        );
        $stub->method('validateSummary')->willReturn(true);

        return $stub;
    }
}
