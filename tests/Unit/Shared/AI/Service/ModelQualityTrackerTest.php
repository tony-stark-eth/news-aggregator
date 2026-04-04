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
}
