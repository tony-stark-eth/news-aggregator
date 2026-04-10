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
        return $this->findByModelIdAndCategory($modelId, 'enrichment');
    }

    public function findByModelIdAndCategory(string $modelId, string $category): ?ModelQualityStat
    {
        $key = $category . ':' . $modelId;

        return $this->stats[$key] ?? null;
    }

    /**
     * @return list<ModelQualityStat>
     */
    public function findAll(): array
    {
        return array_values($this->stats);
    }

    /**
     * @return list<ModelQualityStat>
     */
    public function findByCategory(string $category): array
    {
        return array_values(array_filter(
            $this->stats,
            static fn (ModelQualityStat $stat): bool => $stat->getCategory() === $category,
        ));
    }

    public function save(ModelQualityStat $stat, bool $flush = false): void
    {
        $key = $stat->getCategory() . ':' . $stat->getModelId();
        $this->stats[$key] = $stat;
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
