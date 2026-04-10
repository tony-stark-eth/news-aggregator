<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use App\Shared\Entity\Setting;

interface SettingRepositoryInterface
{
    public function findByKey(string $key): ?Setting;

    /**
     * @return list<Setting>
     */
    public function findAll(): array;

    public function save(Setting $setting, bool $flush = false): void;

    public function flush(): void;
}
