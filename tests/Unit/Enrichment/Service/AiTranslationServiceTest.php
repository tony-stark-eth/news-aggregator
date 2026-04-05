<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiTranslationService;
use App\Enrichment\Service\RuleBasedTranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

#[CoversClass(AiTranslationService::class)]
final class AiTranslationServiceTest extends TestCase
{
    public function testTranslatesSuccessfully(): void
    {
        $translated = 'Federal government announces new measures';

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult($translated));

        $service = new AiTranslationService(
            $platform,
            new RuleBasedTranslationService(),
            new NullLogger(),
        );

        $result = $service->translate('Bundesregierung beschließt neue Maßnahmen', 'de', 'en');

        self::assertSame($translated, $result);
    }

    public function testFallsBackOnFailure(): void
    {
        $original = 'Bundesregierung beschließt neue Maßnahmen';

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API timeout'));

        $service = new AiTranslationService(
            $platform,
            new RuleBasedTranslationService(),
            new NullLogger(),
        );

        $result = $service->translate($original, 'de', 'en');

        self::assertSame($original, $result);
    }

    public function testFallsBackOnEmptyResponse(): void
    {
        $original = 'Bundesregierung beschließt neue Maßnahmen';

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult(''));

        $service = new AiTranslationService(
            $platform,
            new RuleBasedTranslationService(),
            new NullLogger(),
        );

        $result = $service->translate($original, 'de', 'en');

        self::assertSame($original, $result);
    }

    public function testFallsBackOnTooSimilarResponse(): void
    {
        $original = 'Some text that stays the same';

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($this->makeDeferredResult($original));

        $service = new AiTranslationService(
            $platform,
            new RuleBasedTranslationService(),
            new NullLogger(),
        );

        $result = $service->translate($original, 'de', 'en');

        self::assertSame($original, $result);
    }

    public function testSkipsTranslationForSameLanguage(): void
    {
        $original = 'English text';

        $platform = $this->createStub(PlatformInterface::class);
        // Platform should NOT be called when languages match
        $platform->method('invoke')->willThrowException(new \RuntimeException('Should not be called'));

        $service = new AiTranslationService(
            $platform,
            new RuleBasedTranslationService(),
            new NullLogger(),
        );

        $result = $service->translate($original, 'en', 'en');

        self::assertSame($original, $result);
    }

    private function makeDeferredResult(string $text): DeferredResult
    {
        $textResult = new TextResult($text);

        $rawResult = $this->createStub(RawResultInterface::class);

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);
        $converter->method('getTokenUsageExtractor')->willReturn(null);

        return new DeferredResult($converter, $rawResult);
    }
}
