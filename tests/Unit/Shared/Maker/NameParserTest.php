<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Maker;

use App\Shared\Maker\NameParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;

#[CoversClass(NameParser::class)]
final class NameParserTest extends TestCase
{
    #[DataProvider('validNamesProvider')]
    public function testParsesValidNames(string $input, string $expectedContext, string $expectedName): void
    {
        [$context, $name] = NameParser::parse($input);

        self::assertSame($expectedContext, $context);
        self::assertSame($expectedName, $name);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function validNamesProvider(): iterable
    {
        yield 'simple' => ['Article/Url', 'Article', 'Url'];
        yield 'with spaces' => [' Source/FeedUrl ', 'Source', 'FeedUrl'];
        yield 'long context' => ['Notification/AlertUrgency', 'Notification', 'AlertUrgency'];
    }

    public function testRejectsNonStringInput(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('must be a string');

        NameParser::parse(123);
    }

    public function testRejectsNameWithoutSlash(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('must contain a context prefix');

        NameParser::parse('FlatName');
    }

    public function testRejectsEmptyContext(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('empty context or name');

        NameParser::parse('/Name');
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('empty context or name');

        NameParser::parse('Context/');
    }

    public function testTrimOnInputKillsUnwrapTrim(): void
    {
        // Without trim on line 25: "  NoSlash  " → str_contains("  NoSlash  ", "/") = false → throws
        // With trim on line 25: "NoSlash" → same result. Equivalent for no-slash case.
        // But for " / " (spaces around slash): without trim → str_contains(" / ", "/") = true
        // → parts = [" ", " "] → trim each → ["", ""] → empty context/name → throws.
        // With trim on input: "/" → str_contains("/", "/") = true
        // → parts = ["", ""] → trim each → ["", ""] → throws. Same result.
        // Real differentiator: "  Context/Name  " — line 25 trim ensures we parse clean.
        [$context, $name] = NameParser::parse('  Context/Name  ');

        self::assertSame('Context', $context);
        self::assertSame('Name', $name);
    }

    public function testTrimOnContextPartKillsUnwrapTrim(): void
    {
        // Input " Context / Name " after input trim → "Context / Name"
        // explode("/", ..., 2) → ["Context ", " Name"]
        // Without trim on parts[0] (line 34): context = "Context " (trailing space)
        // With trim on parts[0]: context = "Context"
        [$context, $name] = NameParser::parse(' Context / Name ');

        self::assertSame('Context', $context);
        self::assertSame('Name', $name);
    }

    public function testTrimOnNamePartKillsUnwrapTrim(): void
    {
        // explode("/", "Context / Name", 2) → ["Context ", " Name"]
        // Without trim on parts[1] (line 35): name = " Name" (leading space)
        // With trim on parts[1]: name = "Name"
        [$context, $name] = NameParser::parse('Context / Name');

        self::assertSame('Context', $context);
        self::assertSame('Name', $name);
    }
}
