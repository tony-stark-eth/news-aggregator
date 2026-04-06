<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Service\AiDeduplicationService;
use App\Article\Service\DeduplicationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiDeduplicationService::class)]
final class AiDeduplicationServiceTest extends TestCase
{
    public function testDelegatesUrlCheckToRuleBased(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);
        $ruleBased->method('isDuplicate')->willReturn(true);

        $platform = $this->createStub(PlatformInterface::class);

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertTrue($service->isDuplicate('https://example.com/1', 'Title', null));
    }

    public function testReturnsFalseWhenRuleBasedSaysNotDuplicate(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);
        $ruleBased->method('isDuplicate')->willReturn(false);

        $platform = $this->createStub(PlatformInterface::class);

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertFalse($service->isDuplicate('https://example.com/new', 'Unique Title', null));
    }

    public function testSemanticDuplicateHandlesAiFailure(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        // Should return false on AI failure (safe default)
        self::assertFalse($service->isSemanticallyDuplicate('Title A', 'Title B'));
    }

    public function testSemanticDuplicateLoggerCalledOnFailure(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('AI dedup check failed'),
                self::callback(static function (array $context): bool {
                    return array_key_exists('error', $context)
                        && $context['error'] === 'API down';
                }),
            );

        $service = new AiDeduplicationService($ruleBased, $platform, $logger);
        $service->isSemanticallyDuplicate('Title A', 'Title B');
    }

    public function testSemanticDuplicateReturnsTrueForYes(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = new InMemoryPlatform('yes, they are about the same event');

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertTrue($service->isSemanticallyDuplicate('Title A', 'Title B'));
    }

    public function testSemanticDuplicateReturnsFalseForNo(): void
    {
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = new InMemoryPlatform('no, they are different');

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertFalse($service->isSemanticallyDuplicate('Title A', 'Title B'));
    }

    public function testSemanticDuplicateTrimsResponse(): void
    {
        // Kills UnwrapTrim mutation
        // AI returns "  yes  " with whitespace. Without trim, mb_strtolower("  yes  ") = "  yes  "
        // str_starts_with("  yes  ", "yes") → false → not duplicate (wrong)
        // With trim: "yes" → starts with "yes" → duplicate
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = new InMemoryPlatform('  yes, they cover the same event  ');

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertTrue($service->isSemanticallyDuplicate('Title A', 'Title B'));
    }

    public function testSemanticDuplicateMbStrtolowerHandlesUppercase(): void
    {
        // Kills MBString: mb_strtolower → strtolower
        // AI returns "YES" → mb_strtolower("YES") = "yes" → starts with "yes" → true
        // strtolower("YES") = "yes" too for ASCII — so need non-ASCII
        // Actually for this specific case, the response is ASCII "yes/no" so
        // mb_strtolower vs strtolower is equivalent for ASCII.
        // The mutation is practically equivalent here.
        $ruleBased = $this->createStub(DeduplicationServiceInterface::class);

        $platform = new InMemoryPlatform('YES');

        $service = new AiDeduplicationService($ruleBased, $platform, new NullLogger());

        self::assertTrue($service->isSemanticallyDuplicate('Title A', 'Title B'));
    }
}
