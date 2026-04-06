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
use Psr\Log\NullLogger;
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
        $service = $this->createService($platform);

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
        $service = $this->createService($platform);

        $result = $service->translateBatch('Original title here', null, [], 'de', 'en');

        self::assertSame('Translated title for this article', $result->title);
        self::assertNull($result->summary);
        self::assertTrue($result->fromAi);
    }

    public function testFallsBackOnJsonParseFailure(): void
    {
        $platform = new InMemoryPlatform('not json');

        $fallback = $this->createMock(TranslationServiceInterface::class);
        // title + summary + keywords (as comma-separated string) = 3 calls
        $fallback->expects(self::exactly(3))->method('translate')->willReturn('fallback text');

        $service = $this->createService($platform, $fallback);

        $result = $service->translateBatch('Title', 'Summary', ['KW'], 'de', 'en');

        self::assertFalse($result->fromAi);
    }

    public function testFallsBackOnPlatformException(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::atLeastOnce())->method('translate')->willReturn('fallback');

        $service = $this->createService($platform, $fallback);

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

        $service = $this->createService($platform, $fallback);

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

        $service = $this->createService($platform, $fallback);

        // summary is non-null, so "summary" field is expected in response
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
        $service = $this->createService($platform);

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

        // Should translate title, summary, and keywords (as comma-separated string)
        self::assertCount(3, $calls);
        self::assertSame('Title', $calls[0]);
        self::assertSame('Summary', $calls[1]);
        self::assertSame('KW1, KW2', $calls[2]);
        self::assertFalse($result->fromAi);
    }

    private function createService(
        PlatformInterface $platform,
        ?TranslationServiceInterface $fallback = null,
    ): AiBatchTranslationService {
        return new AiBatchTranslationService(
            $platform,
            $fallback ?? $this->createStub(TranslationServiceInterface::class),
            new AiTextCleanupService(),
            $this->createStub(ModelQualityTrackerInterface::class),
            new NullLogger(),
        );
    }
}
