<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

final readonly class ArticleFingerprint
{
    public string $value;

    public function __construct(string $normalizedText)
    {
        $this->value = hash('xxh128', $normalizedText);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromContent(string $rawText): self
    {
        $normalized = mb_strtolower(trim($rawText));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
