<?php

declare(strict_types=1);

namespace App\Digest\Service;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\ValueObject\ArticleCollection;
use App\Digest\Entity\DigestConfig;
use App\Digest\ValueObject\GroupedArticles;

final readonly class DigestGeneratorService implements DigestGeneratorServiceInterface
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
    ) {
    }

    public function collectArticles(DigestConfig $config): GroupedArticles
    {
        /** @var list<Article> $articles */
        $articles = $this->articleRepository->findForDigest($config->getLastRunAt(), $config->getCategories(), $config->getArticleLimit());

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
