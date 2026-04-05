<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;

final readonly class ArticleEnrichmentService implements ArticleEnrichmentServiceInterface
{
    /**
     * @var list<string>
     */
    private array $parsedDisplayLanguages;

    public function __construct(
        private CategorizationServiceInterface $categorization,
        private SummarizationServiceInterface $summarization,
        private TranslationServiceInterface $translation,
        private KeywordExtractionServiceInterface $keywordExtraction,
        private ScoringServiceInterface $scoring,
        private CategoryRepositoryInterface $categoryRepository,
        private string $displayLanguages = 'en',
    ) {
        $this->parsedDisplayLanguages = array_values(
            array_filter(
                array_map(trim(...), explode(',', $this->displayLanguages)),
                static fn (string $lang): bool => $lang !== '',
            ),
        );
    }

    public function enrich(Article $article, FeedItem $item, Source $source): void
    {
        $catResult = $this->categorization->categorize($item->title, $item->contentText);
        $this->applyCategory($article, $catResult, $source);

        if ($item->contentText !== null) {
            $sumResult = $this->summarization->summarize($item->contentText);
            $this->applyEnrichment($article, $catResult, $sumResult);
        }

        $keywords = $this->keywordExtraction->extract($item->title, $item->contentText);
        if ($keywords !== []) {
            $article->setKeywords($keywords);
        }

        $this->applyTranslation($article, $source);
        $article->setScore($this->scoring->score($article));
    }

    private function applyCategory(Article $article, EnrichmentResult $catResult, Source $source): void
    {
        if ($catResult->value !== null) {
            $category = $this->categoryRepository->findBySlug($catResult->value);
            if ($category instanceof Category) {
                $article->setCategory($category);
            }
        }

        if (! $article->getCategory() instanceof Category) {
            $article->setCategory($source->getCategory());
        }
    }

    private function applyEnrichment(Article $article, EnrichmentResult $catResult, EnrichmentResult $sumResult): void
    {
        if ($sumResult->value === null) {
            return;
        }

        $article->setSummary($sumResult->value);

        $aiResult = $sumResult->method === EnrichmentMethod::Ai ? $sumResult : null;
        $aiResult ??= $catResult->method === EnrichmentMethod::Ai ? $catResult : null;

        $article->setEnrichmentMethod($aiResult instanceof EnrichmentResult ? EnrichmentMethod::Ai : EnrichmentMethod::RuleBased);
        $article->setAiModelUsed($aiResult?->modelUsed);
    }

    private function applyTranslation(Article $article, Source $source): void
    {
        $sourceLanguage = $source->getLanguage() ?? 'en';
        $originalTitle = $article->getTitle();
        $originalSummary = $article->getSummary();

        $article->setTitleOriginal($originalTitle);
        $article->setSummaryOriginal($originalSummary);

        $translations = [];
        $originalKeywords = $article->getKeywords() ?? [];

        $translations[$sourceLanguage] = [
            'title' => $originalTitle,
            'summary' => $originalSummary,
            'keywords' => $originalKeywords,
        ];

        foreach ($this->parsedDisplayLanguages as $targetLang) {
            if ($targetLang === $sourceLanguage) {
                continue;
            }

            $translations[$targetLang] = $this->translateToLanguage($originalTitle, $originalSummary, $originalKeywords, $sourceLanguage, $targetLang);
        }

        $article->setTranslations($translations);

        $primaryLang = $this->parsedDisplayLanguages[0] ?? 'en';
        if ($primaryLang !== $sourceLanguage && isset($translations[$primaryLang])) {
            $article->setTitle($translations[$primaryLang]['title']);
            if ($translations[$primaryLang]['summary'] !== null) {
                $article->setSummary($translations[$primaryLang]['summary']);
            }
        }
    }

    /**
     * @param list<string> $keywords
     *
     * @return array{title: string, summary: ?string, keywords: list<string>}
     */
    private function translateToLanguage(string $title, ?string $summary, array $keywords, string $from, string $to): array
    {
        $translatedTitle = $this->translation->translate($title, $from, $to);
        $translatedSummary = $summary !== null
            ? $this->translation->translate($summary, $from, $to)
            : null;

        $translatedKeywords = $keywords;
        if ($keywords !== []) {
            $keywordsText = implode(', ', $keywords);
            $translatedText = $this->translation->translate($keywordsText, $from, $to);
            $translatedKeywords = array_map('trim', explode(',', $translatedText));
        }

        return [
            'title' => $translatedTitle,
            'summary' => $translatedSummary,
            'keywords' => $translatedKeywords,
        ];
    }
}
