<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Platform;

use App\Shared\AI\Platform\ModelFailoverPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    public function testRateLimitBreaksChainIncludingPaidFallback(): void
    {
        $innerPlatform = $this->createMock(PlatformInterface::class);
        $innerPlatform->expects(self::once())->method('invoke')
            ->willThrowException(new \RuntimeException('Rate limit exceeded'));

        $logger = $this->createMock(LoggerInterface::class);

        $platform = new ModelFailoverPlatform(
            $innerPlatform,
            ['free/model-a', 'free/model-b'],
            'paid/model',
            $logger,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rate limit');
        $platform->invoke('openrouter/free', 'test prompt');
    }
}
