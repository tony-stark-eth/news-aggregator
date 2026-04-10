<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\NotificationLog;
use App\Notification\ValueObject\DeliveryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationLog>
 */
final class NotificationLogRepository extends ServiceEntityRepository implements NotificationLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationLog::class);
    }

    /**
     * @return list<NotificationLog>
     */
    public function findRecent(int $limit, ?int $alertRuleId = null, ?DeliveryStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.sentAt', 'DESC')
            ->setMaxResults($limit);

        if ($alertRuleId !== null) {
            $qb->andWhere('l.alertRule = :ruleId')
                ->setParameter('ruleId', $alertRuleId);
        }

        if ($status instanceof DeliveryStatus) {
            $qb->andWhere('l.deliveryStatus = :status')
                ->setParameter('status', $status);
        }

        /** @var list<NotificationLog> */
        return $qb->getQuery()->getResult();
    }

    public function existsRecentForRule(int $ruleId, \DateTimeImmutable $since): bool
    {
        return $this->createQueryBuilder('l')
            ->where('l.alertRule = :rule')
            ->andWhere('l.sentAt > :since')
            ->andWhere('l.success = true')
            ->setParameter('rule', $ruleId)
            ->setParameter('since', $since)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    public function save(NotificationLog $log, bool $flush = false): void
    {
        $this->getEntityManager()->persist($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * @return array<int, array{count: int, lastTriggeredAt: \DateTimeImmutable|null}>
     */
    public function getMatchStatsByAlertRule(): array
    {
        /** @var list<array{ruleId: string, matchCount: string, lastTriggeredAt: string|null}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.alertRule) AS ruleId')
            ->addSelect('COUNT(l.id) AS matchCount')
            ->addSelect('MAX(l.sentAt) AS lastTriggeredAt')
            ->groupBy('l.alertRule')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['ruleId']] = [
                'count' => (int) $row['matchCount'],
                'lastTriggeredAt' => $row['lastTriggeredAt'] !== null
                    ? new \DateTimeImmutable($row['lastTriggeredAt'])
                    : null,
            ];
        }

        return $stats;
    }

    public function countSentSince(\DateTimeImmutable $since): int
    {
        /** @var int */
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.deliveryStatus = :status')
            ->andWhere('l.sentAt >= :since')
            ->setParameter('status', DeliveryStatus::Sent)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
