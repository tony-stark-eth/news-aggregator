<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\EnrichmentResult;

interface CategorizationServiceInterface
{
    /**
     * Categorize an article based on its title and content.
     * Returns EnrichmentResult with category slug value, method used, and optional model name.
     */
    public function categorize(string $title, ?string $contentText): EnrichmentResult;
}
