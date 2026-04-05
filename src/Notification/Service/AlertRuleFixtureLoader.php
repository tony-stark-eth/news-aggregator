<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Notification\Dto\AlertRuleFixture;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use Symfony\Component\Yaml\Yaml;

final class AlertRuleFixtureLoader implements AlertRuleFixtureLoaderInterface
{
    /**
     * @return list<AlertRuleFixture>
     */
    public function loadFromPath(string $path): array
    {
        if (is_dir($path)) {
            return $this->loadFromDirectory($path);
        }

        return $this->loadFromFile($path);
    }

    /**
     * @return list<AlertRuleFixture>
     */
    private function loadFromDirectory(string $directory): array
    {
        $fixtures = [];
        $globResult = glob($directory . '/*.yaml');
        $files = is_array($globResult) ? $globResult : [];
        sort($files);

        foreach ($files as $file) {
            $fixtures = [...$fixtures, ...$this->loadFromFile($file)];
        }

        return $fixtures;
    }

    /**
     * @return list<AlertRuleFixture>
     */
    private function loadFromFile(string $file): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $file));
        }

        $data = Yaml::parseFile($file);

        if (! is_array($data)) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not contain a valid YAML list.', $file));
        }

        /** @var list<array<string, mixed>> $data */
        return array_map($this->parseEntry(...), $data);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function parseEntry(array $entry): AlertRuleFixture
    {
        $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
        $type = is_string($entry['type'] ?? null) ? $entry['type'] : 'keyword';
        $urgency = is_string($entry['urgency'] ?? null) ? $entry['urgency'] : 'medium';
        $prompt = is_string($entry['context_prompt'] ?? null) ? $entry['context_prompt'] : null;
        $threshold = is_int($entry['severity_threshold'] ?? null) ? $entry['severity_threshold'] : 5;
        $cooldown = is_int($entry['cooldown_minutes'] ?? null) ? $entry['cooldown_minutes'] : 60;

        /** @var list<string> $keywords */
        $keywords = $entry['keywords'] ?? [];
        /** @var list<string> $categories */
        $categories = $entry['categories'] ?? [];

        return new AlertRuleFixture(
            name: $name,
            type: AlertRuleType::from($type),
            keywords: $keywords,
            contextPrompt: $prompt,
            urgency: AlertUrgency::from($urgency),
            severityThreshold: $threshold,
            cooldownMinutes: $cooldown,
            categories: $categories,
            enabled: (bool) ($entry['enabled'] ?? true),
        );
    }
}
