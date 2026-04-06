<?php

declare(strict_types=1);

namespace App\Tests\Integration\Enrichment;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\Service\AiBatchTranslationService;
use App\Enrichment\Service\AiCombinedEnrichmentService;
use App\Enrichment\Service\AiQualityGateService;
use App\Enrichment\Service\AiTextCleanupService;
use App\Enrichment\Service\ArticleEnrichmentService;
use App\Enrichment\Service\ArticleTranslationService;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\Service\TranslationServiceInterface;
use App\Enrichment\ValueObject\BatchTranslationResult;
use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Service\FeedItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(ArticleEnrichmentService::class)]
#[CoversClass(AiCombinedEnrichmentService::class)]
#[CoversClass(AiBatchTranslationService::class)]
#[CoversClass(ArticleTranslationService::class)]
#[UsesClass(CombinedEnrichmentResult::class)]
#[UsesClass(BatchTranslationResult::class)]
#[UsesClass(EnrichmentResult::class)]
final class BatchEnrichmentPipelineTest extends TestCase
{
    public function testFullPipelineWithCombinedEnrichmentAndBatchTranslation(): void
    {
        $enrichmentJson = json_encode([
            'category' => 'tech',
            'summary' => 'OpenAI released GPT-5 with improved reasoning.',
            'keywords' => ['OpenAI', 'GPT-5', 'reasoning'],
        ], JSON_THROW_ON_ERROR);

        $translationJson = json_encode([
            'title' => 'OpenAI veröffentlicht GPT-5',
            'summary' => 'OpenAI hat GPT-5 mit verbessertem Denkvermögen veröffentlicht.',
            'keywords' => ['OpenAI', 'GPT-5', 'Denkvermögen'],
        ], JSON_THROW_ON_ERROR);

        $callCount = 0;
        $responses = [$enrichmentJson, $translationJson];
        $platform = new InMemoryPlatform(static function () use (&$callCount, $responses): string {
            return $responses[$callCount++] ?? $responses[0];
        });

        $category = new Category('Tech', 'tech', 1, '#3b82f6');
        $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepository->method('findBySlug')->willReturn($category);

        $scoring = $this->createStub(ScoringServiceInterface::class);
        $scoring->method('score')->willReturn(0.85);

        $qualityTracker = $this->createStub(ModelQualityTrackerInterface::class);
        $logger = new NullLogger();
        $textCleanup = new AiTextCleanupService();
        $qualityGate = new AiQualityGateService($categoryRepository);

        // Wire up AiCombinedEnrichmentService with stub fallbacks (should not be called)
        $categorizationFallback = $this->createMock(CategorizationServiceInterface::class);
        $categorizationFallback->expects(self::never())->method('categorize');

        $summarizationFallback = $this->createMock(SummarizationServiceInterface::class);
        $summarizationFallback->expects(self::never())->method('summarize');

        $keywordFallback = $this->createMock(KeywordExtractionServiceInterface::class);
        $keywordFallback->expects(self::never())->method('extract');

        $combinedEnrichment = new AiCombinedEnrichmentService(
            $platform,
            $categorizationFallback,
            $summarizationFallback,
            $keywordFallback,
            $qualityGate,
            $qualityTracker,
            $textCleanup,
            $logger,
        );

        // Wire up AiBatchTranslationService with a fallback that should not be called
        $translationFallback = $this->createMock(TranslationServiceInterface::class);
        $translationFallback->expects(self::never())->method('translate');

        $batchTranslation = new AiBatchTranslationService(
            $platform,
            $translationFallback,
            $textCleanup,
            $qualityTracker,
            $logger,
        );

        $articleTranslation = new ArticleTranslationService(
            $batchTranslation,
            'en,de',
        );

        $enrichmentService = new ArticleEnrichmentService(
            $combinedEnrichment,
            $articleTranslation,
            $scoring,
            $categoryRepository,
        );

        // Create article and source
        $sourceCategory = new Category('General', 'general', 0, '#6b7280');
        $source = new Source('Test Source', 'https://example.com/feed', $sourceCategory, new \DateTimeImmutable());
        $source->setLanguage('en');

        $article = new Article(
            'OpenAI releases GPT-5',
            'https://example.com/gpt5',
            $source,
            new \DateTimeImmutable(),
        );

        $item = new FeedItem(
            'OpenAI releases GPT-5',
            'https://example.com/gpt5',
            '<p>OpenAI released GPT-5 with improved reasoning capabilities.</p>',
            'OpenAI released GPT-5 with improved reasoning capabilities.',
            new \DateTimeImmutable(),
        );

        // Execute full pipeline
        $enrichmentService->enrich($article, $item, $source);

        // Verify enrichment applied
        self::assertSame($category, $article->getCategory());
        self::assertSame('OpenAI released GPT-5 with improved reasoning.', $article->getSummary());
        self::assertSame(['OpenAI', 'GPT-5', 'reasoning'], $article->getKeywords());
        self::assertSame(EnrichmentMethod::Ai, $article->getEnrichmentMethod());
        self::assertSame(0.85, $article->getScore());

        // Verify translations applied
        $translations = $article->getTranslations();
        self::assertIsArray($translations);
        self::assertArrayHasKey('en', $translations);
        self::assertArrayHasKey('de', $translations);

        // English (source) should have originals
        self::assertSame('OpenAI releases GPT-5', $translations['en']['title']);

        // German should have translated values from batch
        $german = $translations['de'];
        self::assertSame('OpenAI veröffentlicht GPT-5', $german['title']);
        self::assertSame('OpenAI hat GPT-5 mit verbessertem Denkvermögen veröffentlicht.', $german['summary']);
        self::assertArrayHasKey('keywords', $german);
        self::assertSame(['OpenAI', 'GPT-5', 'Denkvermögen'], $german['keywords']);
    }

