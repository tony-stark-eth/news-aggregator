<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\Service\ArticleEnrichmentService;
use App\Enrichment\Service\ArticleTranslationServiceInterface;
use App\Enrichment\Service\CombinedEnrichmentServiceInterface;
use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArticleEnrichmentService::class)]
#[UsesClass(CombinedEnrichmentResult::class)]
#[UsesClass(FeedItem::class)]
final class ArticleEnrichmentServiceTest extends TestCase
{
    public function testEnrichUsesCombinedServiceAndTranslation(): void
    {
        $combinedResult = new CombinedEnrichmentResult(
            'tech',
            'A summary of the article.',
            ['Google', 'AI'],
            EnrichmentMethod::Ai,
            'model-1',
        );

        $combined = $this->createStub(CombinedEnrichmentServiceInterface::class);
        $combined->method('enrich')->willReturn($combinedResult);

        $translation = $this->createMock(ArticleTranslationServiceInterface::class);
        $translation->expects(self::once())->method('applyTranslations');

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(42.0);

        $category = new Category('Technology', 'tech', 1, '#000');
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('findBySlug')->willReturn($category);

        $service = new ArticleEnrichmentService($combined, $translation, $scoring, $categoryRepo);

        $article = $this->createArticle('Title', 'https://example.com/article');
        $item = new FeedItem('Title', 'https://example.com/article', null, 'Content text', null);
        $source = $this->createStub(Source::class);
        $source->method('getCategory')->willReturn($category);

        $service->enrich($article, $item, $source);

        self::assertSame($category, $article->getCategory());
        self::assertSame('A summary of the article.', $article->getSummary());
        self::assertSame(['Google', 'AI'], $article->getKeywords());
        self::assertSame(42.0, $article->getScore());
    }

    public function testFallsBackToSourceCategoryWhenSlugNotFound(): void
    {
        $combinedResult = new CombinedEnrichmentResult(
            null,
            null,
            [],
            EnrichmentMethod::RuleBased,
        );

        $combined = $this->createStub(CombinedEnrichmentServiceInterface::class);
        $combined->method('enrich')->willReturn($combinedResult);

        $translation = $this->createStub(ArticleTranslationServiceInterface::class);
        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.0);

        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('findBySlug')->willReturn(null);

        $sourceCategory = new Category('Science', 'science', 1, '#000');
        $source = $this->createStub(Source::class);
        $source->method('getCategory')->willReturn($sourceCategory);

        $service = new ArticleEnrichmentService($combined, $translation, $scoring, $categoryRepo);

        $article = $this->createArticle('Title', 'https://example.com/article');
        $item = new FeedItem('Title', 'https://example.com/article', null, null, null);

        $service->enrich($article, $item, $source);

        self::assertSame($sourceCategory, $article->getCategory());
    }

    public function testSetsEnrichmentMethodAndModel(): void
    {
        $combinedResult = new CombinedEnrichmentResult(
            'tech',
            'Summary text here that is valid.',
            [],
            EnrichmentMethod::Ai,
            'actual-model-id',
        );

        $combined = $this->createStub(CombinedEnrichmentServiceInterface::class);
        $combined->method('enrich')->willReturn($combinedResult);

        $translation = $this->createStub(ArticleTranslationServiceInterface::class);
        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.0);

        $category = new Category('Technology', 'tech', 1, '#000');
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('findBySlug')->willReturn($category);

        $source = $this->createStub(Source::class);
        $source->method('getCategory')->willReturn($category);

        $service = new ArticleEnrichmentService($combined, $translation, $scoring, $categoryRepo);

        $article = $this->createArticle('Title', 'https://example.com/article');
        $item = new FeedItem('Title', 'https://example.com/article', null, 'Content', null);

        $service->enrich($article, $item, $source);

        self::assertSame(EnrichmentMethod::Ai, $article->getEnrichmentMethod());
        self::assertSame('actual-model-id', $article->getAiModelUsed());
    }

    private function createArticle(string $title, string $url): Article
    {
        $source = $this->createStub(Source::class);

        return new Article($title, $url, $source, new \DateTimeImmutable());
    }
}
