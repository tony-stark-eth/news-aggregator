<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserArticleRead>
 */
final class UserArticleReadRepository extends ServiceEntityRepository implements UserArticleReadRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserArticleRead::class);
    }

    public function findByUserAndArticle(User $user, Article $article): ?UserArticleRead
    {
        return $this->findOneBy([
            'user' => $user,
            'article' => $article,
        ]);
    }

    public function save(UserArticleRead $read, bool $flush = false): void
    {
        $this->getEntityManager()->persist($read);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
