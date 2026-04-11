<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;

final readonly class RuleBasedEnrichmentService implements RuleBasedEnrichmentServiceInterface
{
    public function __construct(
        private CategorizationServiceInterface $categorization,
        private SummarizationServiceInterface $summarization,
        private KeywordExtractionServiceInterface $keywordExtraction,
        private SentimentScoringServiceInterface $sentimentScoring,
        private ScoringServiceInterface $scoring,
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function enrich(Article $article, FeedItem $item, Source $source): void
    {
        $this->applyCategory($article, $item, $source);
        $this->applySummary($article, $item);
        $this->applyKeywords($article, $item);
        $this->applySentiment($article, $item);

        $article->setEnrichmentMethod(EnrichmentMethod::RuleBased);
        $article->setScore($this->scoring->score($article));
    }

    private function applyCategory(Article $article, FeedItem $item, Source $source): void
    {
        $result = $this->categorization->categorize($item->title, $item->contentText);

        if ($result->value !== null) {
            $category = $this->categoryRepository->findBySlug($result->value);
            if ($category instanceof Category) {
                $article->setCategory($category);
            }
        }

        if (! $article->getCategory() instanceof Category) {
            $article->setCategory($source->getCategory());
        }
    }

    private function applySummary(Article $article, FeedItem $item): void
    {
        if ($item->contentText === null) {
            return;
        }

        $result = $this->summarization->summarize($item->contentText, $item->title);

        if ($result->value !== null) {
            $article->setSummary($result->value);
        }
    }

    private function applyKeywords(Article $article, FeedItem $item): void
    {
        $keywords = $this->keywordExtraction->extract($item->title, $item->contentText);

        if ($keywords !== []) {
            $article->setKeywords($keywords);
        }
    }

    private function applySentiment(Article $article, FeedItem $item): void
    {
        $score = $this->sentimentScoring->score($item->title, $item->contentText);

        if ($score !== null) {
            $article->setSentimentScore($score);
        }
    }
}
