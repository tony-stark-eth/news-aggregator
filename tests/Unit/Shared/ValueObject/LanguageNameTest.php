<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\ValueObject;

use App\Shared\ValueObject\LanguageName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageName::class)]
final class LanguageNameTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testLabelReturnsHumanReadableName(LanguageName $case, string $expected): void
    {
        self::assertSame($expected, $case->label());
    }

    /**
     * @return iterable<string, array{LanguageName, string}>
     */
    public static function labelProvider(): iterable
    {
        yield 'English' => [LanguageName::English, 'English'];
        yield 'German' => [LanguageName::German, 'German'];
        yield 'French' => [LanguageName::French, 'French'];
        yield 'Spanish' => [LanguageName::Spanish, 'Spanish'];
    }

    #[DataProvider('labelForProvider')]
    public function testLabelForResolvesCodeToName(string $code, string $expected): void
    {
        self::assertSame($expected, LanguageName::labelFor($code));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function labelForProvider(): iterable
    {
        yield 'en -> English' => ['en', 'English'];
        yield 'de -> German' => ['de', 'German'];
        yield 'fr -> French' => ['fr', 'French'];
        yield 'es -> Spanish' => ['es', 'Spanish'];
    }

    public function testLabelForReturnsCodeForUnknownLanguage(): void
    {
        self::assertSame('zh', LanguageName::labelFor('zh'));
        self::assertSame('ja', LanguageName::labelFor('ja'));
    }

    public function testAllCasesHaveLabels(): void
    {
        foreach (LanguageName::cases() as $case) {
            self::assertNotSame('', $case->label());
        }
    }
}
