<?php

declare(strict_types=1);

namespace App\Source\Event;

use App\Source\ValueObject\SourceHealth;

final readonly class SourceHealthChanged
{
    public function __construct(
        public int $sourceId,
        public string $sourceName,
        public SourceHealth $previousHealth,
        public SourceHealth $newHealth,
    ) {
    }
}
