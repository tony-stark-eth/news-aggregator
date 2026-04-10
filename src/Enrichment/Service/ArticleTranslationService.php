<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Shared\Service\SettingsServiceInterface;
use App\Source\Entity\Source;

final readonly class ArticleTranslationService implements ArticleTranslationServiceInterface
{
    public function __construct(
        private BatchTranslationServiceInterface $batchTranslation,
        private SettingsServiceInterface $settingsService,
    ) {
    }

    public function applyTranslations(Article $article, Source $source): void
    {
        $parsedDisplayLanguages = $this->parseDisplayLanguages();

        $sourceLanguage = $source->getLanguage() ?? 'en';
        $originalTitle = $article->getTitle();
        $originalSummary = $article->getSummary();
        $originalKeywords = $article->getKeywords() ?? [];

        $article->setTitleOriginal($originalTitle);
        $article->setSummaryOriginal($originalSummary);

        $translations = [];
        $translations[$sourceLanguage] = [
            'title' => $originalTitle,
            'summary' => $originalSummary,
            'keywords' => $originalKeywords,
        ];

        foreach ($parsedDisplayLanguages as $targetLang) {
            if ($targetLang === $sourceLanguage) {
                continue;
            }

            $result = $this->batchTranslation->translateBatch(
                $originalTitle,
                $originalSummary,
                $originalKeywords,
                $sourceLanguage,
                $targetLang,
            );

            $translations[$targetLang] = [
                'title' => $result->title,
                'summary' => $result->summary,
                'keywords' => $result->keywords,
            ];
        }

        $article->setTranslations($translations);

        $primaryLang = $parsedDisplayLanguages[0] ?? 'en';
        if ($primaryLang !== $sourceLanguage && isset($translations[$primaryLang])) {
            $article->setTitle($translations[$primaryLang]['title']);
            if ($translations[$primaryLang]['summary'] !== null) {
                $article->setSummary($translations[$primaryLang]['summary']);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function parseDisplayLanguages(): array
    {
        return array_values(
            array_filter(
                array_map(trim(...), explode(',', $this->settingsService->getDisplayLanguages())),
                static fn (string $lang): bool => $lang !== '',
            ),
        );
    }
}
