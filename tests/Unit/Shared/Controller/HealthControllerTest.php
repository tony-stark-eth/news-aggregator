<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\Controller\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class HealthControllerTest extends TestCase
{
    private Connection&MockObject $connection;

    private LoggerInterface&MockObject $logger;

    private HealthController $controller;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = new HealthController($this->connection, $this->logger);
    }

    public function testReturnsOkWhenDatabaseIsReachable(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT 1');

        $response = ($this->controller)();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
    }

    public function testReturns503WithoutLeakingDetailsWhenDatabaseIsUnreachable(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('Connection refused to host database:5432'));

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Health check failed: {error}', self::callback(
                static fn (array $ctx): bool => \is_string($ctx['error']) && str_contains($ctx['error'], 'Connection refused'),
            ));

        $response = ($this->controller)();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('{"status":"error"}', $response->getContent());
    }
}
