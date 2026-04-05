<?php

declare(strict_types=1);

namespace App\Source\Repository;

use App\Source\Entity\Source;

interface SourceRepositoryInterface
{
    public function findById(int $id): ?Source;

    /**
     * @return list<Source>
     */
    public function findAll(): array;

    /**
     * @return list<Source>
     */
    public function findEnabled(): array;

    public function findByFeedUrl(string $feedUrl): ?Source;

    public function countEnabled(): int;

    public function save(Source $source, bool $flush = false): void;

    public function remove(Source $source, bool $flush = false): void;

    public function flush(): void;
}
