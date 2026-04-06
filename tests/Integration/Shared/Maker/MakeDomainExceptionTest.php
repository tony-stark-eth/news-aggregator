<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;

#[CoversNothing]
final class MakeDomainExceptionTest extends AbstractMakerTestCase
{
    private const string COMMAND = 'make:domain-exception';

    public function testGeneratesExceptionWithNamedConstructor(): void
    {
        $this->trackGeneratedFiles(['src/MakerTest/Exception/FeedFetchException.php']);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/FeedFetch');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $content = $this->assertFileWasGenerated('src/MakerTest/Exception/FeedFetchException.php');

        $this->assertPhpFileConventions($content, 'App\MakerTest\Exception', 'final class FeedFetchException');
        self::assertStringContainsString('extends \RuntimeException', $content);
        self::assertStringContainsString('public static function because(string $reason', $content);
        self::assertStringContainsString('?\Throwable $previous = null', $content);
        self::assertStringContainsString('return new self($reason, 0, $previous)', $content);

        $this->assertValidPhpSyntax('src/MakerTest/Exception/FeedFetchException.php');
    }
}
