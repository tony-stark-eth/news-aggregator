<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\BatchTranslationResult;

interface BatchTranslationServiceInterface
{
    /**
     * @param list<string> $keywords
     */
    public function translateBatch(
        string $title,
        ?string $summary,
        array $keywords,
        string $fromLanguage,
        string $toLanguage,
    ): BatchTranslationResult;
}
