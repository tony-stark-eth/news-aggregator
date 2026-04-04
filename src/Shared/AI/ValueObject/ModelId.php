<?php

declare(strict_types=1);

namespace App\Shared\AI\ValueObject;

final readonly class ModelId
{
    public function __construct(
        public string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
