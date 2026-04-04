<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface SummarizationServiceInterface
{
    /**
     * Generate a summary for article content.
     * Returns a 1-2 sentence summary or null if content is too short.
     */
    public function summarize(string $contentText): ?string;
}
