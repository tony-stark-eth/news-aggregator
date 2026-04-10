<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Entity;

use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\ValueObject\SourceHealth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Source::class)]
final class SourceTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $source = $this->createSource();

        self::assertNull($source->getId());
        self::assertSame('Ars Technica', $source->getName());
        self::assertSame('https://feeds.arstechnica.com/arstechnica/index', $source->getFeedUrl());
        self::assertSame('Tech', $source->getCategory()->getName());
        self::assertTrue($source->isEnabled());
        self::assertSame(0, $source->getErrorCount());
        self::assertNull($source->getLastErrorMessage());
        self::assertSame(SourceHealth::Healthy, $source->getHealthStatus());
        self::assertNull($source->getLastFetchedAt());
        self::assertNull($source->getFetchIntervalMinutes());
        self::assertNull($source->getLastErrorAt());
        self::assertSame(0, $source->getSuccessCount());
        self::assertSame(0, $source->getFailureCount());
        self::assertNull($source->getReliabilityWeight());
    }

    public function testRecordSuccess(): void
    {
        $source = $this->createSource();
        $fetchedAt = new \DateTimeImmutable('2026-04-04 13:00:00');

        $source->recordSuccess($fetchedAt);

        self::assertSame(0, $source->getErrorCount());
        self::assertNull($source->getLastErrorMessage());
        self::assertSame(SourceHealth::Healthy, $source->getHealthStatus());
        self::assertSame($fetchedAt, $source->getLastFetchedAt());
        self::assertSame(1, $source->getSuccessCount());
    }

    public function testRecordFailureDegrades(): void
    {
        $source = $this->createSource();
        $failedAt = new \DateTimeImmutable('2026-04-04 13:00:00');

        $source->recordFailure('Connection timeout', $failedAt);

        self::assertSame(1, $source->getErrorCount());
        self::assertSame('Connection timeout', $source->getLastErrorMessage());
        self::assertSame(SourceHealth::Degraded, $source->getHealthStatus());
        self::assertTrue($source->isEnabled());
        self::assertSame($failedAt, $source->getLastErrorAt());
        self::assertSame(1, $source->getFailureCount());
    }

    public function testRecordFailureThreeTimesSetsFailing(): void
    {
        $source = $this->createSource();

        $source->recordFailure('Error 1', new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordFailure('Error 2', new \DateTimeImmutable('2026-04-04 14:00:00'));
        $source->recordFailure('Error 3', new \DateTimeImmutable('2026-04-04 15:00:00'));

        self::assertSame(3, $source->getErrorCount());
        self::assertSame(SourceHealth::Failing, $source->getHealthStatus());
        self::assertTrue($source->isEnabled());
    }

    public function testRecordFailureFiveTimesDisables(): void
    {
        $source = $this->createSource();

        for ($i = 1; $i <= 5; $i++) {
            $source->recordFailure("Error {$i}", new \DateTimeImmutable('2026-04-04 13:00:00'));
        }

        self::assertSame(5, $source->getErrorCount());
        self::assertSame(SourceHealth::Disabled, $source->getHealthStatus());
        self::assertFalse($source->isEnabled());
    }

    public function testRecordSuccessResetsAfterFailures(): void
    {
        $source = $this->createSource();
        $source->recordFailure('Error', new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordFailure('Error', new \DateTimeImmutable('2026-04-04 14:00:00'));

        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 14:00:00'));

        self::assertSame(0, $source->getErrorCount());
        self::assertNull($source->getLastErrorMessage());
        self::assertSame(SourceHealth::Healthy, $source->getHealthStatus());
    }

    public function testRecordSuccessIncrementsSuccessCount(): void
    {
        $source = $this->createSource();

        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 14:00:00'));
        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 15:00:00'));

        self::assertSame(3, $source->getSuccessCount());
        self::assertSame(0, $source->getFailureCount());
    }

    public function testRecordFailureIncrementsFailureCount(): void
    {
        $source = $this->createSource();

        $source->recordFailure('Error 1', new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordFailure('Error 2', new \DateTimeImmutable('2026-04-04 14:00:00'));

        self::assertSame(0, $source->getSuccessCount());
        self::assertSame(2, $source->getFailureCount());
    }

    public function testSuccessRateCalculation(): void
    {
        $source = $this->createSource();

        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 14:00:00'));
        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 15:00:00'));
        $source->recordFailure('Error', new \DateTimeImmutable('2026-04-04 16:00:00'));

        // 3 successes / 4 total = 75.0%
        self::assertSame(75.0, $source->getSuccessRate());
    }

    public function testSuccessRateReturnsNullWithNoFetches(): void
    {
        $source = $this->createSource();

        self::assertNull($source->getSuccessRate());
    }

    public function testSuccessRateWithAllSuccesses(): void
    {
        $source = $this->createSource();
        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 13:00:00'));

        self::assertSame(100.0, $source->getSuccessRate());
    }

    public function testSuccessRateWithAllFailures(): void
    {
        $source = $this->createSource();
        $source->recordFailure('Error', new \DateTimeImmutable('2026-04-04 13:00:00'));

        self::assertSame(0.0, $source->getSuccessRate());
    }

    public function testLastErrorAtUpdatedOnFailure(): void
    {
        $source = $this->createSource();
        $firstError = new \DateTimeImmutable('2026-04-04 13:00:00');
        $secondError = new \DateTimeImmutable('2026-04-04 14:00:00');

        $source->recordFailure('First', $firstError);
        self::assertSame($firstError, $source->getLastErrorAt());

        $source->recordFailure('Second', $secondError);
        self::assertSame($secondError, $source->getLastErrorAt());
    }

    public function testReliabilityWeightGetterAndSetter(): void
    {
        $source = $this->createSource();

        self::assertNull($source->getReliabilityWeight());

        $source->setReliabilityWeight(0.9);
        self::assertSame(0.9, $source->getReliabilityWeight());

        $source->setReliabilityWeight(null);
        self::assertNull($source->getReliabilityWeight());
    }

    public function testReliabilityWeightBoundaryZero(): void
    {
        $source = $this->createSource();

        $source->setReliabilityWeight(0.0);
        self::assertSame(0.0, $source->getReliabilityWeight());
    }

    public function testReliabilityWeightBoundaryOne(): void
    {
        $source = $this->createSource();

        $source->setReliabilityWeight(1.0);
        self::assertSame(1.0, $source->getReliabilityWeight());
    }

    public function testSuccessCountAccumulatesAcrossRecovery(): void
    {
        $source = $this->createSource();

        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordFailure('Error', new \DateTimeImmutable('2026-04-04 14:00:00'));
        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 15:00:00'));

        // errorCount resets on success, but successCount/failureCount accumulate
        self::assertSame(0, $source->getErrorCount());
        self::assertSame(2, $source->getSuccessCount());
        self::assertSame(1, $source->getFailureCount());
    }

    public function testFetchIntervalMinutesGetterAndSetter(): void
    {
        $source = $this->createSource();

        self::assertNull($source->getFetchIntervalMinutes());

        $source->setFetchIntervalMinutes(30);
        self::assertSame(30, $source->getFetchIntervalMinutes());

        $source->setFetchIntervalMinutes(null);
        self::assertNull($source->getFetchIntervalMinutes());
    }

    private function createSource(): Source
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');

        return new Source(
            'Ars Technica',
            'https://feeds.arstechnica.com/arstechnica/index',
            $category,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
    }
}
