<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\ValueObject\AiProvider;

interface SettingsServiceInterface
{
    public function get(string $key): string;

    public function hasDefault(string $key): bool;

    public function set(string $key, string $value): void;

    /**
     * @return array<string, array{value: string, isOverridden: bool}>
     */
    public function getAll(): array;

    public function getDisplayLanguages(): string;

    public function getFetchDefaultInterval(): int;

    public function getRetentionArticles(): int;

    public function getRetentionLogs(): int;

    public function getSentimentSlider(): int;

    public function getAiProvider(): AiProvider;

    public function getOpenAiBaseUrl(): string;

    public function getOpenAiModel(): string;

    public function getOpenAiApiKey(): string;

    public function hasOpenAiApiKey(): bool;

    public function isOpenAiConfigured(): bool;

    public function isAiConfigured(string $openrouterApiKey): bool;
}
