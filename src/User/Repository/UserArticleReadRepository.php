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

    /**
     * @param list<int> $articleIds
     *
     * @return array<int, true>
     */
    public function findReadArticleIdsForUser(User $user, array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        /** @var list<UserArticleRead> $readRecords */
        $readRecords = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.article IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $articleIds)
            ->getQuery()
            ->getResult();

        $readIds = [];
        foreach ($readRecords as $record) {
            $readIds[(int) $record->getArticle()->getId()] = true;
        }

        return $readIds;
    }

    /**
     * @return array{total: int, categories: array<string, int>}
     */
    public function countUnreadByCategory(User $user): array
    {
        $em = $this->getEntityManager();

        // Total unread
        $totalDql = $em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Article::class, 'a')
            ->where('a.id NOT IN (SELECT IDENTITY(r.article) FROM ' . UserArticleRead::class . ' r WHERE r.user = :user)')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Unread per category
        /** @var list<array{slug: string, cnt: string}> $rows */
        $rows = $em->createQueryBuilder()
            ->select('c.slug, COUNT(a.id) AS cnt')
            ->from(Article::class, 'a')
            ->join('a.category', 'c')
            ->where('a.id NOT IN (SELECT IDENTITY(r2.article) FROM ' . UserArticleRead::class . ' r2 WHERE r2.user = :user)')
            ->setParameter('user', $user)
            ->groupBy('c.slug')
            ->getQuery()
            ->getArrayResult();

        $categories = [];
        foreach ($rows as $row) {
            $categories[$row['slug']] = (int) $row['cnt'];
        }

        return [
            'total' => (int) $totalDql,
            'categories' => $categories,
        ];
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
