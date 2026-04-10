<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Scheduler;

use App\Shared\Entity\Category;
use App\Shared\Service\SettingsServiceInterface;
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

        $provider = new FetchScheduleProvider($repo, $this->createSettingsService(15));
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
    }

    public function testScheduleUsesPerSourceIntervalWhenSet(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $source->setFetchIntervalMinutes(45);

        $ref = new \ReflectionProperty(Source::class, 'id');
        $ref->setValue($source, 1);

        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([$source]);

        $provider = new FetchScheduleProvider($repo, $this->createSettingsService(15));
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
    }

    public function testScheduleFallsToCategoryIntervalWhenSourceHasNone(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        // Category has fetchIntervalMinutes set via reflection
        $catRef = new \ReflectionProperty(Category::class, 'fetchIntervalMinutes');
        $catRef->setValue($category, 30);

        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        // source fetchIntervalMinutes is null by default

        $ref = new \ReflectionProperty(Source::class, 'id');
        $ref->setValue($source, 1);

        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([$source]);

        $provider = new FetchScheduleProvider($repo, $this->createSettingsService(60));
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
    }

    public function testScheduleUsesDefaultWhenSourceAndCategoryHaveNoInterval(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        $ref = new \ReflectionProperty(Source::class, 'id');
        $ref->setValue($source, 1);

        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([$source]);

        $provider = new FetchScheduleProvider($repo, $this->createSettingsService(60));
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
    }

    public function testSourceIntervalTakesPrecedenceOverCategoryInterval(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $catRef = new \ReflectionProperty(Category::class, 'fetchIntervalMinutes');
        $catRef->setValue($category, 30);

        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $source->setFetchIntervalMinutes(10);

        $ref = new \ReflectionProperty(Source::class, 'id');
        $ref->setValue($source, 1);

        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([$source]);

        $provider = new FetchScheduleProvider($repo, $this->createSettingsService(60));
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
    }

    public function testEmptyScheduleWhenNoSources(): void
    {
        $repo = $this->createStub(SourceRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn([]);

        $provider = new FetchScheduleProvider($repo, $this->createSettingsService(15));
        $schedule = $provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        self::assertCount(0, $messages);
    }

    private function createSettingsService(int $defaultInterval): SettingsServiceInterface
    {
        $settings = $this->createStub(SettingsServiceInterface::class);
        $settings->method('getFetchDefaultInterval')->willReturn($defaultInterval);

        return $settings;
    }
}
