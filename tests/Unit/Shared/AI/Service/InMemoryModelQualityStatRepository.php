<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Service;

use App\Shared\AI\Entity\ModelQualityStat;
use App\Shared\AI\Repository\ModelQualityStatRepositoryInterface;

final class InMemoryModelQualityStatRepository implements ModelQualityStatRepositoryInterface
{
    /**
     * @var array<string, ModelQualityStat>
     */
    private array $stats = [];

    private int $saveCount = 0;

    public function findByModelId(string $modelId): ?ModelQualityStat
    {
        return $this->stats[$modelId] ?? null;
    }

    /**
     * @return list<ModelQualityStat>
     */
    public function findAll(): array
    {
        return array_values($this->stats);
    }

    public function save(ModelQualityStat $stat, bool $flush = false): void
    {
        $this->stats[$stat->getModelId()] = $stat;
        $this->saveCount++;
    }

    public function flush(): void
    {
    }

    public function getSaveCount(): int
    {
        return $this->saveCount;
    }
}
