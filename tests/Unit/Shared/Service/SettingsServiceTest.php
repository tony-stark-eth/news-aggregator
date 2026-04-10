<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Service;

use App\Shared\Entity\Setting;
use App\Shared\Repository\SettingRepositoryInterface;
use App\Shared\Service\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingsService::class)]
#[UsesClass(Setting::class)]
final class SettingsServiceTest extends TestCase
{
    private MockObject&SettingRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SettingRepositoryInterface::class);
    }

    public function testGetReturnsDbValueWhenOverridden(): void
    {
        $setting = new Setting('display_languages', 'en,de,fr');
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->with('display_languages')
            ->willReturn($setting);

        $service = $this->createService();

        self::assertSame('en,de,fr', $service->get('display_languages'));
    }

    public function testGetReturnsEnvDefaultWhenNoDbOverride(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $service = $this->createService();

        self::assertSame('en', $service->get('display_languages'));
    }

    public function testGetReturnsEmptyStringForUnknownKey(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $service = $this->createService();

        self::assertSame('', $service->get('unknown_key'));
    }

    public function testSetCreatesNewSettingWhenNoneExists(): void
    {
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->with('display_languages')
            ->willReturn(null);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (Setting $s): bool => $s->getKey() === 'display_languages' && $s->getValue() === 'en,de'),
                true,
            );

        $service = $this->createService();
        $service->set('display_languages', 'en,de');
    }

    public function testSetUpdatesExistingSettingValue(): void
    {
        $setting = new Setting('display_languages', 'en');
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->with('display_languages')
            ->willReturn($setting);

        $this->repository->expects(self::once())
            ->method('save')
            ->with($setting, true);

        $service = $this->createService();
        $service->set('display_languages', 'en,de,fr');

        self::assertSame('en,de,fr', $setting->getValue());
    }

    public function testGetAllReturnsAllSettingsWithOverrideStatus(): void
    {
        $dbSetting = new Setting('display_languages', 'en,de');
        $this->repository->method('findAll')->willReturn([$dbSetting]);

        $service = $this->createService();
        $all = $service->getAll();

        self::assertArrayHasKey('display_languages', $all);
        self::assertTrue($all['display_languages']['isOverridden']);
        self::assertSame('en,de', $all['display_languages']['value']);

        self::assertArrayHasKey('fetch_default_interval', $all);
        self::assertFalse($all['fetch_default_interval']['isOverridden']);
        self::assertSame('60', $all['fetch_default_interval']['value']);

        self::assertArrayHasKey('retention_articles', $all);
        self::assertFalse($all['retention_articles']['isOverridden']);
        self::assertSame('90', $all['retention_articles']['value']);

        self::assertArrayHasKey('retention_logs', $all);
        self::assertFalse($all['retention_logs']['isOverridden']);
        self::assertSame('30', $all['retention_logs']['value']);
    }

    public function testGetAllReturnsDefaultsWhenNoDbOverrides(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $service = $this->createService();
        $all = $service->getAll();

        foreach ($all as $entry) {
            self::assertFalse($entry['isOverridden']);
        }

        self::assertSame('en', $all['display_languages']['value']);
    }

    public function testGetDisplayLanguagesReturnsString(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $service = $this->createService();

        self::assertSame('en', $service->getDisplayLanguages());
    }

    public function testGetFetchDefaultIntervalReturnsInt(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $service = $this->createService();

        self::assertSame(60, $service->getFetchDefaultInterval());
    }

    public function testGetRetentionArticlesReturnsInt(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $service = $this->createService();

        self::assertSame(90, $service->getRetentionArticles());
    }

    public function testGetRetentionLogsReturnsInt(): void
    {
        $this->repository->method('findByKey')->willReturn(null);

        $service = $this->createService();

        self::assertSame(30, $service->getRetentionLogs());
    }

    public function testGetFetchDefaultIntervalReturnsDbOverride(): void
    {
        $setting = new Setting('fetch_default_interval', '120');
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->with('fetch_default_interval')
            ->willReturn($setting);

        $service = $this->createService();

        self::assertSame(120, $service->getFetchDefaultInterval());
    }

    public function testGetRetentionArticlesReturnsDbOverride(): void
    {
        $setting = new Setting('retention_articles', '180');
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->with('retention_articles')
            ->willReturn($setting);

        $service = $this->createService();

        self::assertSame(180, $service->getRetentionArticles());
    }

    public function testGetRetentionLogsReturnsDbOverride(): void
    {
        $setting = new Setting('retention_logs', '60');
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->with('retention_logs')
            ->willReturn($setting);

        $service = $this->createService();

        self::assertSame(60, $service->getRetentionLogs());
    }

    private function createService(): SettingsService
    {
        return new SettingsService(
            $this->repository,
            'en',
            60,
            90,
            30,
        );
    }
}
