<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;

interface UserArticleBookmarkRepositoryInterface
{
    /**
     * @return list<UserArticleBookmark>
     */
    public function findByUser(User $user): array;

    public function findByUserAndArticle(User $user, Article $article): ?UserArticleBookmark;

    public function save(UserArticleBookmark $bookmark, bool $flush = false): void;

    public function remove(UserArticleBookmark $bookmark, bool $flush = false): void;

    /**
     * @param list<int> $articleIds
     *
     * @return array<int, true>
     */
    public function getBookmarkedArticleIds(User $user, array $articleIds): array;
}
