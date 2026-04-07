<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\NotificationLog;

interface NotificationLogRepositoryInterface
{
    /**
     * @return list<NotificationLog>
     */
    public function findRecent(int $limit): array;

    public function existsRecentForRule(int $ruleId, \DateTimeImmutable $since): bool;

    public function save(NotificationLog $log, bool $flush = false): void;

    public function flush(): void;

    /**
     * @return array<int, array{count: int, lastTriggeredAt: \DateTimeImmutable|null}>
     */
    public function getMatchStatsByAlertRule(): array;

    public function countSentSince(\DateTimeImmutable $since): int;
}
