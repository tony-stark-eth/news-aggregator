<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Article\Entity\Article;
use App\Chat\ValueObject\SearchSource;

final readonly class ArticleContextFormatter implements ArticleContextFormatterInterface
{
    public function format(array $articles, array $scores, array $sources = []): array
    {
        $results = [];
        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id === null) {
                continue;
            }

            $source = $sources[$id] ?? SearchSource::Keyword;
            $results[] = $this->formatArticle($article, $id, $scores[$id] ?? 0.0, $source);
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * @return array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float, searchSource: string}
     */
    private function formatArticle(Article $article, int $id, float $score, SearchSource $source): array
    {
        return [
            'id' => $id,
            'title' => $article->getTitle(),
            'summary' => $article->getSummary(),
            'keywords' => $article->getKeywords() ?? [],
            'publishedAt' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'url' => $article->getUrl(),
            'score' => round($score, 4),
            'searchSource' => $source->value,
        ];
    }
}
