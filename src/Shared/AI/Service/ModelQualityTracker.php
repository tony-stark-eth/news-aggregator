<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\Entity\ModelQualityStat;
use App\Shared\AI\Repository\ModelQualityStatRepositoryInterface;
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

    public function recordAcceptance(string $modelId): void
    {
        $stat = $this->findOrCreate($modelId);
        $stat->incrementAccepted($this->clock->now());
        $this->repository->save($stat, true);
    }

    public function recordRejection(string $modelId): void
    {
        $stat = $this->findOrCreate($modelId);
        $stat->incrementRejected($this->clock->now());
        $this->repository->save($stat, true);
    }

    public function getStats(string $modelId): ModelQualityStats
    {
        $stat = $this->repository->findByModelId($modelId);

        if (! $stat instanceof ModelQualityStat) {
            return new ModelQualityStats(
                accepted: 0,
                rejected: 0,
                acceptanceRate: 0.0,
            );
        }

        return $this->toValueObject($stat);
    }

    public function getAllStats(): ModelQualityStatsMap
    {
        $stats = [];

        foreach ($this->repository->findAll() as $stat) {
            $stats[$stat->getModelId()] = $this->toValueObject($stat);
        }

        return new ModelQualityStatsMap($stats);
    }

    private function findOrCreate(string $modelId): ModelQualityStat
    {
        return $this->repository->findByModelId($modelId)
            ?? new ModelQualityStat($modelId, $this->clock->now());
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
