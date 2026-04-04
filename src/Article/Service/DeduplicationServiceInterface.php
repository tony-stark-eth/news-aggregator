<?php

declare(strict_types=1);

namespace App\Article\Service;

interface DeduplicationServiceInterface
{
    /**
     * Check if an article is a duplicate based on URL, title similarity, or content fingerprint.
     * Returns true if a duplicate exists.
     */
    public function isDuplicate(string $url, string $title, ?string $fingerprint): bool;
}
