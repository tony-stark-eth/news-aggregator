<?php

declare(strict_types=1);

namespace App\Digest\Repository;

use App\Digest\Entity\DigestLog;

interface DigestLogRepositoryInterface
{
    public function findById(int $id): ?DigestLog;

    /**
     * @return list<DigestLog>
     */
    public function findRecent(int $limit): array;

    public function save(DigestLog $log, bool $flush = false): void;

    public function flush(): void;
}
