<?php

declare(strict_types=1);

namespace App\Shared\Service;

interface SettingsServiceInterface
{
    public function get(string $key): string;

    public function set(string $key, string $value): void;

    /**
     * @return array<string, array{value: string, isOverridden: bool}>
     */
    public function getAll(): array;

    public function getDisplayLanguages(): string;

    public function getFetchDefaultInterval(): int;

    public function getRetentionArticles(): int;

    public function getRetentionLogs(): int;
}
