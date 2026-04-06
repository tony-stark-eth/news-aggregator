<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;

final readonly class AiQualityGateService implements AiQualityGateServiceInterface
{
    private const array REASONING_PREFIXES = [
        'based on ',
        'according to ',
        'here is ',
        'here\'s ',
        'i will ',
        'i\'ll ',
        'let me ',
        'sure,',
        'sure!',
        'certainly,',
        'certainly!',
        'of course,',
        'of course!',
        'the article ',
        'this article ',
        'the provided ',
        'in summary,',
        'in this article,',
        'to summarize,',
    ];

    private const array REASONING_FRAGMENTS = [
        'the key facts are',
        'the key points are',
        'the main points are',
        'as an ai',
        'as a language model',
        'i cannot access',
        'i don\'t have access',
        'provided information',
        'provided text',
        'provided content',
    ];

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

        if ($this->containsReasoningArtifacts($summary)) {
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

    private function containsReasoningArtifacts(string $summary): bool
    {
        $lower = mb_strtolower($summary);

        foreach (self::REASONING_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }
        return array_any(self::REASONING_FRAGMENTS, fn (string $fragment): bool => str_contains($lower, $fragment));
    }
}
