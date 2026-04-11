<?php

declare(strict_types=1);

namespace App\Chat\Tool;

use App\Chat\ValueObject\SearchMergeResult;
use App\Chat\ValueObject\SearchSource;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'article_search',
    description: 'Search the user\'s article database using hybrid semantic and keyword search. Returns relevant articles with title, summary, keywords, publication date, URL, and relevance score. Use this tool when the user asks about news topics, events, or wants to find specific articles.',
    method: 'search',
)]
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
        $mergeResult = $this->mergeSearchResults($trimmed, $since, $limit);

        if ($mergeResult->scores === []) {
            $this->logger->info('Hybrid search returned no results for query', [
                'query' => $trimmed,
            ]);

            return [];
        }

        $this->logSourceDistribution($mergeResult->sources);

        return $this->loadAndFormat($mergeResult, $limit);
    }

    private function mergeSearchResults(string $query, ?\DateTimeImmutable $since, int $limit): SearchMergeResult
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
     */
    private function combineScores(array $semantic, array $keyword): SearchMergeResult
    {
        $combined = [];
        $sources = [];
        $allIds = array_unique([...array_keys($semantic), ...array_keys($keyword)]);

        foreach ($allIds as $id) {
            $score = ($semantic[$id] ?? 0.0) + ($keyword[$id] ?? 0.0);
            $inSemantic = isset($semantic[$id]);
            $inKeyword = isset($keyword[$id]);

            if ($inSemantic && $inKeyword) {
                $score += self::BOOST_BOTH;
                $sources[$id] = SearchSource::Hybrid;
            } elseif ($inSemantic) {
                $sources[$id] = SearchSource::Semantic;
            } else {
                $sources[$id] = SearchSource::Keyword;
            }

            $combined[$id] = $score;
        }

        arsort($combined);

        return new SearchMergeResult($combined, $sources);
    }

    /**
     * @return list<array{id: int, title: string, summary: string|null, keywords: list<string>, publishedAt: string|null, url: string, score: float, searchSource: string}>
     */
    private function loadAndFormat(SearchMergeResult $mergeResult, int $limit): array
    {
        $topIds = \array_slice(array_keys($mergeResult->scores), 0, $limit);
        $articles = $this->deps->articleRepository->findByIds($topIds);

        return $this->deps->formatter->format($articles, $mergeResult->scores, $mergeResult->sources);
    }

    /**
     * @param array<int, SearchSource> $sources
     */
    private function logSourceDistribution(array $sources): void
    {
        $counts = [
            SearchSource::Keyword->value => 0,
            SearchSource::Semantic->value => 0,
            SearchSource::Hybrid->value => 0,
        ];

        foreach ($sources as $source) {
            ++$counts[$source->value];
        }

        $this->logger->info('Search results: {keyword} keyword, {semantic} semantic, {hybrid} hybrid', $counts);
    }

    private function resolveSince(?int $daysBack): ?\DateTimeImmutable
    {
        if ($daysBack === null || $daysBack <= 0) {
            return null;
        }

        return $this->deps->clock->now()->modify(sprintf('-%d days', $daysBack));
    }
}
