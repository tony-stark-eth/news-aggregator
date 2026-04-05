<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface AiQualityGateServiceInterface
{
    public function validateSummary(string $summary, string $title): bool;

    public function validateCategorization(string $categorySlug): bool;
}
