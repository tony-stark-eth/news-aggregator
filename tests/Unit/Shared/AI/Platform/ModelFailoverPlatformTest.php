<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Platform;

use App\Shared\AI\Platform\ModelFailoverPlatform;
use App\Shared\Service\QueueDepthServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(ModelFailoverPlatform::class)]
final class ModelFailoverPlatformTest extends TestCase
{
    public function testPaidFallbackIsUsedWhenAllFreeModelsFail(): void
    {
        $paidPlatform = new InMemoryPlatform('paid response');

        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('Free model 1 failed')),
                self::throwException(new \RuntimeException('Free model 2 failed')),
                self::throwException(new \RuntimeException('Free model 3 failed')),
                $paidPlatform->invoke('paid/model', 'test'),
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info');

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testPaidFallbackSkippedWhenEnvVarEmpty(): void
    {
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->method('invoke')
            ->willThrowException(new \RuntimeException('Model failed'));

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a'],
            '',
        );

        $this->expectException(\RuntimeException::class);
        $platform->invoke('openrouter/free', 'test prompt');
    }

    public function testPaidFallbackNotTriedWhenFreeModelSucceeds(): void
    {
        $platform = new ModelFailoverPlatform(
            new InMemoryPlatform('free response'),
            ['free/model-a'],
            'paid/model',
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('free response', $result->asText());
    }

    public function testRateLimitExceptionBreaksChainIncludingPaidFallback(): void
    {
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::once())->method('invoke')
            ->willThrowException(new RateLimitExceededException());

        $logger = $this->createMock(LoggerInterface::class);

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
        );

        $this->expectException(RateLimitExceededException::class);
        $platform->invoke('openrouter/free', 'test prompt');
    }

    public function testNonRateLimitExceptionContinuesChain(): void
    {
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::exactly(3))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('Some error with Rate limit in message')),
                self::throwException(new \RuntimeException('Another error')),
                new InMemoryPlatform('paid response')->invoke('paid/model', 'test'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a'],
            'paid/model',
            $logger,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    // --- Queue-aware routing tests ---

    public function testQueueBelowAccelerateThresholdUsesFullChain(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(10);

        // All 4 models should be tried: primary + 2 free fallbacks + paid
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::exactly(4))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('fail 1')),
                self::throwException(new \RuntimeException('fail 2')),
                self::throwException(new \RuntimeException('fail 3')),
                new InMemoryPlatform('paid response')->invoke('paid/model', 'test'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueAtAccelerateThresholdTriesPrimaryFreeThenPaid(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(20);

        // Only 2 models: primary free + paid (free fallbacks skipped)
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::exactly(2))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('free failed')),
                new InMemoryPlatform('paid response')->invoke('paid/model', 'test'),
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info')
            ->with(
                self::callback(static fn (string $msg): bool => str_contains($msg, 'Queue depth') || str_contains($msg, 'failed')),
                self::callback(static fn (array $ctx): bool => isset($ctx['depth']) || isset($ctx['model'])),
            );

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueAboveAccelerateBelowSkipUsesAcceleratedChain(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(35);

        // Accelerated: primary free succeeds, paid never tried
        $innerPlatform = new InMemoryPlatform('free response');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::callback(static fn (string $msg): bool => str_contains($msg, 'accelerating')),
                self::callback(static fn (array $ctx): bool => $ctx['depth'] === 35 && $ctx['threshold'] === 20),
            );

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('free response', $result->asText());
    }

    public function testQueueAtSkipFreeThresholdUsesPaidDirectly(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(50);

        // Only paid model tried — exactly 1 invoke
        $paidResult = new InMemoryPlatform('paid response')->invoke('paid/model', 'test');
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::once())->method('invoke')
            ->with('paid/model', 'test prompt', [])
            ->willReturn($paidResult);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::callback(static fn (string $msg): bool => str_contains($msg, 'skipping free models')),
                self::callback(static fn (array $ctx): bool => $ctx['depth'] === 50 && $ctx['threshold'] === 50),
            );

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueAboveSkipFreeThresholdUsesPaidDirectly(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(365);

        $paidResult = new InMemoryPlatform('paid response')->invoke('paid/model', 'test');
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::once())->method('invoke')
            ->with('paid/model', 'test prompt', [])
            ->willReturn($paidResult);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::callback(static fn (string $msg): bool => str_contains($msg, 'skipping free models')),
                self::callback(static fn (array $ctx): bool => $ctx['depth'] === 365 && $ctx['threshold'] === 50),
            );

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueAwareRoutingDisabledWhenNoPaidModel(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::never())->method('getEnrichQueueDepth');

        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->method('invoke')
            ->willThrowException(new \RuntimeException('all failed'));

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a'],
            '',
            new NullLogger(),
            $queueDepth,
        );

        $this->expectException(\RuntimeException::class);
        $platform->invoke('openrouter/free', 'test prompt');
    }

    public function testQueueAwareRoutingDisabledWhenNoQueueService(): void
    {
        // No queue service = null → full chain used
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::exactly(3))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('fail 1')),
                self::throwException(new \RuntimeException('fail 2')),
                new InMemoryPlatform('paid response')->invoke('paid/model', 'test'),
            );

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a'],
            'paid/model',
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueAtExactlyBoundaryBelowAccelerate(): void
    {
        // queue=19, threshold=20 → normal chain
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(19);

        // All 3 models tried: primary + free-a + paid
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::exactly(3))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('fail 1')),
                self::throwException(new \RuntimeException('fail 2')),
                new InMemoryPlatform('paid response')->invoke('paid/model', 'test'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueAtExactlyBoundaryBetweenAccelerateAndSkip(): void
    {
        // queue=49, threshold accelerate=20, skip=50 → accelerated (primary + paid)
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(49);

        // Only 2 models: primary free + paid
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::exactly(2))->method('invoke')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('free failed')),
                new InMemoryPlatform('paid response')->invoke('paid/model', 'test'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('paid response', $result->asText());
    }

    public function testQueueZeroUsesFullChain(): void
    {
        $queueDepth = $this->createMock(QueueDepthServiceInterface::class);
        $queueDepth->expects(self::once())->method('getEnrichQueueDepth')->willReturn(0);

        $innerPlatform = new InMemoryPlatform('free response');

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a'],
            'paid/model',
            new NullLogger(),
            $queueDepth,
            20,
            50,
        );

        $result = $platform->invoke('openrouter/free', 'test prompt');

        self::assertSame('free response', $result->asText());
    }
}
