<?php

declare(strict_types=1);

namespace App\Article\Service;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;

final readonly class DeduplicationService implements DeduplicationServiceInterface
{
    private const float TITLE_SIMILARITY_THRESHOLD = 0.85;

    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
    ) {
    }

    public function isDuplicate(string $url, string $title, ?string $fingerprint): bool
    {
        if ($this->existsByUrl($url)) {
            return true;
        }

        if ($fingerprint !== null && $this->existsByFingerprint($fingerprint)) {
            return true;
        }

        return $this->existsBySimilarTitle($title);
    }

    private function existsByUrl(string $url): bool
    {
        return $this->articleRepository->findByUrl($url) instanceof Article;
    }

    private function existsByFingerprint(string $fingerprint): bool
    {
        return $this->articleRepository->findByFingerprint($fingerprint) instanceof Article;
    }

    private function existsBySimilarTitle(string $title): bool
    {
        $normalized = mb_strtolower(trim($title));
        if ($normalized === '') {
            return false;
        }

        // Check recent articles (last 1000) for title similarity
        $recentTitles = $this->articleRepository->findRecentTitles(1000);

        foreach ($recentTitles as $row) {
            $existingNormalized = mb_strtolower(trim($row['title']));

            similar_text($normalized, $existingNormalized, $percent);
            if ($percent / 100 >= self::TITLE_SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
