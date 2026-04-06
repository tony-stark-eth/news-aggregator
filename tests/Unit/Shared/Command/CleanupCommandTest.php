<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Command;

use App\Shared\Command\CleanupCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CleanupCommand::class)]
final class CleanupCommandTest extends TestCase
{
    private MockObject&Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->clock = new MockClock('2026-04-04 12:00:00');
    }

    public function testExecutesCleanup(): void
    {
        $this->connection->method('executeStatement')->willReturn(5);

        $command = new CleanupCommand($this->connection, $this->clock, 90, 30);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Cleanup complete', $tester->getDisplay());
        self::assertStringContainsString('90 days articles', $tester->getDisplay());
        self::assertStringContainsString('30 days logs', $tester->getDisplay());
    }

    public function testExecutesFourDeleteStatements(): void
    {
        $this->connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturn(0);

        $command = new CleanupCommand($this->connection, $this->clock, 90, 30);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testDeletesReadStatesFirst(): void
    {
        $callOrder = [];
        $this->connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$callOrder): int {
                $callOrder[] = $sql;

                return 0;
            });

        $command = new CleanupCommand($this->connection, $this->clock, 90, 30);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('user_article_read', $callOrder[0]);
        self::assertStringContainsString('article', $callOrder[1]);
        self::assertStringContainsString('notification_log', $callOrder[2]);
        self::assertStringContainsString('digest_log', $callOrder[3]);
    }

    public function testUsesArticleRetentionForReadStatesAndArticles(): void
    {
        $expectedArticleCutoff = $this->clock->now()->modify('-90 days')->format('Y-m-d H:i:s');

        $sqlParams = [];
        $this->connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$sqlParams): int {
                $sqlParams[] = [
                    'sql' => $sql,
                    'params' => $params,
                ];

                return 0;
            });

        $command = new CleanupCommand($this->connection, $this->clock, 90, 30);
        $tester = new CommandTester($command);
        $tester->execute([]);

        // First two statements use article retention cutoff
        self::assertSame($expectedArticleCutoff, $sqlParams[0]['params']['cutoff']);
        self::assertSame($expectedArticleCutoff, $sqlParams[1]['params']['cutoff']);
    }

    public function testUsesLogRetentionForNotificationAndDigestLogs(): void
    {
        $expectedLogCutoff = $this->clock->now()->modify('-30 days')->format('Y-m-d H:i:s');

        $sqlParams = [];
        $this->connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$sqlParams): int {
                $sqlParams[] = [
                    'sql' => $sql,
                    'params' => $params,
                ];

                return 0;
            });

        $command = new CleanupCommand($this->connection, $this->clock, 90, 30);
        $tester = new CommandTester($command);
        $tester->execute([]);

        // Last two statements use log retention cutoff
        self::assertSame($expectedLogCutoff, $sqlParams[2]['params']['cutoff']);
        self::assertSame($expectedLogCutoff, $sqlParams[3]['params']['cutoff']);
    }

    public function testDisplaysDeletedCounts(): void
    {
        $callIndex = 0;
        $this->connection->expects(self::exactly(4))
            ->method('executeStatement')
            ->willReturnCallback(function () use (&$callIndex): int {
                $counts = [3, 10, 5, 2];

                return $counts[$callIndex++];
            });

        $command = new CleanupCommand($this->connection, $this->clock, 90, 30);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('3 old read states', $display);
        self::assertStringContainsString('10 old articles', $display);
        self::assertStringContainsString('5 old notification logs', $display);
        self::assertStringContainsString('2 old digest logs', $display);
    }

    public function testUsesCustomRetentionValues(): void
    {
        $this->connection->method('executeStatement')->willReturn(0);

        $command = new CleanupCommand($this->connection, $this->clock, 180, 60);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('180 days articles', $display);
        self::assertStringContainsString('60 days logs', $display);
    }

    public function testAlwaysReturnsSuccess(): void
    {
        $this->connection->method('executeStatement')->willReturn(0);

        $command = new CleanupCommand($this->connection, $this->clock);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
