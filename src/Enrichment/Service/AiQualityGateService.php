<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;

final readonly class AiQualityGateService implements AiQualityGateServiceInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

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
        return $this->categoryRepository->findBySlug($categorySlug) instanceof Category;
    }
}
