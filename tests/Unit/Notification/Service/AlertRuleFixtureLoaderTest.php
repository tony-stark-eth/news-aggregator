<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Service;

use App\Notification\Dto\AlertRuleFixture;
use App\Notification\Service\AlertRuleFixtureLoader;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(AlertRuleFixtureLoader::class)]
#[UsesClass(AlertRuleFixture::class)]
final class AlertRuleFixtureLoaderTest extends TestCase
{
    private AlertRuleFixtureLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AlertRuleFixtureLoader();
    }

    public function testLoadFromFileParsesYaml(): void
    {
        $file = $this->createTempFixture([
            [
                'name' => 'Test Rule',
                'type' => 'keyword',
                'keywords' => ['foo'],
                'urgency' => 'high',
                'severity_threshold' => 7,
                'cooldown_minutes' => 30,
                'categories' => ['tech'],
                'enabled' => true,
            ],
        ]);

        $fixtures = $this->loader->loadFromPath($file);

        self::assertCount(1, $fixtures);
        self::assertSame('Test Rule', $fixtures[0]->name);
        self::assertSame(AlertRuleType::Keyword, $fixtures[0]->type);
        self::assertSame(['foo'], $fixtures[0]->keywords);
        self::assertNull($fixtures[0]->contextPrompt);
        self::assertSame(AlertUrgency::High, $fixtures[0]->urgency);
        self::assertSame(7, $fixtures[0]->severityThreshold);
        self::assertSame(30, $fixtures[0]->cooldownMinutes);
        self::assertSame(['tech'], $fixtures[0]->categories);
        self::assertTrue($fixtures[0]->enabled);
    }

    public function testLoadFromFileWithContextPrompt(): void
    {
        $file = $this->createTempFixture([
            [
                'name' => 'AI Rule',
                'type' => 'ai',
                'keywords' => ['bar'],
                'context_prompt' => 'Evaluate this.',
                'urgency' => 'medium',
                'severity_threshold' => 5,
                'cooldown_minutes' => 60,
                'categories' => [],
                'enabled' => false,
            ],
        ]);

        $fixtures = $this->loader->loadFromPath($file);

        self::assertCount(1, $fixtures);
        self::assertSame(AlertRuleType::Ai, $fixtures[0]->type);
        self::assertSame('Evaluate this.', $fixtures[0]->contextPrompt);
        self::assertFalse($fixtures[0]->enabled);
    }

    public function testLoadFromDirectoryMergesFiles(): void
    {
        $dir = sys_get_temp_dir() . '/alert_fixtures_' . bin2hex(random_bytes(4));
        mkdir($dir);

        $this->writeYaml($dir . '/a.yaml', [
            [
                'name' => 'Rule A',
                'type' => 'keyword',
                'keywords' => ['a'],
            ],
        ]);
        $this->writeYaml($dir . '/b.yaml', [
            [
                'name' => 'Rule B',
                'type' => 'ai',
                'keywords' => ['b'],
            ],
        ]);

        $fixtures = $this->loader->loadFromPath($dir);

        self::assertCount(2, $fixtures);
        self::assertSame('Rule A', $fixtures[0]->name);
        self::assertSame('Rule B', $fixtures[1]->name);

        unlink($dir . '/a.yaml');
        unlink($dir . '/b.yaml');
        rmdir($dir);
    }

    public function testLoadFromNonExistentFileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $this->loader->loadFromPath('/nonexistent/file.yaml');
    }

    public function testLoadAppliesDefaults(): void
    {
        $file = $this->createTempFixture([
            [
                'name' => 'Minimal',
                'type' => 'keyword',
                'keywords' => ['test'],
            ],
        ]);

        $fixtures = $this->loader->loadFromPath($file);

        self::assertSame(AlertUrgency::Medium, $fixtures[0]->urgency);
        self::assertSame(5, $fixtures[0]->severityThreshold);
        self::assertSame(60, $fixtures[0]->cooldownMinutes);
        self::assertSame([], $fixtures[0]->categories);
        self::assertTrue($fixtures[0]->enabled);
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    private function createTempFixture(array $data): string
    {
        $file = tempnam(sys_get_temp_dir(), 'alert_fixture_') . '.yaml';
        $this->writeYaml($file, $data);

        return $file;
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    private function writeYaml(string $path, array $data): void
    {
        file_put_contents($path, Yaml::dump($data, 4));
    }
}
