<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserArticleBookmark>
 */
final class UserArticleBookmarkRepository extends ServiceEntityRepository implements UserArticleBookmarkRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserArticleBookmark::class);
    }

    /**
     * @return list<UserArticleBookmark>
     */
    public function findByUser(User $user): array
    {
        /** @var list<UserArticleBookmark> */
        return $this->findBy([
            'user' => $user,
        ], [
            'createdAt' => 'DESC',
        ]);
    }

    public function findByUserAndArticle(User $user, Article $article): ?UserArticleBookmark
    {
        return $this->findOneBy([
            'user' => $user,
            'article' => $article,
        ]);
    }

    public function save(UserArticleBookmark $bookmark, bool $flush = false): void
    {
        $this->getEntityManager()->persist($bookmark);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserArticleBookmark $bookmark, bool $flush = false): void
    {
        $this->getEntityManager()->remove($bookmark);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param list<int> $articleIds
     *
     * @return array<int, true>
     */
    public function getBookmarkedArticleIds(User $user, array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        /** @var list<UserArticleBookmark> $bookmarks */
        $bookmarks = $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->andWhere('b.article IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $articleIds)
            ->getQuery()
            ->getResult();

        $bookmarkedIds = [];
        foreach ($bookmarks as $bookmark) {
            $bookmarkedIds[(int) $bookmark->getArticle()->getId()] = true;
        }

        return $bookmarkedIds;
    }
}
