<?php

declare(strict_types=1);

namespace App\Digest\Service;

use App\Article\Entity\Article;
use App\Digest\Entity\DigestConfig;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DigestGeneratorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, list<Article>> Articles grouped by category slug
     */
    public function collectArticles(DigestConfig $config): array
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

        return $grouped;
    }
}
