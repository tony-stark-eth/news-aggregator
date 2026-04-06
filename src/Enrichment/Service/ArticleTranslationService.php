<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Source\Entity\Source;

final readonly class ArticleTranslationService implements ArticleTranslationServiceInterface
{
    /**
     * @var list<string>
     */
    private array $parsedDisplayLanguages;

    public function __construct(
        private BatchTranslationServiceInterface $batchTranslation,
        private string $displayLanguages = 'en',
    ) {
        $this->parsedDisplayLanguages = array_values(
            array_filter(
                array_map(trim(...), explode(',', $this->displayLanguages)),
                static fn (string $lang): bool => $lang !== '',
            ),
        );
    }

    public function applyTranslations(Article $article, Source $source): void
    {
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

        foreach ($this->parsedDisplayLanguages as $targetLang) {
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

        $primaryLang = $this->parsedDisplayLanguages[0] ?? 'en';
        if ($primaryLang !== $sourceLanguage && isset($translations[$primaryLang])) {
            $article->setTitle($translations[$primaryLang]['title']);
            if ($translations[$primaryLang]['summary'] !== null) {
                $article->setSummary($translations[$primaryLang]['summary']);
            }
        }
    }
}
