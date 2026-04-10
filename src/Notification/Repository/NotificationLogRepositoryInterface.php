<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\NotificationLog;
use App\Notification\ValueObject\DeliveryStatus;

interface NotificationLogRepositoryInterface
{
    /**
     * @return list<NotificationLog>
     */
    public function findRecent(int $limit, ?int $alertRuleId = null, ?DeliveryStatus $status = null): array;

    public function existsRecentForRule(int $ruleId, \DateTimeImmutable $since): bool;

    public function save(NotificationLog $log, bool $flush = false): void;

    public function flush(): void;

    /**
     * @return array<int, array{count: int, lastTriggeredAt: \DateTimeImmutable|null}>
     */
    public function getMatchStatsByAlertRule(): array;

    public function countSentSince(\DateTimeImmutable $since): int;
}
