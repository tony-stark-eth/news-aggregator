<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Article\Entity\Article;

final readonly class ArticleContextFormatter implements ArticleContextFormatterInterface
{
    public function format(array $articles, array $scores): array
    {
        $results = [];
        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id === null) {
                continue;
            }

            $results[] = $this->formatArticle($article, $id, $scores[$id] ?? 0.0);
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * @return array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float}
     */
    private function formatArticle(Article $article, int $id, float $score): array
    {
        return [
            'id' => $id,
            'title' => $article->getTitle(),
            'summary' => $article->getSummary(),
            'keywords' => $article->getKeywords() ?? [],
            'publishedAt' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'url' => $article->getUrl(),
            'score' => round($score, 4),
        ];
    }
}
