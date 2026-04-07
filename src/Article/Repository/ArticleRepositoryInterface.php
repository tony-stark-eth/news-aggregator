<?php

declare(strict_types=1);

namespace App\Article\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    public function findByUrl(string $url): ?Article;

    public function findByFingerprint(string $fingerprint): ?Article;

    /**
     * @return list<array{title: string}>
     */
    public function findRecentTitles(int $limit): array;

    /**
     * @param list<string> $categorySlugs
     *
     * @return list<Article>
     */
    public function findForDigest(?\DateTimeImmutable $since, array $categorySlugs, int $limit): array;

    /**
     * @return list<Article>
     */
    public function findBatched(int $limit, int $offset): array;

    /**
     * @param list<int> $ids
     *
     * @return list<Article>
     */
    public function findByIds(array $ids): array;

    /**
     * @return list<Article>
     */
    public function findPaginated(?string $categorySlug, ?User $unreadForUser, int $page, int $limit, ?int $sourceId = null, ?User $bookmarkedForUser = null): array;

    public function countSince(\DateTimeImmutable $since): int;

    /**
     * @return list<Article>
     */
    public function findUnreadForUser(User $user): array;

    /**
     * @return list<Article>
     */
    public function findWithoutTranslations(int $limit): array;

    public function save(Article $article, bool $flush = false): void;

    public function flush(): void;

    public function clear(): void;
}
