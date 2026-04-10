<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Repository;

use App\Chat\Repository\VectorSearchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(VectorSearchRepository::class)]
final class VectorSearchRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;

    private LoggerInterface&MockObject $logger;

    private VectorSearchRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->repository = new VectorSearchRepository($this->connection, $this->logger);
    }

    public function testFindBySimilarityEmptyVectorReturnsEmpty(): void
    {
        $this->connection->expects(self::never())->method('fetchAllAssociative');

        $result = $this->repository->findBySimilarity([], 10);
        self::assertSame([], $result);
    }

    public function testFindBySimilarityBuildsCorrectSqlAndParams(): void
    {
        $vector = [0.1, 0.2, 0.3];
        $capturedSql = '';
        $capturedParams = [];
        $capturedTypes = [];

        $this->connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static function (string $sql) use (&$capturedSql): bool {
                    $capturedSql = $sql;

                    return true;
                }),
                self::callback(static function (array $params) use (&$capturedParams): bool {
                    $capturedParams = $params;

                    return true;
                }),
                self::callback(static function (array $types) use (&$capturedTypes): bool {
                    $capturedTypes = $types;

                    return true;
                }),
            )
            ->willReturn([
                [
                    'id' => 1,
                    'similarity' => '0.95',
                ],
                [
                    'id' => '42',
                    'similarity' => '0.80',
                ],
            ]);

        $results = $this->repository->findBySimilarity($vector, 10);

        // Verify SQL structure
        self::assertStringStartsWith('SELECT id, 1 - (embedding <=> :query_vector::vector)', $capturedSql);
        self::assertStringContainsString('FROM article', $capturedSql);
        self::assertStringContainsString('WHERE embedding IS NOT NULL', $capturedSql);
        self::assertStringContainsString('ORDER BY embedding <=> :query_vector::vector LIMIT :limit', $capturedSql);
        self::assertStringNotContainsString('published_at', $capturedSql);

        // Verify params
        self::assertSame('[0.1,0.2,0.3]', $capturedParams['query_vector']);
        self::assertSame(10, $capturedParams['limit']);
        self::assertArrayNotHasKey('since', $capturedParams);

        // Verify types include limit as INTEGER
        self::assertSame(ParameterType::INTEGER, $capturedTypes['limit']);

        // Verify return values are cast correctly
        self::assertCount(2, $results);
        self::assertSame(1, $results[0]['id']);
        self::assertSame(0.95, $results[0]['similarity']);
        self::assertSame(42, $results[1]['id']);
        self::assertSame(0.80, $results[1]['similarity']);
    }

    public function testFindBySimilarityWithSinceAddsDateFilter(): void
    {
        $since = new \DateTimeImmutable('2026-04-01 00:00:00');
        $capturedSql = '';
        $capturedParams = [];

        $this->connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static function (string $sql) use (&$capturedSql): bool {
                    $capturedSql = $sql;

                    return true;
                }),
                self::callback(static function (array $params) use (&$capturedParams): bool {
                    $capturedParams = $params;

                    return true;
                }),
                self::anything(),
            )
            ->willReturn([]);

        $result = $this->repository->findBySimilarity([0.1], 5, $since);

        self::assertSame([], $result);
        self::assertStringContainsString('AND published_at >= :since', $capturedSql);
        self::assertSame('2026-04-01 00:00:00', $capturedParams['since']);
        self::assertSame('[0.1]', $capturedParams['query_vector']);
        self::assertSame(5, $capturedParams['limit']);
    }

    public function testFindBySimilarityLogsAndReturnsEmptyOnException(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('pgvector not installed'));

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'Vector search query failed: {error}',
                self::callback(static fn (array $ctx): bool => $ctx['error'] === 'pgvector not installed'),
            );

        $result = $this->repository->findBySimilarity([0.1], 10);
        self::assertSame([], $result);
    }

    public function testFindBySimilarityVectorStringFormat(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::anything(),
                self::callback(static fn (array $p): bool => $p['query_vector'] === '[1.5,2.5]'),
                self::anything(),
            )
            ->willReturn([]);

        $this->repository->findBySimilarity([1.5, 2.5], 5);
    }
}
