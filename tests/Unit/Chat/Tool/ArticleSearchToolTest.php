<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Tool;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Chat\Repository\VectorSearchRepositoryInterface;
use App\Chat\Service\ArticleContextFormatter;
use App\Chat\Service\EmbeddingServiceInterface;
use App\Chat\Tool\ArticleSearchTool;
use App\Chat\Tool\SearchDependencies;
use App\Chat\ValueObject\SearchMergeResult;
use App\Chat\ValueObject\SearchSource;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ArticleSearchTool::class)]
#[CoversClass(SearchDependencies::class)]
#[CoversClass(SearchMergeResult::class)]
#[CoversClass(SearchSource::class)]
final class ArticleSearchToolTest extends TestCase
{
    private EmbeddingServiceInterface&MockObject $embedding;

    private VectorSearchRepositoryInterface&MockObject $vectorSearch;

    private ArticleSearchServiceInterface&MockObject $keywordSearch;

    private ArticleRepositoryInterface&MockObject $articleRepository;

    private LoggerInterface&MockObject $logger;

    private MockClock $clock;

    private ArticleSearchTool $tool;

    protected function setUp(): void
    {
        $this->embedding = $this->createMock(EmbeddingServiceInterface::class);
        $this->vectorSearch = $this->createMock(VectorSearchRepositoryInterface::class);
        $this->keywordSearch = $this->createMock(ArticleSearchServiceInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-10 12:00:00'));

        $deps = new SearchDependencies(
            $this->embedding,
            $this->vectorSearch,
            $this->keywordSearch,
            $this->articleRepository,
            new ArticleContextFormatter(),
            $this->clock,
        );

        $this->tool = new ArticleSearchTool($deps, $this->logger);
    }

    public function testSearchEmptyQueryReturnsEmpty(): void
    {
        $result = $this->tool->search('');
        self::assertSame([], $result);
    }

    public function testSearchWhitespaceOnlyQueryReturnsEmpty(): void
    {
        $result = $this->tool->search('   ');
        self::assertSame([], $result);
    }

    public function testSearchHybridMergesDedupesAndRanks(): void
    {
        $vector = [0.1, 0.2, 0.3];
        $this->embedding->expects(self::once())
            ->method('embed')
            ->with('AI news')
            ->willReturn($vector);

        $this->vectorSearch->expects(self::once())
            ->method('findBySimilarity')
            ->with($vector, 8, null)
            ->willReturn([
                [
                    'id' => 1,
                    'similarity' => 0.9,
                ],
                [
                    'id' => 2,
                    'similarity' => 0.7,
                ],
            ]);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->with('AI news', null, 8)
            ->willReturn([2, 3]);

        $article1 = $this->createArticle(1, 'Article 1', 'https://example.com/1');
        $article2 = $this->createArticle(2, 'Article 2', 'https://example.com/2');
        $article3 = $this->createArticle(3, 'Article 3', 'https://example.com/3');

        $this->articleRepository->expects(self::once())
            ->method('findByIds')
            ->willReturn([$article1, $article2, $article3]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Search results: {keyword} keyword, {semantic} semantic, {hybrid} hybrid',
                self::callback(static fn (array $ctx): bool => $ctx['keyword'] === 1
                    && $ctx['semantic'] === 1
                    && $ctx['hybrid'] === 1),
            );

        $results = $this->tool->search('AI news');

        self::assertCount(3, $results);
        // Article 2 found by both: 0.7 (semantic) + 1.0 (keyword pos 0) + 0.2 (boost) = 1.9
        self::assertSame(2, $results[0]['id']);
        self::assertSame(1.9, $results[0]['score']);
        self::assertSame('hybrid', $results[0]['searchSource']);
        // Article 3: 0.95 (keyword pos 1)
        self::assertSame(3, $results[1]['id']);
        self::assertSame(0.95, $results[1]['score']);
        self::assertSame('keyword', $results[1]['searchSource']);
        // Article 1: 0.9 (semantic only)
        self::assertSame(1, $results[2]['id']);
        self::assertSame(0.9, $results[2]['score']);
        self::assertSame('semantic', $results[2]['searchSource']);
    }

    public function testSearchFallsBackToKeywordOnlyWhenEmbeddingReturnsNull(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn(null);

        $this->vectorSearch->expects(self::never())
            ->method('findBySimilarity');

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->with('test query', null, 8)
            ->willReturn([5]);

        $article = $this->createArticle(5, 'Keyword Article', 'https://example.com/5');
        $this->articleRepository->expects(self::once())
            ->method('findByIds')
            ->with([5])
            ->willReturn([$article]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Search results: {keyword} keyword, {semantic} semantic, {hybrid} hybrid',
                self::callback(static fn (array $ctx): bool => $ctx['keyword'] === 1
                    && $ctx['semantic'] === 0
                    && $ctx['hybrid'] === 0),
            );

        $results = $this->tool->search('test query');

        self::assertCount(1, $results);
        self::assertSame(5, $results[0]['id']);
        self::assertSame(1.0, $results[0]['score']);
        self::assertSame('keyword', $results[0]['searchSource']);
    }

    public function testSearchWithDaysBackFilterPassesSinceDate(): void
    {
        $vector = [0.1];
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn($vector);

        $expectedSince = new \DateTimeImmutable('2026-04-03 12:00:00');

        $this->vectorSearch->expects(self::once())
            ->method('findBySimilarity')
            ->with(
                $vector,
                8,
                self::callback(static fn (\DateTimeImmutable $since): bool => $since->format('Y-m-d H:i:s') === $expectedSince->format('Y-m-d H:i:s')),
            )
            ->willReturn([]);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with('Hybrid search returned no results for query', self::callback(
                static fn (array $ctx): bool => $ctx['query'] === 'test',
            ));

        $this->tool->search('test', 7);
    }

    public function testSearchWithZeroDaysBackDoesNotFilterByDate(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn([0.1]);

        $this->vectorSearch->expects(self::once())
            ->method('findBySimilarity')
            ->with([0.1], 8, null)
            ->willReturn([]);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([]);

        $this->logger->expects(self::once())
            ->method('info');

        $this->tool->search('test', 0);
    }

    public function testSearchWithNegativeDaysBackDoesNotFilterByDate(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn([0.1]);

        $this->vectorSearch->expects(self::once())
            ->method('findBySimilarity')
            ->with([0.1], 8, null)
            ->willReturn([]);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([]);

        $this->logger->expects(self::once())
            ->method('info');

        $this->tool->search('test', -1);
    }

    public function testSearchNoResultsLogsInfo(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn(null);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Hybrid search returned no results for query',
                self::callback(static fn (array $ctx): bool => $ctx['query'] === 'nothing'),
            );

        $result = $this->tool->search('nothing');
        self::assertSame([], $result);
    }

