<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface TranslationServiceInterface
{
    /**
     * Translate text from one language to another.
     * Returns the translated text, or the original on failure.
     */
    public function translate(string $text, string $fromLanguage, string $toLanguage): string;
}
