<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Entity\Setting;
use App\Shared\Repository\SettingRepositoryInterface;

final readonly class SettingsService implements SettingsServiceInterface
{
    public const string KEY_DISPLAY_LANGUAGES = 'display_languages';

    public const string KEY_FETCH_DEFAULT_INTERVAL = 'fetch_default_interval';

    public const string KEY_RETENTION_ARTICLES = 'retention_articles';

    public const string KEY_RETENTION_LOGS = 'retention_logs';

    public const string KEY_SENTIMENT_SLIDER = 'sentiment_slider';

    /**
     * @var array<string, string>
     */
    private array $defaults;

    public function __construct(
        private SettingRepositoryInterface $settingRepository,
        string $displayLanguages,
        int $fetchDefaultInterval,
        int $retentionArticles,
        int $retentionLogs,
    ) {
        $this->defaults = [
            self::KEY_DISPLAY_LANGUAGES => $displayLanguages,
            self::KEY_FETCH_DEFAULT_INTERVAL => (string) $fetchDefaultInterval,
            self::KEY_RETENTION_ARTICLES => (string) $retentionArticles,
            self::KEY_RETENTION_LOGS => (string) $retentionLogs,
            self::KEY_SENTIMENT_SLIDER => '0',
        ];
    }

    public function get(string $key): string
    {
        $setting = $this->settingRepository->findByKey($key);

        if ($setting instanceof Setting) {
            return $setting->getValue();
        }

        return $this->defaults[$key] ?? '';
    }

    public function set(string $key, string $value): void
    {
        $setting = $this->settingRepository->findByKey($key);

        if ($setting instanceof Setting) {
            $setting->setValue($value);
        } else {
            $setting = new Setting($key, $value);
        }

        $this->settingRepository->save($setting, true);
    }

    /**
     * @return array<string, array{value: string, isOverridden: bool}>
     */
    public function getAll(): array
    {
        $result = [];

        $dbSettings = [];
        foreach ($this->settingRepository->findAll() as $setting) {
            $dbSettings[$setting->getKey()] = $setting->getValue();
        }

        foreach ($this->defaults as $key => $default) {
            $isOverridden = isset($dbSettings[$key]);
            $result[$key] = [
                'value' => $dbSettings[$key] ?? $default,
                'isOverridden' => $isOverridden,
            ];
        }

        return $result;
    }

    public function getDisplayLanguages(): string
    {
        return $this->get(self::KEY_DISPLAY_LANGUAGES);
    }

    public function getFetchDefaultInterval(): int
    {
        return (int) $this->get(self::KEY_FETCH_DEFAULT_INTERVAL);
    }

    public function getRetentionArticles(): int
    {
        return (int) $this->get(self::KEY_RETENTION_ARTICLES);
    }

    public function getRetentionLogs(): int
    {
        return (int) $this->get(self::KEY_RETENTION_LOGS);
    }

    public function getSentimentSlider(): int
    {
        return (int) $this->get(self::KEY_SENTIMENT_SLIDER);
    }
}