    public function testSearchRespectsLimit(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn(null);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->with('test', null, 3)
            ->willReturn([1, 2, 3, 4, 5]);

        $articles = [];
        for ($i = 1; $i <= 5; ++$i) {
            $articles[] = $this->createArticle($i, "Article {$i}", "https://example.com/{$i}");
        }

        $this->articleRepository->expects(self::once())
            ->method('findByIds')
            ->with([1, 2, 3])
            ->willReturn(array_slice($articles, 0, 3));

        $results = $this->tool->search('test', null, 3);
        self::assertCount(3, $results);
    }

    public function testSearchOutputFormat(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn(null);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([1]);

        $article = $this->createArticle(1, 'Test Title', 'https://example.com/1');
        $article->setSummary('Test summary');
        $article->setKeywords(['keyword1', 'keyword2']);
        $article->setPublishedAt(new \DateTimeImmutable('2026-04-09T10:00:00+00:00'));

        $this->articleRepository->expects(self::once())
            ->method('findByIds')
            ->willReturn([$article]);

        $results = $this->tool->search('test');

        self::assertCount(1, $results);
        $result = $results[0];
        self::assertSame(1, $result['id']);
        self::assertSame('Test Title', $result['title']);
        self::assertSame('Test summary', $result['summary']);
        self::assertSame(['keyword1', 'keyword2'], $result['keywords']);
        self::assertSame('2026-04-09T10:00:00+00:00', $result['publishedAt']);
        self::assertSame('https://example.com/1', $result['url']);
        self::assertSame(1.0, $result['score']);
        self::assertSame('keyword', $result['searchSource']);
    }

    public function testSearchArticleWithNullFieldsFormatsGracefully(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn(null);

        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([1]);

        $article = $this->createArticle(1, 'Title', 'https://example.com/1');
        // summary, keywords, publishedAt are all null by default

        $this->articleRepository->expects(self::once())
            ->method('findByIds')
            ->willReturn([$article]);

        $results = $this->tool->search('test');

        self::assertCount(1, $results);
        self::assertNull($results[0]['summary']);
        self::assertSame([], $results[0]['keywords']);
        self::assertNull($results[0]['publishedAt']);
    }

    public function testSearchKeywordScoreDecaysByPosition(): void
    {
        $this->embedding->expects(self::once())
            ->method('embed')
            ->willReturn(null);

        // 3 results from keyword search
        $this->keywordSearch->expects(self::once())
            ->method('search')
            ->willReturn([10, 20, 30]);

        $articles = [
            $this->createArticle(10, 'First', 'https://example.com/10'),
            $this->createArticle(20, 'Second', 'https://example.com/20'),
            $this->createArticle(30, 'Third', 'https://example.com/30'),
        ];

        $this->articleRepository->expects(self::once())
            ->method('findByIds')
            ->willReturn($articles);

        $results = $this->tool->search('test');

        // Position 0: 1.0, Position 1: 0.95, Position 2: 0.90
        self::assertSame(1.0, $results[0]['score']);
        self::assertSame(0.95, $results[1]['score']);
        self::assertSame(0.9, $results[2]['score']);
    }

    private function createArticle(int $id, string $title, string $url): Article
    {
        $source = $this->createStub(Source::class);
        $source->method('getName')->willReturn('Test Source');

        $article = new Article($title, $url, $source, new \DateTimeImmutable('2026-04-09'));

        $reflection = new \ReflectionProperty(Article::class, 'id');
        $reflection->setValue($article, $id);

        return $article;
    }
}
