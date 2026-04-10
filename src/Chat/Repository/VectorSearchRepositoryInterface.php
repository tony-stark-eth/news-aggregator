<?php

declare(strict_types=1);

namespace App\Chat\Repository;

interface VectorSearchRepositoryInterface
{
    /**
     * Find articles by cosine similarity to the given embedding vector.
     *
     * @param list<float> $queryVector
     *
     * @return list<array{id: int, similarity: float}>
     */
    public function findBySimilarity(array $queryVector, int $limit, ?\DateTimeImmutable $since = null): array;
}
