<?php

declare(strict_types=1);

namespace App\Tests\Unit\Digest\Entity;

use App\Digest\Entity\DigestConfig;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DigestConfig::class)]
final class DigestConfigTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $config = new DigestConfig('Daily Tech', '0 8 * * *', $user, new \DateTimeImmutable('2026-04-04'));

        self::assertNull($config->getId());
        self::assertSame('Daily Tech', $config->getName());
        self::assertSame('0 8 * * *', $config->getSchedule());
        self::assertSame(10, $config->getArticleLimit());
        self::assertSame([], $config->getCategories());
        self::assertTrue($config->isEnabled());
        self::assertNull($config->getLastRunAt());
    }

    public function testSetCategories(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());
        $config->setCategories(['tech', 'science']);

        self::assertSame(['tech', 'science'], $config->getCategories());
    }
}
