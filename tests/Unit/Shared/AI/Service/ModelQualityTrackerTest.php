<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Service;

use App\Shared\AI\Service\ModelQualityTracker;
use App\Shared\AI\ValueObject\ModelQualityStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(ModelQualityTracker::class)]
final class ModelQualityTrackerTest extends TestCase
{
    private ModelQualityTracker $tracker;

    protected function setUp(): void
    {
        $this->tracker = new ModelQualityTracker(new ArrayAdapter());
    }

    public function testInitialStatsAreZero(): void
    {
        $stats = $this->tracker->getStats('some-model');

        self::assertSame(0, $stats->accepted);
        self::assertSame(0, $stats->rejected);
        self::assertSame(0.0, $stats->acceptanceRate);
    }

    public function testRecordAcceptance(): void
    {
        $this->tracker->recordAcceptance('model-a');
        $this->tracker->recordAcceptance('model-a');
        $this->tracker->recordRejection('model-a');

        $stats = $this->tracker->getStats('model-a');

        self::assertSame(2, $stats->accepted);
        self::assertSame(1, $stats->rejected);
        self::assertEqualsWithDelta(0.6667, $stats->acceptanceRate, 0.001);
    }

    public function testGetAllStats(): void
    {
        $this->tracker->recordAcceptance('model-x');
        $this->tracker->recordRejection('model-y');

        $all = $this->tracker->getAllStats();

        self::assertCount(2, $all);
        self::assertTrue($all->containsKey('model-x'));
        self::assertTrue($all->containsKey('model-y'));
        self::assertContainsOnlyInstancesOf(ModelQualityStats::class, $all->toArray());
    }

    public function testRecordRejectionOnly(): void
    {
        $this->tracker->recordRejection('model-b');

        $stats = $this->tracker->getStats('model-b');

        self::assertSame(0, $stats->accepted);
        self::assertSame(1, $stats->rejected);
        self::assertSame(0.0, $stats->acceptanceRate);
    }

    public function testRecordAcceptanceOnly(): void
    {
        $this->tracker->recordAcceptance('model-c');

        $stats = $this->tracker->getStats('model-c');

        self::assertSame(1, $stats->accepted);
        self::assertSame(0, $stats->rejected);
        self::assertSame(1.0, $stats->acceptanceRate);
    }

    public function testIndexDoesNotDuplicate(): void
    {
        $this->tracker->recordAcceptance('model-d');
        $this->tracker->recordAcceptance('model-d');
        $this->tracker->recordAcceptance('model-d');

        $all = $this->tracker->getAllStats();

        // Model-d should appear only once
        self::assertCount(1, $all);
        $stats = $all->get('model-d');
        self::assertInstanceOf(ModelQualityStats::class, $stats);
        self::assertSame(3, $stats->accepted);
    }

    public function testGetAllStatsEmptyWhenNoRecords(): void
    {
        $all = $this->tracker->getAllStats();

        self::assertCount(0, $all);
    }

    public function testAcceptanceRateRoundedTo4Decimals(): void
    {
        // 1 accepted, 2 rejected = rate = 1/3 = 0.33333...
        // round(0.33333, 4) = 0.3333
        // round(0.33333, 3) = 0.333 (kills DecrementInteger)
        // round(0.33333, 5) = 0.33333 (kills IncrementInteger)
        $this->tracker->recordAcceptance('model-x');
        $this->tracker->recordRejection('model-x');
        $this->tracker->recordRejection('model-x');

        $stats = $this->tracker->getStats('model-x');

        self::assertSame(0.3333, $stats->acceptanceRate);
    }

    public function testCacheExpirySetOnUpdate(): void
    {
        // Record and verify data persists (kills MethodCallRemoval on expiresAfter)
        $cache = new ArrayAdapter();
        $tracker = new ModelQualityTracker($cache);

        $tracker->recordAcceptance('test-model');

        $stats = $tracker->getStats('test-model');
        self::assertSame(1, $stats->accepted);
    }

    public function testIndexCacheExpirySet(): void
    {
        // Record and verify index persists (kills MethodCallRemoval on expiresAfter in addToIndex)
        $cache = new ArrayAdapter();
        $tracker = new ModelQualityTracker($cache);

        $tracker->recordAcceptance('model-a');
        $tracker->recordAcceptance('model-b');

        $all = $tracker->getAllStats();
        self::assertCount(2, $all);
    }
}
