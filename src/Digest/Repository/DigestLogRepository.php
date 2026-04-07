<?php

declare(strict_types=1);

namespace App\Digest\Repository;

use App\Digest\Entity\DigestLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DigestLog>
 */
final class DigestLogRepository extends ServiceEntityRepository implements DigestLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DigestLog::class);
    }

    public function findById(int $id): ?DigestLog
    {
        return $this->find($id);
    }

    /**
     * @return list<DigestLog>
     */
    public function findRecent(int $limit, ?bool $deliverySuccess = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.generatedAt', 'DESC')
            ->setMaxResults($limit);

        if ($deliverySuccess !== null) {
            $qb->andWhere('l.deliverySuccess = :success')
                ->setParameter('success', $deliverySuccess);
        }

        /** @var list<DigestLog> */
        return $qb->getQuery()->getResult();
    }

    public function save(DigestLog $log, bool $flush = false): void
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
