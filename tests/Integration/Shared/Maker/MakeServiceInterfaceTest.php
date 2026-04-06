<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;

#[CoversNothing]
final class MakeServiceInterfaceTest extends AbstractMakerTestCase
{
    private const string COMMAND = 'make:service-interface';

    public function testGeneratesBothInterfaceAndImplementation(): void
    {
        $this->trackGeneratedFiles([
            'src/MakerTest/Service/ScoringServiceInterface.php',
            'src/MakerTest/Service/ScoringService.php',
        ]);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/Scoring');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Verify interface
        $interfaceContent = $this->assertFileWasGenerated('src/MakerTest/Service/ScoringServiceInterface.php');
        $this->assertPhpFileConventions($interfaceContent, 'App\MakerTest\Service', 'interface ScoringServiceInterface');
        $this->assertValidPhpSyntax('src/MakerTest/Service/ScoringServiceInterface.php');

        // Verify implementation
        $implContent = $this->assertFileWasGenerated('src/MakerTest/Service/ScoringService.php');
        $this->assertPhpFileConventions($implContent, 'App\MakerTest\Service', 'final readonly class ScoringService');
        self::assertStringContainsString('implements ScoringServiceInterface', $implContent);
        self::assertStringContainsString('public function __construct()', $implContent);
        $this->assertValidPhpSyntax('src/MakerTest/Service/ScoringService.php');
    }

    public function testOutputListsBothFiles(): void
    {
        $this->trackGeneratedFiles([
            'src/MakerTest/Service/FooServiceInterface.php',
            'src/MakerTest/Service/FooService.php',
        ]);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/Foo');
        $output = $tester->getDisplay();

        self::assertStringContainsString('FooServiceInterface.php', $output);
        self::assertStringContainsString('FooService.php', $output);
    }
}
