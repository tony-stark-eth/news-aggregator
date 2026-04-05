<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\ValueObject\ModelQualityStats;
use App\Shared\AI\ValueObject\ModelQualityStatsMap;

interface ModelQualityTrackerInterface
{
    public function recordAcceptance(string $modelId): void;

    public function recordRejection(string $modelId): void;

    public function getStats(string $modelId): ModelQualityStats;

    public function getAllStats(): ModelQualityStatsMap;
}
