<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

final readonly class ReadabilityResult
{
    public function __construct(
        public ?string $textContent,
        public ?string $htmlContent,
        public bool $success,
    ) {
    }
}
