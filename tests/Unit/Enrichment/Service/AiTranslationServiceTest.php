<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiTranslationService;
use App\Enrichment\Service\RuleBasedTranslationService;
use App\Enrichment\Service\TranslationServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiTranslationService::class)]
final class AiTranslationServiceTest extends TestCase
{
    public function testTranslatesSuccessfully(): void
    {
        $translated = 'Federal government announces new measures';
        $platform = new InMemoryPlatform($translated);

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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('AI translation failed'),
                self::callback(static function (array $context): bool {
                    return isset($context['error'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::once())->method('translate')
            ->with($original, 'de', 'en')
            ->willReturn($original);

        $service = new AiTranslationService(
            $platform,
            $fallback,
            $logger,
        );

        $result = $service->translate($original, 'de', 'en');

        self::assertSame($original, $result);
    }

    public function testFallsBackOnEmptyResponse(): void
    {
        $original = 'Bundesregierung beschließt neue Maßnahmen';
        $platform = new InMemoryPlatform('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('rejected'),
                self::callback(static function (array $context): bool {
                    return isset($context['original_length'])
                        && isset($context['translated_length'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::once())->method('translate')
            ->willReturn($original);

        $service = new AiTranslationService(
            $platform,
            $fallback,
            $logger,
        );

        $result = $service->translate($original, 'de', 'en');

        self::assertSame($original, $result);
    }

    public function testFallsBackOnTooSimilarResponse(): void
    {
        $original = 'Some text that stays the same';
        $platform = new InMemoryPlatform($original);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('rejected'),
                self::callback(static function (array $context): bool {
                    return isset($context['original_length'])
                        && isset($context['translated_length'])
                        && isset($context['model'])
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::once())->method('translate')
            ->willReturn($original);

        $service = new AiTranslationService(
            $platform,
            $fallback,
            $logger,
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

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::never())->method('translate');

        $service = new AiTranslationService(
            $platform,
            $fallback,
            new NullLogger(),
        );

        $result = $service->translate($original, 'en', 'en');

        self::assertSame($original, $result);
    }

    public function testAcceptsSufficientlyDifferentTranslation(): void
    {
        // German text -> very different English translation
        $original = 'Dies ist ein deutscher Text';
        $translated = 'This is a German text completely different';
        $platform = new InMemoryPlatform($translated);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::never())->method('translate');

        $service = new AiTranslationService(
            $platform,
            $fallback,
            new NullLogger(),
        );

        $result = $service->translate($original, 'de', 'en');

        self::assertSame($translated, $result);
    }

    public function testTrimsWhitespaceFromTranslation(): void
    {
        $platform = new InMemoryPlatform('  Translated text with whitespace  ');

        $service = new AiTranslationService(
            $platform,
            new RuleBasedTranslationService(),
            new NullLogger(),
        );

        $result = $service->translate('Original text that is different enough', 'de', 'en');

        self::assertSame('Translated text with whitespace', $result);
    }

    public function testMbStrtolowerBothSidesRequired(): void
    {
        $original = 'ÜBER DIE NACHRICHTEN HEUTE ABEND IM FERNSEHEN';
        $translated = 'ÜBER DIE NACHRICHTEN HEUTE ABEND IM FERNSEHEN';
        $platform = new InMemoryPlatform($translated);

        $fallback = $this->createMock(TranslationServiceInterface::class);
        $fallback->expects(self::once())->method('translate')->willReturn('fallback');

        $service = new AiTranslationService($platform, $fallback, new NullLogger());

        $result = $service->translate($original, 'de', 'en');

        self::assertSame('fallback', $result);
    }

    public function testMbStrlenTranslatedInLogRejection(): void
    {
        $platform = new InMemoryPlatform('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('rejected'),
                self::callback(static function (array $context): bool {
                    return $context['original_length'] === 18
                        && $context['translated_length'] === 0
                        && $context['model'] === 'openrouter/free';
                }),
            );

        $fallback = $this->createStub(TranslationServiceInterface::class);
        $fallback->method('translate')->willReturn('fallback');

        $service = new AiTranslationService($platform, $fallback, $logger);

        $service->translate('Original text here', 'de', 'en');
    }
}
