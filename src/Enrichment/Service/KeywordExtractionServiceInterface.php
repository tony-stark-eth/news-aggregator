<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface KeywordExtractionServiceInterface
{
    /**
     * Extract keywords/entities from article title and content.
     *
     * @return list<string>
     */
    public function extract(string $title, ?string $contentText): array;
}