    public function testFallbackToIndividualServicesOnMalformedJson(): void
    {
        // Combined enrichment returns garbage → falls back to individual services
        $platform = new InMemoryPlatform('not valid json at all');

        $qualityTracker = $this->createStub(ModelQualityTrackerInterface::class);
        $logger = new NullLogger();
        $textCleanup = new AiTextCleanupService();
        $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
        $qualityGate = new AiQualityGateService($categoryRepository);

        $categorizationFallback = $this->createStub(CategorizationServiceInterface::class);
        $categorizationFallback->method('categorize')->willReturn(
            new EnrichmentResult('science', EnrichmentMethod::RuleBased),
        );

        $summarizationFallback = $this->createStub(SummarizationServiceInterface::class);
        $summarizationFallback->method('summarize')->willReturn(
            new EnrichmentResult('Fallback summary.', EnrichmentMethod::RuleBased),
        );

        $keywordFallback = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywordFallback->method('extract')->willReturn([]);

        $combinedEnrichment = new AiCombinedEnrichmentService(
            $platform,
            $categorizationFallback,
            $summarizationFallback,
            $keywordFallback,
            $qualityGate,
            $qualityTracker,
            $textCleanup,
            $logger,
        );

        $result = $combinedEnrichment->enrich('Test title', 'Test content');

        // Should have fallen back to individual services
        self::assertSame('science', $result->categorySlug);
        self::assertSame('Fallback summary.', $result->summary);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testBatchTranslationFallsBackOnMalformedJson(): void
    {
        $platform = new InMemoryPlatform('invalid json');

        $qualityTracker = $this->createStub(ModelQualityTrackerInterface::class);
        $logger = new NullLogger();
        $textCleanup = new AiTextCleanupService();

        $translationFallback = $this->createStub(TranslationServiceInterface::class);
        $translationFallback->method('translate')->willReturnCallback(
            static fn (string $text): string => '[translated] ' . $text,
        );

        $batchTranslation = new AiBatchTranslationService(
            $platform,
            $translationFallback,
            $textCleanup,
            $qualityTracker,
            $logger,
        );

        $result = $batchTranslation->translateBatch(
            'Test title',
            'Test summary',
            ['keyword1', 'keyword2'],
            'en',
            'de',
        );

        // Should have used individual fallback
        self::assertFalse($result->fromAi);
        self::assertSame('[translated] Test title', $result->title);
        self::assertSame('[translated] Test summary', $result->summary);
    }

    public function testSameLanguageSkipsTranslation(): void
    {
        // Platform should never be called for same-language
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::never())->method('invoke');

        $qualityTracker = $this->createStub(ModelQualityTrackerInterface::class);
        $translationFallback = $this->createStub(TranslationServiceInterface::class);
        $textCleanup = new AiTextCleanupService();
        $logger = new NullLogger();

        $batchTranslation = new AiBatchTranslationService(
            $platform,
            $translationFallback,
            $textCleanup,
            $qualityTracker,
            $logger,
        );

        $result = $batchTranslation->translateBatch('Title', 'Summary', ['kw'], 'en', 'en');

        self::assertFalse($result->fromAi);
        self::assertSame('Title', $result->title);
        self::assertSame('Summary', $result->summary);
    }
}
