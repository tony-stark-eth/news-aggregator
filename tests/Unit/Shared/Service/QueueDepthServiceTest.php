<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Service;

use App\Shared\Service\QueueDepthService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(QueueDepthService::class)]
final class QueueDepthServiceTest extends TestCase
{
    public function testGetEnrichQueueDepthReturnsCount(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')
            ->with(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue',
                [
                    'queue' => 'enrich',
                ],
            )
            ->willReturn('42');

        $service = new QueueDepthService($connection);

        self::assertSame(42, $service->getEnrichQueueDepth());
    }

    public function testGetEnrichQueueDepthReturnsZeroOnException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                'Failed to query enrich queue depth: {error}',
                self::callback(static fn (array $ctx): bool => $ctx['error'] === 'Connection refused'),
            );

        $service = new QueueDepthService($connection, $logger);

        self::assertSame(0, $service->getEnrichQueueDepth());
    }

    public function testGetEnrichQueueDepthReturnsZeroWhenCountIsZero(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')
            ->willReturn('0');

        $service = new QueueDepthService($connection);

        self::assertSame(0, $service->getEnrichQueueDepth());
    }

    public function testGetEnrichQueueDepthCastsStringToInt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')
            ->willReturn('365');

        $service = new QueueDepthService($connection);

        self::assertSame(365, $service->getEnrichQueueDepth());
    }

    public function testGetEnrichQueueDepthReturnsFalseFromFetchOne(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')
            ->willReturn(false);

        $service = new QueueDepthService($connection);

        self::assertSame(0, $service->getEnrichQueueDepth());
    }
}
