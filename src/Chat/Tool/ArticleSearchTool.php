<?php

declare(strict_types=1);

namespace App\Chat\Tool;

use Psr\Log\LoggerInterface;

final readonly class ArticleSearchTool implements ArticleSearchToolInterface
{
    private const float KEYWORD_SCORE_BASE = 1.0;

    private const float KEYWORD_SCORE_DECAY = 0.05;

    private const float BOOST_BOTH = 0.2;

    public function __construct(
        private SearchDependencies $deps,
        private LoggerInterface $logger,
    ) {
    }

    public function search(string $query, ?int $daysBack = null, int $limit = 8): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        $since = $this->resolveSince($daysBack);
        $scores = $this->mergeSearchResults($trimmed, $since, $limit);

        if ($scores === []) {
            $this->logger->info('Hybrid search returned no results for query', [
                'query' => $trimmed,
            ]);

            return [];
        }

        return $this->loadAndFormat($scores, $limit);
    }

    /**
     * @return array<int, float> article ID => combined score
     */
    private function mergeSearchResults(string $query, ?\DateTimeImmutable $since, int $limit): array
    {
        $semanticScores = $this->runSemanticSearch($query, $since, $limit);
        $keywordScores = $this->runKeywordSearch($query, $limit);

        return $this->combineScores($semanticScores, $keywordScores);
    }

    /**
     * @return array<int, float>
     */
    private function runSemanticSearch(string $query, ?\DateTimeImmutable $since, int $limit): array
    {
        $vector = $this->deps->embedding->embed($query);
        if ($vector === null) {
            return [];
        }

        $results = $this->deps->vectorSearch->findBySimilarity($vector, $limit, $since);
        $scores = [];
        foreach ($results as $row) {
            $scores[$row['id']] = $row['similarity'];
        }

        return $scores;
    }

    /**
     * @return array<int, float>
     */
    private function runKeywordSearch(string $query, int $limit): array
    {
        $ids = $this->deps->keywordSearch->search($query, null, $limit);
        $scores = [];
        foreach ($ids as $position => $id) {
            $scores[$id] = max(0.0, self::KEYWORD_SCORE_BASE - ($position * self::KEYWORD_SCORE_DECAY));
        }

        return $scores;
    }

    /**
     * @param array<int, float> $semantic
     * @param array<int, float> $keyword
     *
     * @return array<int, float>
     */
    private function combineScores(array $semantic, array $keyword): array
    {
        $combined = [];
        $allIds = array_unique([...array_keys($semantic), ...array_keys($keyword)]);

        foreach ($allIds as $id) {
            $score = ($semantic[$id] ?? 0.0) + ($keyword[$id] ?? 0.0);
            if (isset($semantic[$id], $keyword[$id])) {
                $score += self::BOOST_BOTH;
            }
            $combined[$id] = $score;
        }

        arsort($combined);

        return $combined;
    }

    /**
     * @param array<int, float> $scores
     *
     * @return list<array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float}>
     */
    private function loadAndFormat(array $scores, int $limit): array
    {
        $topIds = \array_slice(array_keys($scores), 0, $limit);
        $articles = $this->deps->articleRepository->findByIds($topIds);

        return $this->deps->formatter->format($articles, $scores);
    }

    private function resolveSince(?int $daysBack): ?\DateTimeImmutable
    {
        if ($daysBack === null || $daysBack <= 0) {
            return null;
        }

        return $this->deps->clock->now()->modify(sprintf('-%d days', $daysBack));
    }
}
