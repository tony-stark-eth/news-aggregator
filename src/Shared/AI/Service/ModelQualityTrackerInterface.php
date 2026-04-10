<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\ValueObject\ModelQualityCategory;
use App\Shared\AI\ValueObject\ModelQualityStats;
use App\Shared\AI\ValueObject\ModelQualityStatsMap;

interface ModelQualityTrackerInterface
{
    public function recordAcceptance(string $modelId, ModelQualityCategory $category = ModelQualityCategory::Enrichment): void;

    public function recordRejection(string $modelId, ModelQualityCategory $category = ModelQualityCategory::Enrichment): void;

    public function getStats(string $modelId, ModelQualityCategory $category = ModelQualityCategory::Enrichment): ModelQualityStats;

    public function getAllStats(): ModelQualityStatsMap;

    public function getStatsByCategory(ModelQualityCategory $category): ModelQualityStatsMap;
}
