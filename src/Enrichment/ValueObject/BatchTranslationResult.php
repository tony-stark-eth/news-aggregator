<?php

declare(strict_types=1);

namespace App\Enrichment\ValueObject;

final readonly class BatchTranslationResult
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public string $title,
        public ?string $summary,
        public array $keywords,
        public bool $fromAi,
    ) {
    }
}
