<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Entity\Setting;
use App\Shared\Repository\SettingRepositoryInterface;
use App\Shared\ValueObject\AiProvider;

final readonly class SettingsService implements SettingsServiceInterface
{
    public const string KEY_DISPLAY_LANGUAGES = 'display_languages';

    public const string KEY_FETCH_DEFAULT_INTERVAL = 'fetch_default_interval';

    public const string KEY_RETENTION_ARTICLES = 'retention_articles';

    public const string KEY_RETENTION_LOGS = 'retention_logs';

    public const string KEY_SENTIMENT_SLIDER = 'sentiment_slider';

    public const string KEY_AI_PROVIDER = 'ai_provider';

    public const string KEY_AI_OPENAI_BASE_URL = 'ai_openai_base_url';

    public const string KEY_AI_OPENAI_API_KEY = 'ai_openai_api_key';

    public const string KEY_AI_OPENAI_MODEL = 'ai_openai_model';

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
        string $defaultAiProvider = 'openrouter',
        string $defaultAiOpenAiBaseUrl = '',
        string $defaultAiOpenAiApiKey = '',
        string $defaultAiOpenAiModel = '',
    ) {
        $defaults = [
            self::KEY_DISPLAY_LANGUAGES => $displayLanguages,
            self::KEY_FETCH_DEFAULT_INTERVAL => (string) $fetchDefaultInterval,
            self::KEY_RETENTION_ARTICLES => (string) $retentionArticles,
            self::KEY_RETENTION_LOGS => (string) $retentionLogs,
            self::KEY_SENTIMENT_SLIDER => '0',
            self::KEY_AI_PROVIDER => $defaultAiProvider,
            self::KEY_AI_OPENAI_BASE_URL => $defaultAiOpenAiBaseUrl,
            self::KEY_AI_OPENAI_MODEL => $defaultAiOpenAiModel,
        ];

        if ($defaultAiOpenAiApiKey !== '') {
            $defaults[self::KEY_AI_OPENAI_API_KEY] = $defaultAiOpenAiApiKey;
        }

        $this->defaults = $defaults;
    }

    public function get(string $key): string
    {
        $setting = $this->settingRepository->findByKey($key);

        if ($setting instanceof Setting) {
            return $setting->getValue();
        }

        return $this->defaults[$key] ?? '';
    }

    public function hasDefault(string $key): bool
    {
        return \array_key_exists($key, $this->defaults);
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

        if (isset($dbSettings[self::KEY_AI_OPENAI_API_KEY])) {
            $result[self::KEY_AI_OPENAI_API_KEY] = [
                'value' => '',
                'isOverridden' => true,
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

    public function getAiProvider(): AiProvider
    {
        return AiProvider::fromString($this->get(self::KEY_AI_PROVIDER));
    }

    public function getOpenAiBaseUrl(): string
    {
        return trim($this->get(self::KEY_AI_OPENAI_BASE_URL));
    }

    public function getOpenAiModel(): string
    {
        return trim($this->get(self::KEY_AI_OPENAI_MODEL));
    }

    public function getOpenAiApiKey(): string
    {
        $setting = $this->settingRepository->findByKey(self::KEY_AI_OPENAI_API_KEY);

        if ($setting instanceof Setting) {
            return $setting->getValue();
        }

        return $this->defaults[self::KEY_AI_OPENAI_API_KEY] ?? '';
    }

    public function hasOpenAiApiKey(): bool
    {
        return $this->getOpenAiApiKey() !== '';
    }

    public function isOpenAiConfigured(): bool
    {
        return $this->getOpenAiBaseUrl() !== ''
            && $this->getOpenAiModel() !== ''
            && $this->hasOpenAiApiKey();
    }

    public function isAiConfigured(string $openrouterApiKey): bool
    {
        return match ($this->getAiProvider()) {
            AiProvider::OpenAi => $this->isOpenAiConfigured(),
            AiProvider::OpenRouter => $openrouterApiKey !== '',
        };
    }
}
