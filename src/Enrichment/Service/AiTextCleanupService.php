<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

/**
 * Post-processes AI-generated text to fix common artifacts from free-tier models.
 *
 * Primary fix: camelCase word-joining ("InformationSecurity" -> "Information Security").
 * Regex requires 2+ lowercase chars before the uppercase boundary to preserve
 * brand names like iPhone, eBay, and abbreviations like pH or HTTPServer.
 */
final readonly class AiTextCleanupService
{
    private const string CAMEL_CASE_JOIN_PATTERN = '/(?<=[a-z]{2})(?=[A-Z][a-z])/';

    public function clean(string $text): string
    {
        return (string) preg_replace(self::CAMEL_CASE_JOIN_PATTERN, ' ', $text);
    }
}
