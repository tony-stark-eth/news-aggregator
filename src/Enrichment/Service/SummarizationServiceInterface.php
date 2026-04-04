<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\EnrichmentResult;

interface SummarizationServiceInterface
{
    /**
     * Generate a summary for article content.
     * Returns EnrichmentResult with summary value, method used, and optional model name.
     */
    public function summarize(string $contentText): EnrichmentResult;
}
