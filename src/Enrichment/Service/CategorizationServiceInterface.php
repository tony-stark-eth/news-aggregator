<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface CategorizationServiceInterface
{
    /**
     * Categorize an article based on its title and content.
     * Returns the category slug or null if no match.
     */
    public function categorize(string $title, ?string $contentText): ?string;
}
