<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\CombinedEnrichmentResult;

interface CombinedEnrichmentServiceInterface
{
    public function enrich(string $title, ?string $contentText): CombinedEnrichmentResult;
}
