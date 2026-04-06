<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiCombinedEnrichmentService;
use App\Enrichment\Service\AiQualityGateServiceInterface;
use App\Enrichment\Service\AiTextCleanupService;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiCombinedEnrichmentService::class)]
#[UsesClass(CombinedEnrichmentResult::class)]
#[UsesClass(EnrichmentResult::class)]
final class AiCombinedEnrichmentServiceTest extends TestCase
{
    public function testSuccessfulCombinedEnrichment(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'Google announced a new AI model for developers.',
            'keywords' => ['Google', 'AI', 'developers'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Google AI announcement', 'New AI model for developers');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame('Google announced a new AI model for developers.', $result->summary);
        self::assertSame(['Google', 'AI', 'developers'], $result->keywords);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('openrouter/free', $result->modelUsed);
    }

    public function testHandlesMarkdownWrappedJson(): void
    {
        $json = "```json\n" . json_encode([
            'category' => 'tech',
            'summary' => 'A concise summary of the article content.',
            'keywords' => ['Google', 'AI'],
        ], JSON_THROW_ON_ERROR) . "\n```";

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testFallsBackToIndividualOnJsonParseFailure(): void
    {
        $platform = new InMemoryPlatform('not valid json at all');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        // Falls back to individual services (which are mocked as rule-based)
        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFallsBackToIndividualOnPlatformException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testPartialFallbackOnInvalidCategory(): void
    {
        $json = json_encode([
            'category' => 'invalid_category',
            'summary' => 'A valid summary that passes quality gates easily.',
            'keywords' => ['Keyword1', 'Keyword2'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        // Category falls back to individual, but summary+keywords from AI
        self::assertSame('tech', $result->categorySlug); // from fallback
        self::assertSame('A valid summary that passes quality gates easily.', $result->summary);
        self::assertSame(['Keyword1', 'Keyword2'], $result->keywords);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testPartialFallbackOnInvalidSummary(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'Too short',
            'keywords' => ['Keyword1'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Some content here');

        self::assertSame('tech', $result->categorySlug);
        // Summary falls back to individual (mock returns 'rule summary')
        self::assertSame('rule summary', $result->summary);
        self::assertSame(['Keyword1'], $result->keywords);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testPartialFallbackOnMissingKeywords(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(['rule-keyword'], $result->keywords); // from fallback
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testAllFieldsInvalidFallsBackCompletely(): void
    {
        $json = json_encode([
            'category' => 'invalid',
            'summary' => 'x',
            'keywords' => [],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertNull($result->modelUsed);
    }

    public function testNullContentSkipsSummary(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', null);

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testKeywordsExceedingMaxLengthAreFiltered(): void
    {
        $longKeyword = str_repeat('a', 101);
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => [$longKeyword, 'Valid'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(['Valid'], $result->keywords);
    }

    private function createService(
        PlatformInterface $platform,
        ?ModelQualityTrackerInterface $tracker = null,
    ): AiCombinedEnrichmentService {
        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(
            new EnrichmentResult('tech', EnrichmentMethod::RuleBased),
        );

        $summarization = $this->createStub(SummarizationServiceInterface::class);
        $summarization->method('summarize')->willReturn(
            new EnrichmentResult('rule summary', EnrichmentMethod::RuleBased),
        );

        $keywordExtraction = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywordExtraction->method('extract')->willReturn(['rule-keyword']);

        $qualityGate = $this->createStub(AiQualityGateServiceInterface::class);
        $qualityGate->method('validateCategorization')->willReturnCallback(
            static fn (string $slug): bool => in_array($slug, ['politics', 'business', 'tech', 'science', 'sports'], true),
        );
        $qualityGate->method('validateSummary')->willReturnCallback(
            static fn (string $summary): bool => mb_strlen($summary) >= 20 && mb_strlen($summary) <= 500,
        );

        return new AiCombinedEnrichmentService(
            $platform,
            $categorization,
            $summarization,
            $keywordExtraction,
            $qualityGate,
            $tracker ?? $this->createStub(ModelQualityTrackerInterface::class),
            new AiTextCleanupService(),
            new NullLogger(),
        );
    }
}
