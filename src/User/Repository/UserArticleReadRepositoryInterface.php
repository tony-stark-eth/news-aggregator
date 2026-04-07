<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;

interface UserArticleReadRepositoryInterface
{
    public function findByUserAndArticle(User $user, Article $article): ?UserArticleRead;

    /**
     * @param list<int> $articleIds
     *
     * @return array<int, true>
     */
    public function findReadArticleIdsForUser(User $user, array $articleIds): array;

    public function save(UserArticleRead $read, bool $flush = false): void;

    /**
     * Count unread articles per category for a user.
     *
     * @return array{total: int, categories: array<string, int>}
     */
    public function countUnreadByCategory(User $user): array;

    public function flush(): void;
}
