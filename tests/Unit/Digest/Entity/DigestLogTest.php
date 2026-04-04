<?php

declare(strict_types=1);

namespace App\Tests\Unit\Digest\Entity;

use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DigestLog::class)]
final class DigestLogTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $config = new DigestConfig('Daily', '0 8 * * *', $user, new \DateTimeImmutable());
        $log = new DigestLog($config, new \DateTimeImmutable('2026-04-04 08:00:00'), 5, 'Digest content...', true);

        self::assertNull($log->getId());
        self::assertSame($config, $log->getDigestConfig());
        self::assertSame(5, $log->getArticleCount());
        self::assertSame('Digest content...', $log->getContent());
        self::assertTrue($log->isDeliverySuccess());
    }
}
