<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Entity;

use App\Shared\Entity\Setting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Setting::class)]
final class SettingTest extends TestCase
{
    public function testConstructorSetsKeyAndValue(): void
    {
        $setting = new Setting('display_languages', 'en,de');

        self::assertSame('display_languages', $setting->getKey());
        self::assertSame('en,de', $setting->getValue());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $setting = new Setting('key', 'value');

        self::assertNull($setting->getId());
    }

    public function testSetValueUpdatesValue(): void
    {
        $setting = new Setting('key', 'original');

        $setting->setValue('updated');

        self::assertSame('updated', $setting->getValue());
    }
}
