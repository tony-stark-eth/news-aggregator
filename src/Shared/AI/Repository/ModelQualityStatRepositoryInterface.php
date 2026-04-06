<?php

declare(strict_types=1);

namespace App\Shared\AI\Repository;

use App\Shared\AI\Entity\ModelQualityStat;

interface ModelQualityStatRepositoryInterface
{
    public function findByModelId(string $modelId): ?ModelQualityStat;

    /**
     * @return list<ModelQualityStat>
     */
    public function findAll(): array;

    public function save(ModelQualityStat $stat, bool $flush = false): void;

    public function flush(): void;
}
