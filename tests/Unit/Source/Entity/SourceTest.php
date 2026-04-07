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
    }

    public function testRecordFailureDegrades(): void
    {
        $source = $this->createSource();

        $source->recordFailure('Connection timeout');

        self::assertSame(1, $source->getErrorCount());
        self::assertSame('Connection timeout', $source->getLastErrorMessage());
        self::assertSame(SourceHealth::Degraded, $source->getHealthStatus());
        self::assertTrue($source->isEnabled());
    }

    public function testRecordFailureThreeTimesSetsFailing(): void
    {
        $source = $this->createSource();

        $source->recordFailure('Error 1');
        $source->recordFailure('Error 2');
        $source->recordFailure('Error 3');

        self::assertSame(3, $source->getErrorCount());
        self::assertSame(SourceHealth::Failing, $source->getHealthStatus());
        self::assertTrue($source->isEnabled());
    }

    public function testRecordFailureFiveTimesDisables(): void
    {
        $source = $this->createSource();

        for ($i = 1; $i <= 5; $i++) {
            $source->recordFailure("Error {$i}");
        }

        self::assertSame(5, $source->getErrorCount());
        self::assertSame(SourceHealth::Disabled, $source->getHealthStatus());
        self::assertFalse($source->isEnabled());
    }

    public function testRecordSuccessResetsAfterFailures(): void
    {
        $source = $this->createSource();
        $source->recordFailure('Error');
        $source->recordFailure('Error');

        $source->recordSuccess(new \DateTimeImmutable('2026-04-04 14:00:00'));

        self::assertSame(0, $source->getErrorCount());
        self::assertNull($source->getLastErrorMessage());
        self::assertSame(SourceHealth::Healthy, $source->getHealthStatus());
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
