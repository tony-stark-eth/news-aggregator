<?php

declare(strict_types=1);

namespace App\Digest\Repository;

use App\Digest\Entity\DigestConfig;
use App\User\Entity\User;

interface DigestConfigRepositoryInterface
{
    public function findById(int $id): ?DigestConfig;

    /**
     * @return list<DigestConfig>
     */
    public function findAll(): array;

    /**
     * @return list<DigestConfig>
     */
    public function findEnabled(): array;

    public function findByNameAndUser(string $name, User $user): ?DigestConfig;

    public function save(DigestConfig $config, bool $flush = false): void;

    public function flush(): void;
}
