<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\Controller\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class HealthControllerTest extends TestCase
{
    private Connection&MockObject $connection;

    private HealthController $controller;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->controller = new HealthController($this->connection);
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

    public function testReturns503WhenDatabaseIsUnreachable(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $response = ($this->controller)();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        /** @var array{status: string, message: string} $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('error', $data['status']);
        self::assertSame('Connection refused', $data['message']);
    }
}
