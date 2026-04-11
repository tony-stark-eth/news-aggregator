<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\RuleBasedEnrichmentService;
use App\Enrichment\Service\SentimentScoringServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedEnrichmentService::class)]
#[UsesClass(EnrichmentResult::class)]
#[UsesClass(EnrichmentMethod::class)]
final class RuleBasedEnrichmentServiceTest extends TestCase
{
    public function testEnrichAppliesCategoryFromRuleBased(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = $this->createSource($category);
        $article = $this->createArticle($source);
        $item = new FeedItem('AI is changing the world', 'https://example.com/1', null, 'AI software programming developer', null);

        $categorization = $this->createMock(CategorizationServiceInterface::class);
        $categorization->expects(self::once())
            ->method('categorize')
            ->with('AI is changing the world', 'AI software programming developer')
            ->willReturn(new EnrichmentResult('tech', EnrichmentMethod::RuleBased));

        $categoryRepo = $this->createMock(CategoryRepositoryInterface::class);
        $categoryRepo->expects(self::once())
            ->method('findBySlug')
            ->with('tech')
            ->willReturn($category);

        $summarization = $this->createStub(SummarizationServiceInterface::class);
        $summarization->method('summarize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $keywords = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywords->method('extract')->willReturn([]);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.5);

        $sentimentScoring = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentScoring->method('score')->willReturn(null);

        $service = new RuleBasedEnrichmentService($categorization, $summarization, $keywords, $sentimentScoring, $scoring, $categoryRepo);
        $service->enrich($article, $item, $source);

        self::assertSame($category, $article->getCategory());
        self::assertSame(EnrichmentMethod::RuleBased, $article->getEnrichmentMethod());
        self::assertSame(0.5, $article->getScore());
    }

    public function testEnrichFallsBackToSourceCategoryWhenRuleBasedReturnsNull(): void
    {
        $sourceCategory = new Category('Science', 'science', 20, '#10B981');
        $source = $this->createSource($sourceCategory);
        $article = $this->createArticle($source);
        $item = new FeedItem('Random title', 'https://example.com/2', null, null, null);

        $categorization = $this->createMock(CategorizationServiceInterface::class);
        $categorization->expects(self::once())
            ->method('categorize')
            ->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);

        $summarization = $this->createStub(SummarizationServiceInterface::class);
        $summarization->method('summarize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $keywords = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywords->method('extract')->willReturn([]);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.3);

        $sentimentScoring = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentScoring->method('score')->willReturn(null);

        $service = new RuleBasedEnrichmentService($categorization, $summarization, $keywords, $sentimentScoring, $scoring, $categoryRepo);
        $service->enrich($article, $item, $source);

        self::assertSame($sourceCategory, $article->getCategory());
    }

    public function testEnrichAppliesSummaryWhenContentTextExists(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = $this->createSource($category);
        $article = $this->createArticle($source);
        $item = new FeedItem('Title', 'https://example.com/3', null, 'Long content text for summary generation.', null);

        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);

        $summarization = $this->createMock(SummarizationServiceInterface::class);
        $summarization->expects(self::once())
            ->method('summarize')
            ->with('Long content text for summary generation.', 'Title')
            ->willReturn(new EnrichmentResult('Generated summary.', EnrichmentMethod::RuleBased));

        $keywords = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywords->method('extract')->willReturn([]);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.6);

        $sentimentScoring = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentScoring->method('score')->willReturn(null);

        $service = new RuleBasedEnrichmentService($categorization, $summarization, $keywords, $sentimentScoring, $scoring, $categoryRepo);
        $service->enrich($article, $item, $source);

        self::assertSame('Generated summary.', $article->getSummary());
    }

    public function testEnrichSkipsSummaryWhenContentTextIsNull(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = $this->createSource($category);
        $article = $this->createArticle($source);
        $item = new FeedItem('Title', 'https://example.com/4', null, null, null);

        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);

        $summarization = $this->createMock(SummarizationServiceInterface::class);
        $summarization->expects(self::never())->method('summarize');

        $keywords = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywords->method('extract')->willReturn([]);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.4);

        $sentimentScoring = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentScoring->method('score')->willReturn(null);

        $service = new RuleBasedEnrichmentService($categorization, $summarization, $keywords, $sentimentScoring, $scoring, $categoryRepo);
        $service->enrich($article, $item, $source);

        self::assertNull($article->getSummary());
    }

    public function testEnrichAppliesKeywords(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = $this->createSource($category);
        $article = $this->createArticle($source);
        $item = new FeedItem('Google launches AI', 'https://example.com/5', null, 'Google AI content', null);

        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);

        $summarization = $this->createStub(SummarizationServiceInterface::class);
        $summarization->method('summarize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $keywords = $this->createMock(KeywordExtractionServiceInterface::class);
        $keywords->expects(self::once())
            ->method('extract')
            ->with('Google launches AI', 'Google AI content')
            ->willReturn(['Google', 'AI']);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.7);

        $sentimentScoring = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentScoring->method('score')->willReturn(null);

        $service = new RuleBasedEnrichmentService($categorization, $summarization, $keywords, $sentimentScoring, $scoring, $categoryRepo);
        $service->enrich($article, $item, $source);

        self::assertSame(['Google', 'AI'], $article->getKeywords());
    }

    public function testEnrichDoesNotSetKeywordsWhenEmpty(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = $this->createSource($category);
        $article = $this->createArticle($source);
        $item = new FeedItem('Short', 'https://example.com/6', null, null, null);

        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);

        $summarization = $this->createStub(SummarizationServiceInterface::class);
        $summarization->method('summarize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $keywords = $this->createMock(KeywordExtractionServiceInterface::class);
        $keywords->expects(self::once())
            ->method('extract')
            ->willReturn([]);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.3);

        $sentimentScoring = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentScoring->method('score')->willReturn(null);

        $service = new RuleBasedEnrichmentService($categorization, $summarization, $keywords, $sentimentScoring, $scoring, $categoryRepo);
        $service->enrich($article, $item, $source);

        self::assertNull($article->getKeywords());
    }

    private function createSource(Category $category): Source
    {
        return new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
    }

    private function createArticle(Source $source): Article
    {
        return new Article('Test Article', 'https://example.com/article', $source, new \DateTimeImmutable());
    }
}
