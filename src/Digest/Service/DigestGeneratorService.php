<?php

declare(strict_types=1);

namespace App\Digest\Service;

use App\Article\Entity\Article;
use App\Article\ValueObject\ArticleCollection;
use App\Digest\Entity\DigestConfig;
use App\Digest\ValueObject\GroupedArticles;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DigestGeneratorService implements DigestGeneratorServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function collectArticles(DigestConfig $config): GroupedArticles
    {
        $qb = $this->entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->join('a.source', 's')
            ->leftJoin('a.category', 'c')
            ->orderBy('a.score', 'DESC')
            ->setMaxResults($config->getArticleLimit());

        if ($config->getLastRunAt() instanceof \DateTimeImmutable) {
            $qb->andWhere('a.fetchedAt > :since')
                ->setParameter('since', $config->getLastRunAt());
        }

        $categories = $config->getCategories();
        if ($categories !== []) {
            $qb->andWhere('c.slug IN (:cats)')
                ->setParameter('cats', $categories);
        }

        /** @var list<Article> $articles */
        $articles = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($articles as $article) {
            $slug = $article->getCategory()?->getSlug() ?? 'uncategorized';
            $grouped[$slug][] = $article;
        }

        $groupedCollections = [];
        foreach ($grouped as $slug => $groupArticles) {
            $groupedCollections[$slug] = new ArticleCollection($groupArticles);
        }

        return new GroupedArticles($groupedCollections);
    }
}
