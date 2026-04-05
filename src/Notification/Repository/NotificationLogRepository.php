<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\NotificationLog;
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
    public function findRecent(int $limit): array
    {
        /** @var list<NotificationLog> */
        return $this->createQueryBuilder('l')
            ->orderBy('l.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
}
