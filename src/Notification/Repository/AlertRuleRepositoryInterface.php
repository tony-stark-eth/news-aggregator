<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\AlertRule;
use App\User\Entity\User;

interface AlertRuleRepositoryInterface
{
    public function findById(int $id): ?AlertRule;

    /**
     * @return list<AlertRule>
     */
    public function findAll(): array;

    /**
     * @return list<AlertRule>
     */
    public function findEnabled(): array;

    public function findByNameAndUser(string $name, User $user): ?AlertRule;

    /**
     * @return list<AlertRule>
     */
    public function findByUser(User $user): array;

    public function save(AlertRule $alertRule, bool $flush = false): void;

    public function remove(AlertRule $alertRule, bool $flush = false): void;

    public function flush(): void;
}
