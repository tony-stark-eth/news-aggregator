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
}
