<?php

declare(strict_types=1);

namespace App\Shared\AI\Repository;

use App\Shared\AI\Entity\ModelQualityStat;

interface ModelQualityStatRepositoryInterface
{
    public function findByModelId(string $modelId): ?ModelQualityStat;

    public function findByModelIdAndCategory(string $modelId, string $category): ?ModelQualityStat;

    /**
     * @return list<ModelQualityStat>
     */
    public function findAll(): array;

    /**
     * @return list<ModelQualityStat>
     */
    public function findByCategory(string $category): array;

    public function save(ModelQualityStat $stat, bool $flush = false): void;

    public function flush(): void;
}
