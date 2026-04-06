<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;

#[CoversNothing]
final class MakeValueObjectTest extends AbstractMakerTestCase
{
    private const string COMMAND = 'make:value-object';

    public function testGeneratesValueObjectFile(): void
    {
        $this->trackGeneratedFiles(['src/MakerTest/ValueObject/Price.php']);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/Price');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $content = $this->assertFileWasGenerated('src/MakerTest/ValueObject/Price.php');

        $this->assertPhpFileConventions($content, 'App\MakerTest\ValueObject', 'final readonly class Price');
        self::assertStringContainsString('implements \Stringable', $content);
        self::assertStringContainsString('public string $value', $content);
        self::assertStringContainsString('public function __construct(string $value)', $content);
        self::assertStringContainsString('throw new \InvalidArgumentException', $content);
        self::assertStringContainsString("'Price must not be empty.'", $content);
        self::assertStringContainsString('public function __toString(): string', $content);
        self::assertStringContainsString('public function equals(self $other): bool', $content);

        $this->assertValidPhpSyntax('src/MakerTest/ValueObject/Price.php');
    }

    public function testOutputContainsSuccessMessage(): void
    {
        $this->trackGeneratedFiles(['src/MakerTest/ValueObject/Token.php']);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/Token');
        $output = $tester->getDisplay();

        self::assertStringContainsString('Success', $output);
        self::assertStringContainsString('src/MakerTest/ValueObject/Token.php', $output);
    }

    public function testRejectsNameWithoutContext(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must contain a context prefix');

        $this->runMakerCommand(self::COMMAND, 'FlatName');
    }
}
