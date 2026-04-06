<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversNothing]
abstract class AbstractMakerTestCase extends KernelTestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    /**
     * @var list<string>
     */
    private array $generatedFiles = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = \dirname(__DIR__, 4); // tests/Integration/Shared/Maker → project root
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            if ($this->filesystem->exists($file)) {
                $this->filesystem->remove($file);
            }
        }

        $this->cleanTestDirectories();

        parent::tearDown();
    }

    protected function runMakerCommand(string $commandName, string $argument): CommandTester
    {
        $kernel = self::bootKernel([
            'environment' => 'dev',
            'debug' => false,
        ]);
        $application = new Application($kernel);

        $command = $application->find($commandName);
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => $argument,
        ]);

        return $tester;
    }

    /**
     * @param list<string> $relativePaths
     */
    protected function trackGeneratedFiles(array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $this->generatedFiles[] = $this->projectDir . '/' . $relativePath;
        }
    }

    protected function assertFileWasGenerated(string $relativePath): string
    {
        $fullPath = $this->projectDir . '/' . $relativePath;

        self::assertFileExists($fullPath, sprintf('Expected file "%s" was not generated.', $relativePath));

        $content = file_get_contents($fullPath);
        self::assertIsString($content);

        return $content;
    }

    protected function assertValidPhpSyntax(string $relativePath): void
    {
        $fullPath = $this->projectDir . '/' . $relativePath;
        $output = [];
        $exitCode = 0;

        exec(sprintf('php -l %s 2>&1', escapeshellarg($fullPath)), $output, $exitCode);

        self::assertSame(0, $exitCode, sprintf(
            'PHP syntax error in "%s": %s',
            $relativePath,
            implode("\n", $output),
        ));
    }

    protected function assertPhpFileConventions(string $content, string $expectedNamespace, string $expectedClassOrEnum): void
    {
        self::assertStringContainsString('<?php', $content, 'Missing PHP open tag');
        self::assertStringContainsString('declare(strict_types=1)', $content, 'Missing declare(strict_types=1)');
        self::assertStringContainsString(
            sprintf('namespace %s', $expectedNamespace),
            $content,
            sprintf('Wrong namespace. Expected: %s', $expectedNamespace),
        );
        self::assertStringContainsString($expectedClassOrEnum, $content, sprintf('Missing: %s', $expectedClassOrEnum));
    }

    private function cleanTestDirectories(): void
    {
        $testContextDir = $this->projectDir . '/src/MakerTest';
        if ($this->filesystem->exists($testContextDir)) {
            $this->filesystem->remove($testContextDir);
        }
    }
}
