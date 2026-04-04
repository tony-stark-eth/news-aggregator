<?php

declare(strict_types=1);

namespace App\Source\Service;

final readonly class FeedItem
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $contentRaw,
        public ?string $contentText,
        public ?\DateTimeImmutable $publishedAt,
    ) {
    }
}
