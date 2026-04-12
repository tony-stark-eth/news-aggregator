<?php

declare(strict_types=1);

namespace App\Article\Repository;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use App\User\Entity\UserArticleRead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
    public function findForDigest(?\DateTimeImmutable $since, array $categorySlugs, int $limit, ?int $sentimentSlider = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.source', 's')
            ->leftJoin('a.category', 'c')
            ->setMaxResults($limit);

        if ($sentimentSlider !== null && $sentimentSlider !== 0) {
            $sliderFactor = $sentimentSlider / 10.0;
            $qb->addSelect('(COALESCE(a.sentimentScore, 0) * :sliderFactor) AS HIDDEN sentimentBoost')
                ->setParameter('sliderFactor', $sliderFactor)
                ->orderBy('sentimentBoost', 'DESC')
                ->addOrderBy('a.score', 'DESC');
        } else {
            $qb->orderBy('a.score', 'DESC');
        }

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
    public function findPaginated(?string $categorySlug, ?User $unreadForUser, int $page, int $limit, ?int $sourceId = null, ?User $bookmarkedForUser = null, ?int $sentimentSlider = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.source', 's')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $this->applySentimentOrdering($qb, $sentimentSlider);

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

    public function getPipelineStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{pending_fulltext: int|string, pending_enrichment: int|string, failed_fulltext: int|string}|false $result */
        $result = $conn->executeQuery("
            SELECT
                COUNT(*) FILTER (WHERE full_text_status = 'pending') AS pending_fulltext,
                COUNT(*) FILTER (WHERE enrichment_status = 'pending') AS pending_enrichment,
                COUNT(*) FILTER (WHERE full_text_status = 'failed') AS failed_fulltext
            FROM article
        ")->fetchAssociative();

        if ($result === false) {
            return [
                'pending_fulltext' => 0,
                'pending_enrichment' => 0,
                'failed_fulltext' => 0,
            ];
        }

        return [
            'pending_fulltext' => (int) $result['pending_fulltext'],
            'pending_enrichment' => (int) $result['pending_enrichment'],
            'failed_fulltext' => (int) $result['failed_fulltext'],
        ];
    }

    public function isConnectionHealthy(): bool
    {
        return $this->getEntityManager()->isOpen();
    }

    public function clear(): void
    {
        $this->getEntityManager()->clear();
    }

    public function getFullTextStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{total: int|string, fetched: int|string, failed: int|string, pending: int|string, skipped: int|string, no_status: int|string}|false $result */
        $result = $conn->executeQuery("
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE full_text_status = 'fetched') AS fetched,
                COUNT(*) FILTER (WHERE full_text_status = 'failed') AS failed,
                COUNT(*) FILTER (WHERE full_text_status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE full_text_status = 'skipped') AS skipped,
                COUNT(*) FILTER (WHERE full_text_status IS NULL) AS no_status
            FROM article
        ")->fetchAssociative();

        if ($result === false) {
            return [
                'total' => 0,
                'fetched' => 0,
                'failed' => 0,
                'pending' => 0,
                'skipped' => 0,
                'no_status' => 0,
            ];
        }

        return [
            'total' => (int) $result['total'],
            'fetched' => (int) $result['fetched'],
            'failed' => (int) $result['failed'],
            'pending' => (int) $result['pending'],
            'skipped' => (int) $result['skipped'],
            'no_status' => (int) $result['no_status'],
        ];
    }

    public function getEnrichmentStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{total: int|string, ai: int|string, rule_based: int|string, pending: int|string, complete: int|string, no_method: int|string}|false $result */
        $result = $conn->executeQuery("
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE enrichment_method = 'ai') AS ai,
                COUNT(*) FILTER (WHERE enrichment_method = 'rule_based') AS rule_based,
                COUNT(*) FILTER (WHERE enrichment_status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE enrichment_status = 'complete') AS complete,
                COUNT(*) FILTER (WHERE enrichment_method IS NULL) AS no_method
            FROM article
        ")->fetchAssociative();

        if ($result === false) {
            return [
                'total' => 0,
                'ai' => 0,
                'rule_based' => 0,
                'pending' => 0,
                'complete' => 0,
                'no_method' => 0,
            ];
        }

        return [
            'total' => (int) $result['total'],
            'ai' => (int) $result['ai'],
            'rule_based' => (int) $result['rule_based'],
            'pending' => (int) $result['pending'],
            'complete' => (int) $result['complete'],
            'no_method' => (int) $result['no_method'],
        ];
    }

    public function findIdsWithoutEmbeddings(int $limit): array
    {
        /** @var list<int> */
        return $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.embedding IS NULL')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findWithoutSentiment(int $limit): array
    {
        /** @var list<Article> */
        return $this->createQueryBuilder('a')
            ->where('a.sentimentScore IS NULL')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findIdsWithoutSentiment(int $limit): array
    {
        /** @var list<int> */
        return $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.sentimentScore IS NULL')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getEmbeddingStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{total: int|string, with_embedding: int|string, without_embedding: int|string}|false $result */
        $result = $conn->executeQuery('
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE embedding IS NOT NULL) AS with_embedding,
                COUNT(*) FILTER (WHERE embedding IS NULL) AS without_embedding
            FROM article
        ')->fetchAssociative();

        if ($result === false) {
            return [
                'total' => 0,
                'with_embedding' => 0,
                'without_embedding' => 0,
            ];
        }

        return [
            'total' => (int) $result['total'],
            'with_embedding' => (int) $result['with_embedding'],
            'without_embedding' => (int) $result['without_embedding'],
        ];
    }

    public function getSentimentDistribution(\DateTimeImmutable $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{average: string|null, positive: int|string, neutral: int|string, negative: int|string}|false $result */
        $result = $conn->executeQuery('
            SELECT
                AVG(sentiment_score) AS average,
                COUNT(*) FILTER (WHERE sentiment_score > 0.2) AS positive,
                COUNT(*) FILTER (WHERE sentiment_score BETWEEN -0.2 AND 0.2) AS neutral,
                COUNT(*) FILTER (WHERE sentiment_score < -0.2) AS negative
            FROM article
            WHERE sentiment_score IS NOT NULL AND fetched_at >= :since
        ', [
            'since' => $since->format('Y-m-d H:i:s'),
        ])->fetchAssociative();

        if ($result === false) {
            return [
                'average' => 0.0,
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ];
        }

        return [
            'average' => round((float) ($result['average'] ?? 0.0), 3),
            'positive' => (int) $result['positive'],
            'neutral' => (int) $result['neutral'],
            'negative' => (int) $result['negative'],
        ];
    }

    public function getSentimentStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{total: int|string, scored: int|string, unscored: int|string}|false $result */
        $result = $conn->executeQuery('
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE sentiment_score IS NOT NULL) AS scored,
                COUNT(*) FILTER (WHERE sentiment_score IS NULL) AS unscored
            FROM article
        ')->fetchAssociative();

        if ($result === false) {
            return [
                'total' => 0,
                'scored' => 0,
                'unscored' => 0,
            ];
        }

        return [
            'total' => (int) $result['total'],
            'scored' => (int) $result['scored'],
            'unscored' => (int) $result['unscored'],
        ];
    }

    private function applySentimentOrdering(QueryBuilder $qb, ?int $sentimentSlider): void
    {
        if ($sentimentSlider === null || $sentimentSlider === 0) {
            $qb->orderBy('CASE WHEN a.publishedAt IS NOT NULL THEN a.publishedAt ELSE a.fetchedAt END', 'DESC')
                ->addOrderBy('a.score', 'DESC');

            return;
        }

        $sliderFactor = $sentimentSlider / 10.0;

        // At ±6-10: filter out opposite sentiment articles (null sentiment always included)
        if (abs($sentimentSlider) >= 6) {
            if ($sentimentSlider > 0) {
                $qb->andWhere('a.sentimentScore IS NULL OR a.sentimentScore >= :sentimentThreshold')
                    ->setParameter('sentimentThreshold', -0.3);
            } else {
                $qb->andWhere('a.sentimentScore IS NULL OR a.sentimentScore <= :sentimentThreshold')
                    ->setParameter('sentimentThreshold', 0.3);
            }
        }

        // Boost matching sentiment via ORDER BY: sentiment_boost = COALESCE(sentiment_score, 0) * sliderFactor
        $qb->addSelect('(COALESCE(a.sentimentScore, 0) * :sliderFactor) AS HIDDEN sentimentBoost')
            ->setParameter('sliderFactor', $sliderFactor)
            ->orderBy('sentimentBoost', 'DESC')
            ->addOrderBy('CASE WHEN a.publishedAt IS NOT NULL THEN a.publishedAt ELSE a.fetchedAt END', 'DESC')
            ->addOrderBy('a.score', 'DESC');
    }
}
