<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;

#[CoversNothing]
final class MakeDtoTest extends AbstractMakerTestCase
{
    private const string COMMAND = 'make:dto';

    public function testGeneratesReadonlyDto(): void
    {
        $this->trackGeneratedFiles(['src/MakerTest/Dto/ArticleInfoDto.php']);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/ArticleInfo');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $content = $this->assertFileWasGenerated('src/MakerTest/Dto/ArticleInfoDto.php');

        $this->assertPhpFileConventions($content, 'App\MakerTest\Dto', 'final readonly class ArticleInfoDto');
        self::assertStringContainsString('public function __construct()', $content);

        $this->assertValidPhpSyntax('src/MakerTest/Dto/ArticleInfoDto.php');
    }
}
