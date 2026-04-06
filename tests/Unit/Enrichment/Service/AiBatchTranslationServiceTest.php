<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiBatchTranslationService;
use App\Enrichment\Service\AiTextCleanupService;
use App\Enrichment\Service\TranslationServiceInterface;
use App\Enrichment\ValueObject\BatchTranslationResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiBatchTranslationService::class)]
#[UsesClass(BatchTranslationResult::class)]
final class AiBatchTranslationServiceTest extends TestCase
{
    public function testSuccessfulBatchTranslation(): void
    {
        $json = json_encode([
            'title' => 'Federal government announces new measures',
            'summary' => 'The government has announced a new policy package.',
            'keywords' => ['Government', 'Policy', 'Berlin'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');
        $tracker->expects(self::never())->method('recordRejection');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch(
            'Bundesregierung beschließt neue Maßnahmen',
            'Die Regierung hat ein neues Maßnahmenpaket angekündigt.',
            ['Regierung', 'Politik', 'Berlin'],
            'de',
            'en',
        );

        self::assertSame('Federal government announces new measures', $result->title);
        self::assertSame('The government has announced a new policy package.', $result->summary);
        self::assertSame(['Government', 'Policy', 'Berlin'], $result->keywords);
        self::assertTrue($result->fromAi);
    }

    public function testHandlesMarkdownWrappedJson(): void
    {
        $json = "```json\n" . json_encode([
            'title' => 'Translated title for this article',
            'summary' => null,
            'keywords' => [],
        ], JSON_THROW_ON_ERROR) . "\n```";

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Original title here', null, [], 'de', 'en');

        self::assertSame('Translated title for this article', $result->title);
        self::assertNull($result->summary);
        self::assertTrue($result->fromAi);
    }

    public function testFallsBackOnJsonParseFailure(): void
    {
        $platform = new InMemoryPlatform('not json');

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::exactly(3))->method('translate')->willReturn('fallback text');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');
        $tracker->expects(self::never())->method('recordAcceptance');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                'Batch translation JSON parse failed',
                self::callback(static function (array $context): bool {
                    return isset($context['response'], $context['model'])
                        && is_string($context['response'])
                        && is_string($context['model']);
                }),
            );
        // Must NOT trigger the exception catch path (no warning logged)
        $logger->expects(self::never())->method('warning');

        $service = $this->createService($platform, $fallback, $tracker, $logger);

        $result = $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testFallsBackOnPlatformException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                'Batch translation failed: {error}',
                self::callback(static function (array $context): bool {
                    return $context['error'] === 'API down'
                        && $context['from'] === 'de'
                        && $context['to'] === 'en'
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $service = $this->createService($platform, $fallback, logger: $logger);

        $result = $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testFallsBackOnTitleTooSimilar(): void
    {
        $json = json_encode([
            'title' => 'Same title as original',
            'summary' => 'Translated summary text here.',
            'keywords' => ['KW'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Batch translation title too similar or empty');

        $service = $this->createService($platform, $fallback, $tracker, $logger);

        $result = $service->translateBatch('Same title as original', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testAllOrNothingOnMissingSummary(): void
    {
        $json = json_encode([
            'title' => 'Translated title for this article',
            'keywords' => ['KW'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title here', 'Original summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testSkipsTranslationForSameLanguage(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('Should not be called'));

        $service = $this->createService($platform);

        $result = $service->translateBatch('Title', 'Summary', ['KW'], 'en', 'en');

        self::assertSame('Title', $result->title);
        self::assertSame('Summary', $result->summary);
        self::assertSame(['KW'], $result->keywords);
        self::assertFalse($result->fromAi);
    }

    public function testNullSummaryAcceptedWhenOriginalIsNull(): void
    {
        $json = json_encode([
            'title' => 'Completely different translated title',
            'summary' => null,
            'keywords' => [],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Original title here', null, [], 'de', 'en');

        self::assertSame('Completely different translated title', $result->title);
        self::assertNull($result->summary);
        self::assertTrue($result->fromAi);
    }

    public function testFallbackTranslatesKeywordsViaConcatenation(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('fail'));

        $calls = [];
        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->method('translate')->willReturnCallback(
            static function (string $text) use (&$calls): string {
                $calls[] = $text;

                return 'translated: ' . $text;
            },
        );

        $service = $this->createService($platform, $fallback);

        $result = $service->translateBatch('Title', 'Summary', ['KW1', 'KW2'], 'de', 'en');

        self::assertCount(3, $calls);
        self::assertSame('Title', $calls[0]);
        self::assertSame('Summary', $calls[1]);
        self::assertSame('KW1, KW2', $calls[2]);
        self::assertFalse($result->fromAi);
    }

    public function testFallbackKeywordsAreTrimmedAfterSplit(): void
    {
        // Mutation #25: UnwrapArrayMap — tests that array_map('trim', ...) matters
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('fail'));

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->method('translate')->willReturnCallback(
            static function (string $text): string {
                if ($text === 'KW1, KW2') {
                    return ' Translated1 , Translated2 ';
                }

                return 'translated';
            },
        );

        $service = $this->createService($platform, $fallback);

        $result = $service->translateBatch('Title', 'Summary', ['KW1', 'KW2'], 'de', 'en');

        // Without array_map('trim', ...) keywords would have spaces
        self::assertSame(['Translated1', 'Translated2'], $result->keywords);
    }

    public function testNullSummaryUsesLiteralNullInPrompt(): void
    {
        // Mutation #12: Coalesce — `$summary ?? 'null'` vs `'null' ?? $summary`
        // Use closure to inspect the prompt and verify 'null' literal is present
        $capturedPrompt = '';
        $json = json_encode([
            'title' => 'A completely new translated title here',
            'summary' => null,
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

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Original title', null, ['KW'], 'de', 'en');

        self::assertTrue($result->fromAi);
        self::assertNull($result->summary);
        \assert(is_string($capturedPrompt));
        // Verify the prompt contains 'null' as the summary placeholder
        self::assertStringContainsString('- summary: null', $capturedPrompt);
    }

    public function testNonNullSummaryUsedInPrompt(): void
    {
        // Complementary to null test — verify actual summary text appears in prompt
        $capturedPrompt = '';
        $json = json_encode([
            'title' => 'A completely new translated title here',
            'summary' => 'Translated summary content.',
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

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Original title', 'Real summary text', ['KW'], 'de', 'en');

        self::assertTrue($result->fromAi);
        self::assertSame('Translated summary content.', $result->summary);
        \assert(is_string($capturedPrompt));
        // Verify the prompt contains the actual summary, not 'null'
        self::assertStringContainsString('- summary: Real summary text', $capturedPrompt);
    }

    public function testTrimIsAppliedToResponseAndFields(): void
    {
        // Mutation #13: UnwrapTrim on rawText
        // Mutation #19: UnwrapTrim on keyword check
        // Response with surrounding whitespace — trim must strip it for JSON parsing
        $json = json_encode([
            'title' => '  Translated title with spaces  ',
            'summary' => '  Translated summary with spaces  ',
            'keywords' => ['  Keyword  '],
        ], JSON_THROW_ON_ERROR);

        // Add whitespace around the JSON to test trim on rawText
        $platform = new InMemoryPlatform("  \n" . $json . "\n  ");

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Originaltitel', 'Originale Zusammenfassung', ['Schlüsselwort'], 'de', 'en');

        self::assertSame('Translated title with spaces', $result->title);
        self::assertSame('Translated summary with spaces', $result->summary);
        self::assertSame(['Keyword'], $result->keywords);
        self::assertTrue($result->fromAi);
    }

    public function testWhitespaceOnlyKeywordCausesRejection(): void
    {
        // Mutation #19-20: Tests that trim($kw) === '' catches whitespace-only keywords
        // and that the || operator matters (not &&)
        $json = json_encode([
            'title' => 'A completely different translated title',
            'summary' => 'A translated summary text here.',
            'keywords' => ['  '],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title', 'Summary', ['KW'], 'de', 'en');

        // Whitespace-only keyword should cause null return from extractKeywords, triggering fallback
        self::assertFalse($result->fromAi);
    }

    public function testNonStringKeywordCausesRejection(): void
    {
        // Mutation #20: Tests that the || in `!is_string($kw) || trim($kw) === ''`
        // matters: non-string keywords should cause rejection
        $response = '{"title": "Translated title for article", "summary": "Translated summary.", "keywords": [123]}';

        $platform = new InMemoryPlatform($response);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testMbStrtolowerOnOriginalForSimilarityCheck(): void
    {
        // Mutation #16: strtolower on $original in isTooSimilar
        // 'ÄRGER ÜBER ÖFFENTLICHE ÄMTER' — strtolower can't lowercase Ä,Ü,Ö
        // With mb_strtolower both sides: 100% similar → rejected (correct)
        // With strtolower on original: 87.5% similar → accepted (wrong → mutation killed)
        $json = json_encode([
            'title' => 'Ärger über öffentliche Ämter',
            'summary' => 'Translated summary text here.',
            'keywords' => ['KW'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('ÄRGER ÜBER ÖFFENTLICHE ÄMTER', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testMbStrtolowerOnTranslatedForSimilarityCheck(): void
    {
        // Mutation #17: strtolower on $translated in isTooSimilar
        // Reversed: original is lowercase, translated is uppercase with multibyte
        // With mb_strtolower both sides: 100% similar → rejected (correct)
        // With strtolower on translated: 87.5% similar → accepted (wrong → mutation killed)
        $json = json_encode([
            'title' => 'ÄRGER ÜBER ÖFFENTLICHE ÄMTER',
            'summary' => 'Translated summary text here.',
            'keywords' => ['KW'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Ärger über öffentliche Ämter', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testSimilarityThresholdBoundaryExactly90Percent(): void
    {
        // Mutation #18: GreaterThan to GreaterThanOrEqual
        // similar_text('abcdefghij', 'abcdefghiX') = exactly 90%
        // With > 90: NOT too similar → accepted (correct)
        // With >= 90: too similar → rejected (wrong → mutation killed)
        $json = json_encode([
            'title' => 'abcdefghiX',
            'summary' => 'A translated summary for the article.',
            'keywords' => ['KW'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('abcdefghij', 'Summary', ['KW'], 'de', 'en');

        // Exactly 90% similar — should be accepted (> 90 is threshold, not >=)
        self::assertTrue($result->fromAi);
    }

    public function testTitleNotSetInResponseCausesRejection(): void
    {
        // Mutation #16: LogicalOr on `! isset($decoded['title']) || ! is_string($decoded['title'])`
        $json = json_encode([
            'summary' => 'Translated summary text here.',
            'keywords' => ['KW'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testTitleNotStringInResponseCausesRejection(): void
    {
        // Mutation #16: Tests the second part of `|| ! is_string($decoded['title'])`
        $response = '{"title": 123, "summary": "Translated.", "keywords": ["KW"]}';

        $platform = new InMemoryPlatform($response);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testEmptyOriginalKeywordsReturnsEmptyKeywords(): void
    {
        // Mutation #15: ReturnRemoval of `return []` when originalKeywords is empty
        // Response intentionally has keywords, but since original had none, result must be []
        // Without the early return, extractKeywords would try to extract from response
        $json = json_encode([
            'title' => 'Completely different translated title',
            'summary' => 'Translated summary text for the article.',
            'keywords' => ['ShouldBeIgnored', 'AlsoIgnored'],
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Original title here', 'Original summary text', [], 'de', 'en');

        // Must be empty — original had no keywords, so translated must have none too
        self::assertSame([], $result->keywords);
        self::assertTrue($result->fromAi);
    }

    public function testKeywordsNotSetInResponseCausesRejection(): void
    {
        // Mutation #18: LogicalOr on `! isset($decoded['keywords']) || ! is_array($decoded['keywords'])`
        $json = json_encode([
            'title' => 'Completely different translated title',
            'summary' => 'Translated summary text here.',
        ], JSON_THROW_ON_ERROR);

        $platform = new InMemoryPlatform($json);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testKeywordsNotArrayInResponseCausesRejection(): void
    {
        // Mutation #18: Tests second part of || — keywords is set but not an array
        $response = '{"title": "Completely different translated title", "summary": "Translated.", "keywords": "not-array"}';

        $platform = new InMemoryPlatform($response);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $service = $this->createService($platform, $fallback, $tracker);

        $result = $service->translateBatch('Original title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testLoggerInfoIncludesResponseAndModelOnJsonFailure(): void
    {
        $platform = new InMemoryPlatform('invalid json response here');

        $fallback = $this->createStub(TranslationServiceInterface::class);
        $fallback->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                'Batch translation JSON parse failed',
                self::callback(static function (array $context): bool {
                    return array_key_exists('response', $context)
                        && array_key_exists('model', $context)
                        && is_string($context['response'])
                        && str_contains($context['response'], 'invalid json');
                }),
            );

        $service = $this->createService($platform, $fallback, $tracker, $logger);

        $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');
    }

    public function testMbSubstrTruncatesLongResponseInLog(): void
    {
        // Mutation #14: MBString — mb_substr vs substr for truncation
        // Use multibyte characters to make mb_substr and substr differ
        $longResponse = str_repeat('ä', 300);
        $platform = new InMemoryPlatform($longResponse);

        $fallback = $this->createStub(TranslationServiceInterface::class);
        $fallback->method('translate')->willReturn('fallback');

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::anything(),
                self::callback(static function (array $context): bool {
                    // mb_substr(str_repeat('ä', 300), 0, 200) = 200 'ä' chars (200 mb_strlen)
                    // substr(str_repeat('ä', 300), 0, 200) = 100 'ä' chars (truncates mid-byte)
                    return is_string($context['response'])
                        && mb_strlen($context['response']) === 200;
                }),
            );

        $service = $this->createService($platform, $fallback, $tracker, $logger);

        $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');
    }

    public function testJsonParseFailureReturnsTriggersFallback(): void
    {
        // Mutation #15: ReturnRemoval of `return null` after JSON parse failure
        // Without the return, code would continue to validateAndBuild with null $decoded
        $platform = new InMemoryPlatform('not json');

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::exactly(3))->method('translate')->willReturn('fb');

        $service = $this->createService($platform, $fallback);

        $result = $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');

        // Must fall back — the null return after JSON failure is essential
        self::assertFalse($result->fromAi);
        self::assertSame('fb', $result->title);
    }

    public function testLoggerWarningIncludesAllContextKeysOnException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('Connection timeout'));

        $fallback = $this->createStub(TranslationServiceInterface::class);
        $fallback->method('translate')->willReturn('fallback');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::anything(),
                self::callback(static function (array $context): bool {
                    return array_key_exists('error', $context)
                        && array_key_exists('from', $context)
                        && array_key_exists('to', $context)
                        && array_key_exists('model', $context);
                }),
            );

        $service = $this->createService($platform, $fallback, logger: $logger);

        $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');
    }

    public function testParseJsonTrimsBeforeParsing(): void
    {
        // Mutation #24: UnwrapTrim in parseJson — whitespace around JSON must be stripped
        $json = json_encode([
            'title' => 'Completely different translated title',
            'summary' => 'Translated summary of the article.',
            'keywords' => ['Keyword'],
        ], JSON_THROW_ON_ERROR);

        // Add whitespace that only trim would handle (not stripped by regex)
        $platform = new InMemoryPlatform("\t  " . $json . "  \t");

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance');

        $service = $this->createService($platform, tracker: $tracker);

        $result = $service->translateBatch('Original title here', 'Summary text', ['KW'], 'de', 'en');

        self::assertTrue($result->fromAi);
    }

    private function createService(
        PlatformInterface $platform,
        ?TranslationServiceInterface $fallback = null,
        ?ModelQualityTrackerInterface $tracker = null,
        ?LoggerInterface $logger = null,
    ): AiBatchTranslationService {
        return new AiBatchTranslationService(
            $platform,
            $fallback ?? $this->createStub(TranslationServiceInterface::class),
            new AiTextCleanupService(),
            $tracker ?? $this->createStub(ModelQualityTrackerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
