<?php

declare(strict_types=1);

namespace App\Source\ValueObject;

final readonly class FeedPreview
{
    public function __construct(
        public string $title,
        public int $itemCount,
        public ?string $detectedLanguage,
        public FeedUrl $feedUrl,
        public bool $hasFullContent = false,
    ) {
    }
}
