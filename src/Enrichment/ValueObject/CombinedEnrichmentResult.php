<?php

declare(strict_types=1);

namespace App\Enrichment\ValueObject;

use App\Shared\ValueObject\EnrichmentMethod;

final readonly class CombinedEnrichmentResult
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public ?string $categorySlug,
        public ?string $summary,
        public array $keywords,
        public EnrichmentMethod $method,
        public ?string $modelUsed = null,
    ) {
    }
}
