<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;

interface UserArticleReadRepositoryInterface
{
    public function findByUserAndArticle(User $user, Article $article): ?UserArticleRead;

    public function save(UserArticleRead $read, bool $flush = false): void;

    public function flush(): void;
}
