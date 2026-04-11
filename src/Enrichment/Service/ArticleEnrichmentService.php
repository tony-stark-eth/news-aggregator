<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;

final readonly class ArticleEnrichmentService implements ArticleEnrichmentServiceInterface
{
    public function __construct(
        private CombinedEnrichmentServiceInterface $combinedEnrichment,
        private ArticleTranslationServiceInterface $articleTranslation,
        private ScoringServiceInterface $scoring,
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function enrich(Article $article, FeedItem $item, Source $source): void
    {
        $result = $this->combinedEnrichment->enrich($item->title, $item->contentText);

        $this->applyCategory($article, $result, $source);
        $this->applySummaryAndMethod($article, $result);
        $this->applyKeywords($article, $result);
        $this->applySentiment($article, $result);

        $this->articleTranslation->applyTranslations($article, $source);
        $article->setScore($this->scoring->score($article));
    }

    private function applyCategory(Article $article, CombinedEnrichmentResult $result, Source $source): void
    {
        if ($result->categorySlug !== null) {
            $category = $this->categoryRepository->findBySlug($result->categorySlug);
            if ($category instanceof Category) {
                $article->setCategory($category);
            }
        }

        if (! $article->getCategory() instanceof Category) {
            $article->setCategory($source->getCategory());
        }
    }

    private function applySummaryAndMethod(Article $article, CombinedEnrichmentResult $result): void
    {
        if ($result->summary !== null) {
            $article->setSummary($result->summary);
        }

        $article->setEnrichmentMethod($result->method);
        $article->setAiModelUsed($result->modelUsed);
    }

    private function applyKeywords(Article $article, CombinedEnrichmentResult $result): void
    {
        if ($result->keywords !== []) {
            $article->setKeywords($result->keywords);
        }
    }

    private function applySentiment(Article $article, CombinedEnrichmentResult $result): void
    {
        if ($result->sentimentScore !== null) {
            $article->setSentimentScore($result->sentimentScore);
        }
    }
}
