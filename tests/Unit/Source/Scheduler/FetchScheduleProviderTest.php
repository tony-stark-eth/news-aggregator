<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Scheduler;

use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Scheduler\FetchScheduleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FetchScheduleProvider::class)]
final class FetchScheduleProviderTest extends TestCase
{
    public function testScheduleContainsEnabledSources(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        // We need to set the ID via reflection since it's auto-generated
        $ref = new \ReflectionProperty(Source::class, 'id');
        $ref->setValue($source, 1);

        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([$source]);

        $provider = new FetchScheduleProvider($repo);
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
    }

    public function testEmptyScheduleWhenNoSources(): void
    {
        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([]);

        $provider = new FetchScheduleProvider($repo);
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(0, $messages);
    }
}
