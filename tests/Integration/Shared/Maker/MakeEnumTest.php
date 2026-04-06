<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;

#[CoversNothing]
final class MakeEnumTest extends AbstractMakerTestCase
{
    private const string COMMAND = 'make:enum';

    public function testGeneratesStringBackedEnum(): void
    {
        $this->trackGeneratedFiles(['src/MakerTest/Enum/Priority.php']);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/Priority');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $content = $this->assertFileWasGenerated('src/MakerTest/Enum/Priority.php');

        $this->assertPhpFileConventions($content, 'App\MakerTest\Enum', 'enum Priority: string');
        self::assertStringContainsString("case Example = 'example'", $content);

        $this->assertValidPhpSyntax('src/MakerTest/Enum/Priority.php');
    }
}
