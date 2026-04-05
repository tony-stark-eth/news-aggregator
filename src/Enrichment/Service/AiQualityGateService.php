<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class AiQualityGateService implements AiQualityGateServiceInterface
{
    public function validateSummary(string $summary, string $title): bool
    {
        $length = mb_strlen($summary);

        if ($length < 20 || $length > 500) {
            return false;
        }

        // Reject if summary is just the title repeated
        similar_text(mb_strtolower($summary), mb_strtolower($title), $percent);

        return $percent < 90.0;
    }

    public function validateCategorization(string $categorySlug): bool
    {
        $validSlugs = ['politics', 'business', 'tech', 'science', 'sports'];

        return in_array($categorySlug, $validSlugs, true);
    }
}
