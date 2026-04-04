<?php

declare(strict_types=1);

namespace App\Article\Service;

use App\Article\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeduplicationService implements DeduplicationServiceInterface
{
    private const float TITLE_SIMILARITY_THRESHOLD = 0.85;

    public function __construct(
        private EntityManagerInterface $entityManager,
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
        return $this->entityManager
            ->getRepository(Article::class)
            ->findOneBy([
                'url' => $url,
            ]) !== null;
    }

    private function existsByFingerprint(string $fingerprint): bool
    {
        return $this->entityManager
            ->getRepository(Article::class)
            ->findOneBy([
                'fingerprint' => $fingerprint,
            ]) !== null;
    }

    private function existsBySimilarTitle(string $title): bool
    {
        $normalized = mb_strtolower(trim($title));
        if ($normalized === '') {
            return false;
        }

        // Check recent articles (last 1000) for title similarity
        $recentArticles = $this->entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->select('a.title')
            ->orderBy('a.fetchedAt', 'DESC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getArrayResult();

        /** @var list<array{title: string}> $recentArticles */
        foreach ($recentArticles as $row) {
            $existingNormalized = mb_strtolower(trim($row['title']));

            similar_text($normalized, $existingNormalized, $percent);
            if ($percent / 100 >= self::TITLE_SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
