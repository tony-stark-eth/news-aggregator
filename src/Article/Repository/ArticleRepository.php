<?php

declare(strict_types=1);

namespace App\Article\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use App\User\Entity\UserArticleRead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
final class ArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findById(int $id): ?Article
    {
        return $this->find($id);
    }

    public function findByUrl(string $url): ?Article
    {
        return $this->findOneBy([
            'url' => $url,
        ]);
    }

    public function findByFingerprint(string $fingerprint): ?Article
    {
        return $this->findOneBy([
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * @return list<array{title: string}>
     */
    public function findRecentTitles(int $limit): array
    {
        /** @var list<array{title: string}> */
        return $this->createQueryBuilder('a')
            ->select('a.title')
            ->orderBy('a.fetchedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @param list<string> $categorySlugs
     *
     * @return list<Article>
     */
    public function findForDigest(?\DateTimeImmutable $since, array $categorySlugs, int $limit): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.source', 's')
            ->leftJoin('a.category', 'c')
            ->orderBy('a.score', 'DESC')
            ->setMaxResults($limit);

        if ($since instanceof \DateTimeImmutable) {
            $qb->andWhere('a.fetchedAt > :since')
                ->setParameter('since', $since);
        }

        if ($categorySlugs !== []) {
            $qb->andWhere('c.slug IN (:cats)')
                ->setParameter('cats', $categorySlugs);
        }

        /** @var list<Article> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Article>
     */
    public function findBatched(int $limit, int $offset): array
    {
        /** @var list<Article> */
        return $this->createQueryBuilder('a')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Article>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<Article> */
        return $this->createQueryBuilder('a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('a.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Article>
     */
    public function findPaginated(?string $categorySlug, ?User $unreadForUser, int $page, int $limit, ?int $sourceId = null, ?User $bookmarkedForUser = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.source', 's')
            ->orderBy('CASE WHEN a.publishedAt IS NOT NULL THEN a.publishedAt ELSE a.fetchedAt END', 'DESC')
            ->addOrderBy('a.score', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($categorySlug !== null && $categorySlug !== '') {
            $qb->andWhere('c.slug = :cat')->setParameter('cat', $categorySlug);
        }

        if ($sourceId !== null) {
            $qb->andWhere('s.id = :sourceId')->setParameter('sourceId', $sourceId);
        }

        if ($unreadForUser instanceof User) {
            $sub = $this->getEntityManager()
                ->getRepository(UserArticleRead::class)
                ->createQueryBuilder('r2')
                ->select('1')
                ->where('r2.article = a')
                ->andWhere('r2.user = :currentUser')
                ->getDQL();

            $qb->andWhere($qb->expr()->not($qb->expr()->exists($sub)))
                ->setParameter('currentUser', $unreadForUser);
        }

        if ($bookmarkedForUser instanceof User) {
            $bookmarkSub = $this->getEntityManager()
                ->getRepository(UserArticleBookmark::class)
                ->createQueryBuilder('b2')
                ->select('1')
                ->where('b2.article = a')
                ->andWhere('b2.user = :bookmarkUser')
                ->getDQL();

            $qb->andWhere($qb->expr()->exists($bookmarkSub))
                ->setParameter('bookmarkUser', $bookmarkedForUser);
        }

        /** @var list<Article> */
        return $qb->getQuery()->getResult();
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.fetchedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Article>
     */
    public function findUnreadForUser(User $user): array
    {
        $subDql = $this->getEntityManager()
            ->getRepository(UserArticleRead::class)
            ->createQueryBuilder('r')
            ->select('IDENTITY(r.article)')
            ->where('r.user = :user')
            ->getDQL();

        /** @var list<Article> */
        return $this->createQueryBuilder('a')
            ->where("a.id NOT IN ({$subDql})")
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findWithoutTranslations(int $limit): array
    {
        /** @var list<Article> */
        return $this->createQueryBuilder('a')
            ->where('a.translations IS NULL')
            ->andWhere('a.enrichmentMethod = :method')
            ->setParameter('method', 'ai')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Article $article, bool $flush = false): void
    {
        $this->getEntityManager()->persist($article);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function isConnectionHealthy(): bool
    {
        return $this->getEntityManager()->isOpen();
    }

    public function clear(): void
    {
        $this->getEntityManager()->clear();
    }
}
