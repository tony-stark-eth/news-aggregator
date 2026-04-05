<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class RuleBasedTranslationService implements TranslationServiceInterface
{
    /**
     * Rule-based fallback: returns the original text unchanged.
     * Translation requires AI — no rule-based approach is viable.
     */
    public function translate(string $text, string $fromLanguage, string $toLanguage): string
    {
        return $text;
    }
}
