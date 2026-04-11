<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiCombinedEnrichmentService;
use App\Enrichment\Service\AiQualityGateServiceInterface;
use App\Enrichment\Service\AiTextCleanupService;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\KeywordFilterService;
use App\Enrichment\Service\SentimentScoringServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
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
            'keywords' => ['Google', 'Azure', 'developers'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');
        $tracker->expects(self::never())->method('recordRejection');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Google AI announcement', 'New AI model for developers');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame('Google announced a new AI model for developers.', $result->summary);
        self::assertSame(['Google', 'Azure', 'developers'], $result->keywords);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('openrouter/free', $result->modelUsed);
    }

    public function testHandlesMarkdownWrappedJson(): void
    {
        $json = "```json\n" . json_encode([
            'category' => 'tech',
            'summary' => 'A concise summary of the article content.',
            'keywords' => ['Google', 'Azure'],
        ], JSON_THROW_ON_ERROR) . "\n```";

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testFallsBackToIndividualOnJsonParseFailure(): void
    {
        $platform = new InMemoryPlatform('not valid json at all');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');
        $tracker->expects(self::never())->method('recordAcceptance');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                'Combined AI enrichment JSON parse failed',
                self::callback(static function (array $context): bool {
                    return array_key_exists('response', $context)
                        && array_key_exists('model', $context)
                        && is_string($context['response'])
                        && is_string($context['model']);
                }),
            );
        // Must NOT trigger the exception catch path (no warning logged)
        $logger->expects(self::never())->method('warning');

        $service = $this->createService($platform, $tracker, $logger);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFallsBackToIndividualOnPlatformException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                'Combined AI enrichment failed: {error}',
                self::callback(static function (array $context): bool {
                    return $context['error'] === 'API down'
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = $this->createService($platform, logger: $logger);

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

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
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

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Some content here');

        self::assertSame('tech', $result->categorySlug);
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

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(['rule-keyword'], $result->keywords);
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
        $tracker->expects(self::never())->method('recordAcceptance');

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

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

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

    public function testKeywordExactlyAtMaxLengthIsAccepted(): void
    {
        // Mutation #47: LessThanOrEqualTo — `<=` to `<` on 100 length
        $keyword100 = str_repeat('a', 100);
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => [$keyword100],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame([$keyword100], $result->keywords);
    }

    public function testCategoryTrimIsNeeded(): void
    {
        // Mutation #28: UnwrapTrim on category — without trim, '  science  ' fails validation
        // Using 'science' (not 'tech') so we can distinguish from fallback
        $json = json_encode([
            'category' => '  science  ',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        // Must be 'science' from AI (after trim+lower), NOT 'tech' from fallback
        self::assertSame('science', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testCategoryMbStrtolowerNeeded(): void
    {
        // Mutation #27: MBString — strtolower('ÜBER') → 'Über', not 'über'
        // Quality gate won't validate 'Über' — it's not in the allowed list
        // Use category with multibyte uppercase that strtolower can't handle
        // But our allowed list doesn't have multibyte categories.
        // Use 'SCIENCE' — strtolower works for ASCII, so test with uppercase ASCII
        // The real test is: mb_strtolower works for all input, strtolower doesn't for multibyte
        // Since our categories are ASCII, the MBString mutation on category is equivalent.
        // Instead, verify the behavior with a standard uppercase category
        $json = json_encode([
            'category' => '  SCIENCE  ',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('science', $result->categorySlug);
    }

    public function testSummaryIsTrimmedAndCleaned(): void
    {
        // Mutation: UnwrapTrim on summary
        $json = json_encode([
            'category' => 'tech',
            'summary' => '  A valid summary with whitespace around it.  ',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('A valid summary with whitespace around it.', $result->summary);
    }

    public function testKeywordsAreTrimmed(): void
    {
        // Mutation #45: UnwrapTrim on keywords
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['  Google  ', '  Azure  '],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(['Google', 'Azure'], $result->keywords);
    }

    public function testEmptyKeywordsAfterTrimAreRejected(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['  ', 'Valid'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(['Valid'], $result->keywords);
    }

    public function testKeywordTrimAffectsMbStrlenCheck(): void
    {
        // Mutation #29: UnwrapTrim — mb_strlen(trim($keyword)) → mb_strlen($keyword)
        // Keyword with whitespace: after trim = 100 chars (passes), without trim = 102 (rejected)
        $keyword = ' ' . str_repeat('a', 100) . ' ';
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => [$keyword],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        // After trim: 100 chars (passes <= 100), stored as trimmed
        self::assertSame([str_repeat('a', 100)], $result->keywords);
    }

    public function testKeywordMbStrlenUsedNotStrlen(): void
    {
        // Mutation #30: MBString — mb_strlen vs strlen on keyword length check
        // Multibyte keyword: mb_strlen = 100 (passes), strlen = 200 (rejected by strlen)
        $keyword = str_repeat('ä', 100); // 100 mb chars, 200 bytes in UTF-8
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => [$keyword],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        // mb_strlen = 100 (passes), strlen would be 200 (rejected)
        self::assertSame([$keyword], $result->keywords);
    }

    public function testKeywordsAreCappedAtMaxKeywords(): void
    {
        // Keyword filter limits to 5 max keywords
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertCount(5, $result->keywords);
        self::assertSame(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'], $result->keywords);
    }

    public function testTrimOnRawResponse(): void
    {
        // Mutation #31: UnwrapTrim on $rawText = trim($response->asText())
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        // Add whitespace that requires trim
        $platform = new InMemoryPlatform("  \n" . $json . "\n  ");

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testMbSubstrTruncatesResponseInLogContext(): void
    {
        // Mutation #32: MBString — mb_substr vs substr for truncation in log
        $longResponse = str_repeat('ä', 300);
        $platform = new InMemoryPlatform($longResponse);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::anything(),
                self::callback(static function (array $context): bool {
                    // mb_substr truncates to 200 multibyte chars, not 200 bytes
                    return is_string($context['response'])
                        && mb_strlen($context['response']) === 200;
                }),
            );

        $service = $this->createService($platform, $tracker, $logger);

        $service->enrich('Title', 'Content');
    }

    public function testJsonParseFailureReturnNullStopsExecution(): void
    {
        // Mutation #33: ReturnRemoval of `return null` after JSON parse failure
        $platform = new InMemoryPlatform('not json');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        // Must fall back completely — return null is essential
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertSame('tech', $result->categorySlug);
    }

    public function testOnlyCategoryFromAiStillRecordsAcceptance(): void
    {
        // Mutation #34-35: LogicalOr on $anyFieldFromAi
        // Only category is valid from AI, summary and keywords are invalid
        $json = json_encode([
            'category' => 'science',
            'summary' => 'x',
            'keywords' => [],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');
        $tracker->expects(self::never())->method('recordRejection');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('science', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testOnlySummaryFromAiStillRecordsAcceptance(): void
    {
        // Mutation #34-35: Another branch of LogicalOr — only summary is valid
        $json = json_encode([
            'category' => 'invalid',
            'summary' => 'A valid summary that passes quality gates for articles.',
            'keywords' => [],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testOnlyKeywordsFromAiStillRecordsAcceptance(): void
    {
        // Mutation #34-35: Third branch of LogicalOr — only keywords are valid
        $json = json_encode([
            'category' => 'invalid',
            'summary' => 'x',
            'keywords' => ['ValidKeyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame(['ValidKeyword'], $result->keywords);
    }

    public function testCategorySlugPreservedWhenValidFromAi(): void
    {
        // Mutation #36: AssignCoalesce — $categorySlug ??= fallback
        // When AI returns valid category, the ??= must NOT overwrite it
        $json = json_encode([
            'category' => 'science',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        // Must be 'science' from AI, not 'tech' from fallback
        self::assertSame('science', $result->categorySlug);
    }

    public function testCategoryNotSetInResponseReturnsNull(): void
    {
        // Mutation #37-41: Various mutations on category extraction guard
        $json = json_encode([
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        // Category falls back to 'tech' from individual service
        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testCategoryNotStringReturnsNull(): void
    {
        // Mutation #37-41: Tests second part of || — category set but not string
        $response = '{"category": 123, "summary": "A valid summary that passes quality checks.", "keywords": ["KW"]}';

        $platform = new InMemoryPlatform($response);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        // Category falls back
        self::assertSame('tech', $result->categorySlug);
    }

    public function testSummaryNotSetInResponseReturnsNull(): void
    {
        // Mutation #44: LogicalOr on summary extraction
        $json = json_encode([
            'category' => 'tech',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('rule summary', $result->summary);
    }

    public function testSummaryNotStringReturnsNull(): void
    {
        // Mutation #44: Tests second part of || — summary set but not string
        $response = '{"category": "tech", "summary": 123, "keywords": ["KW"]}';

        $platform = new InMemoryPlatform($response);
        $service = $this->createService($platform);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('rule summary', $result->summary);
    }

    public function testContentTextCoalesceUsesEmptyStringWhenNull(): void
    {
        // Mutation #23: Coalesce — `$contentText ?? ''` → `'' ?? $contentText`
        // When contentText is null: normal gives '', mutation gives '' ('' ?? null = '')
        // These are equivalent for null. But with non-null content, we verify it's used.
        $capturedPrompt = '';
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);
        $platform = new InMemoryPlatform(static function (Model $model, MessageBag $input) use (&$capturedPrompt, $json): string {
            $userMessage = $input->getUserMessage();
            \assert($userMessage instanceof UserMessage);
            $capturedPrompt = $userMessage->asText();

            return $json;
        });

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        // Non-null content — the coalesce must use actual content, not ''
        $result = $service->enrich('Title', 'Important article content here');

        self::assertSame('tech', $result->categorySlug);
        \assert(is_string($capturedPrompt));
        self::assertStringContainsString('Important article content here', $capturedPrompt);
    }

    public function testNullContentCoalesceDoesNotPassNullToMbSubstr(): void
    {
        // Mutation #23: With null content, `$contentText ?? ''` gives ''
        // Mutation flips to `'' ?? $contentText` which also gives '' — equivalent for null
        // But we still verify the null path works correctly
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', null);

        self::assertSame('tech', $result->categorySlug);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
    }

    public function testFallbackWithNullContentSummarizationSkipped(): void
    {
        // Mutation #50: Ternary — tests that with null content, summarization is skipped
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('fail'));

        $summarization = $this->createMock(SummarizationServiceInterface::class);
        $summarization->expects(self::never())->method('summarize');

        $service = $this->createServiceWithCustomSummarization($platform, $summarization);

        $result = $service->enrich('Title', null);

        self::assertNull($result->summary);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testFallbackWithContentCallsSummarization(): void
    {
        // Mutation #50: Ternary flipped — with content, summarization MUST be called
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('fail'));

        $summarization = $this->createMock(SummarizationServiceInterface::class);
        $summarization->expects(self::once())->method('summarize')
            ->willReturn(new EnrichmentResult('fallback summary', EnrichmentMethod::RuleBased));

        $service = $this->createServiceWithCustomSummarization($platform, $summarization);

        $result = $service->enrich('Title', 'Some content');

        self::assertSame('fallback summary', $result->summary);
    }

    public function testNullContentFallbackReturnsNullSummary(): void
    {
        // Mutation #56: NullSafePropertyCall — $sumResult?->value
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('fail'));

        $service = $this->createService($platform);

        $result = $service->enrich('Title', null);

        // $sumResult is null, so $sumResult?->value should be null
        self::assertNull($result->summary);
    }

    public function testParseJsonTrimsBeforeParsing(): void
    {
        // Mutation #49: UnwrapTrim in parseJson
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'A valid summary that passes all quality checks.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform("\t  " . $json . "  \t");

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame('tech', $result->categorySlug);
    }

    public function testRecordRejectionNotCalledOnPlatformException(): void
    {
        // Exception path doesn't call tracker — it falls through to fallbackToIndividual
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API error'));

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::never())->method('recordAcceptance');
        $tracker->expects(self::never())->method('recordRejection');

        $service = $this->createService($platform, $tracker);

        $result = $service->enrich('Title', 'Content');

        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testLoggerInfoIncludesResponseAndModelContextOnJsonFailure(): void
    {
        $platform = new InMemoryPlatform('bad json response text');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                'Combined AI enrichment JSON parse failed',
                self::callback(static function (array $context): bool {
                    return array_key_exists('response', $context)
                        && array_key_exists('model', $context)
                        && is_string($context['response'])
                        && str_contains($context['response'], 'bad json');
                }),
            );

        $service = $this->createService($platform, $tracker, $logger);

        $service->enrich('Title', 'Content');
    }

    public function testLoggerWarningIncludesAllContextKeysOnException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('Timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::anything(),
                self::callback(static function (array $context): bool {
                    return array_key_exists('error', $context)
                        && array_key_exists('model', $context)
                        && $context['error'] === 'Timeout';
                }),
            );

        $service = $this->createService($platform, logger: $logger);

        $service->enrich('Title', 'Content');
    }

    public function testExtractsSentimentScoreFromAiResponse(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'Google announced a new AI model for developers.',
            'keywords' => ['Google', 'AI'],
            'sentiment_score' => 0.75,
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Google AI announcement', 'New AI model for developers');

        self::assertSame(0.75, $result->sentimentScore);
    }

    public function testSentimentScoreNullWhenMissing(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'Google announced a new AI model for developers.',
            'keywords' => ['Google', 'AI'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Google AI announcement', 'New AI model for developers');

        self::assertNull($result->sentimentScore);
    }

    public function testSentimentScoreNullWhenOutOfRange(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'Google announced a new AI model for developers.',
            'keywords' => ['Google', 'AI'],
            'sentiment_score' => 5.0,
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Google AI announcement', 'New AI model for developers');

        self::assertNull($result->sentimentScore);
    }

    public function testSentimentScoreNullWhenNotNumeric(): void
    {
        $json = json_encode([
            'category' => 'tech',
            'summary' => 'Google announced a new AI model for developers.',
            'keywords' => ['Google', 'AI'],
            'sentiment_score' => 'positive',
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Google AI announcement', 'New AI model for developers');

        self::assertNull($result->sentimentScore);
    }

    public function testNegativeSentimentScoreExtracted(): void
    {
        $json = json_encode([
            'category' => 'politics',
            'summary' => 'The government announced new sanctions against multiple countries.',
            'keywords' => ['sanctions', 'government'],
            'sentiment_score' => -0.8,
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);
        $service = $this->createService($platform);

        $result = $service->enrich('Sanctions Announcement', 'The government announced new sanctions');

        self::assertSame(-0.8, $result->sentimentScore);
    }

    private function createService(
        PlatformInterface $platform,
        ?ModelQualityTrackerInterface $tracker = null,
        ?LoggerInterface $logger = null,
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
        $qualityGate->method('validateSentiment')->willReturnCallback(
            static fn (float $score): bool => $score >= -1.0 && $score <= 1.0,
        );

        $sentimentFallback = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentFallback->method('score')->willReturn(null);

        return new AiCombinedEnrichmentService(
            $platform,
            $categorization,
            $summarization,
            $keywordExtraction,
            $sentimentFallback,
            $qualityGate,
            $tracker ?? $this->createStub(ModelQualityTrackerInterface::class),
            new AiTextCleanupService(),
            new KeywordFilterService(),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function createServiceWithCustomSummarization(
        PlatformInterface $platform,
        SummarizationServiceInterface $summarization,
    ): AiCombinedEnrichmentService {
        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(
            new EnrichmentResult('tech', EnrichmentMethod::RuleBased),
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
        $qualityGate->method('validateSentiment')->willReturnCallback(
            static fn (float $score): bool => $score >= -1.0 && $score <= 1.0,
        );

        $sentimentFallback = $this->createStub(SentimentScoringServiceInterface::class);
        $sentimentFallback->method('score')->willReturn(null);

        return new AiCombinedEnrichmentService(
            $platform,
            $categorization,
            $summarization,
            $keywordExtraction,
            $sentimentFallback,
            $qualityGate,
            $this->createStub(ModelQualityTrackerInterface::class),
            new AiTextCleanupService(),
            new KeywordFilterService(),
            $this->createStub(LoggerInterface::class),
        );
    }
}
