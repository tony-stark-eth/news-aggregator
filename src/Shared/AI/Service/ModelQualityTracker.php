<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\Entity\ModelQualityStat;
use App\Shared\AI\Repository\ModelQualityStatRepositoryInterface;
use App\Shared\AI\ValueObject\ModelQualityCategory;
use App\Shared\AI\ValueObject\ModelQualityStats;
use App\Shared\AI\ValueObject\ModelQualityStatsMap;
use Psr\Clock\ClockInterface;

final class ModelQualityTracker implements ModelQualityTrackerInterface
{
    public function __construct(
        private readonly ModelQualityStatRepositoryInterface $repository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function recordAcceptance(string $modelId, ModelQualityCategory $category = ModelQualityCategory::Enrichment): void
    {
        $stat = $this->findOrCreate($modelId, $category);
        $stat->incrementAccepted($this->clock->now());
        $this->repository->save($stat, true);
    }

    public function recordRejection(string $modelId, ModelQualityCategory $category = ModelQualityCategory::Enrichment): void
    {
        $stat = $this->findOrCreate($modelId, $category);
        $stat->incrementRejected($this->clock->now());
        $this->repository->save($stat, true);
    }

    public function getStats(string $modelId, ModelQualityCategory $category = ModelQualityCategory::Enrichment): ModelQualityStats
    {
        $stat = $this->repository->findByModelIdAndCategory($modelId, $category->value);

        if (! $stat instanceof ModelQualityStat) {
            return new ModelQualityStats(accepted: 0, rejected: 0, acceptanceRate: 0.0);
        }

        return $this->toValueObject($stat);
    }

    public function getAllStats(): ModelQualityStatsMap
    {
        $stats = [];

        foreach ($this->repository->findAll() as $stat) {
            $key = $stat->getCategory() . ':' . $stat->getModelId();
            $stats[$key] = $this->toValueObject($stat);
        }

        return new ModelQualityStatsMap($stats);
    }

    public function getStatsByCategory(ModelQualityCategory $category): ModelQualityStatsMap
    {
        $stats = [];

        foreach ($this->repository->findByCategory($category->value) as $stat) {
            $stats[$stat->getModelId()] = $this->toValueObject($stat);
        }

        return new ModelQualityStatsMap($stats);
    }

    private function findOrCreate(string $modelId, ModelQualityCategory $category): ModelQualityStat
    {
        return $this->repository->findByModelIdAndCategory($modelId, $category->value)
            ?? new ModelQualityStat($modelId, $this->clock->now(), $category->value);
    }

    private function toValueObject(ModelQualityStat $stat): ModelQualityStats
    {
        $total = $stat->getAccepted() + $stat->getRejected();
        $rate = $total > 0 ? $stat->getAccepted() / $total : 0.0;

        return new ModelQualityStats(
            accepted: $stat->getAccepted(),
            rejected: $stat->getRejected(),
            acceptanceRate: round($rate, 4),
        );
    }
}
