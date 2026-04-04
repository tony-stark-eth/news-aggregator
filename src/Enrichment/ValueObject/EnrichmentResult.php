<?php

declare(strict_types=1);

namespace App\Enrichment\ValueObject;

use App\Shared\ValueObject\EnrichmentMethod;

final readonly class EnrichmentResult
{
    public function __construct(
        public ?string $value,
        public EnrichmentMethod $method,
        public ?string $modelUsed = null,
    ) {
    }
}
