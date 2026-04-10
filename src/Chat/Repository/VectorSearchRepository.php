<?php

declare(strict_types=1);

namespace App\Chat\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final readonly class VectorSearchRepository implements VectorSearchRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function findBySimilarity(array $queryVector, int $limit, ?\DateTimeImmutable $since = null): array
    {
        if ($queryVector === []) {
            return [];
        }

        $vectorString = '[' . implode(',', $queryVector) . ']';

        $sql = 'SELECT id, 1 - (embedding <=> :query_vector::vector) AS similarity'
            . ' FROM article'
            . ' WHERE embedding IS NOT NULL';

        $params = [
            'query_vector' => $vectorString,
            'limit' => $limit,
        ];
        $types = [
            'limit' => ParameterType::INTEGER,
        ];

        if ($since instanceof \DateTimeImmutable) {
            $sql .= ' AND published_at >= :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY embedding <=> :query_vector::vector LIMIT :limit';

        try {
            /** @var list<array{id: int|string, similarity: float|string}> $rows */
            $rows = $this->connection->fetchAllAssociative($sql, $params, $types);
        } catch (\Throwable $e) {
            $this->logger->warning('Vector search query failed: {error}', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'similarity' => (float) $row['similarity'],
            ],
            $rows,
        );
    }
}
