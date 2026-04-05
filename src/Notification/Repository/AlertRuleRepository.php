<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\AlertRule;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlertRule>
 */
final class AlertRuleRepository extends ServiceEntityRepository implements AlertRuleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertRule::class);
    }

    public function findById(int $id): ?AlertRule
    {
        return $this->find($id);
    }

    /**
     * @return list<AlertRule>
     */
    public function findAll(): array
    {
        /** @var list<AlertRule> */
        return parent::findAll();
    }

    /**
     * @return list<AlertRule>
     */
    public function findEnabled(): array
    {
        /** @var list<AlertRule> */
        return $this->findBy([
            'enabled' => true,
        ]);
    }

    public function findByNameAndUser(string $name, User $user): ?AlertRule
    {
        return $this->findOneBy([
            'name' => $name,
            'user' => $user,
        ]);
    }

    /**
     * @return list<AlertRule>
     */
    public function findByUser(User $user): array
    {
        /** @var list<AlertRule> */
        return $this->findBy([
            'user' => $user,
        ]);
    }

    public function save(AlertRule $alertRule, bool $flush = false): void
    {
        $this->getEntityManager()->persist($alertRule);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AlertRule $alertRule, bool $flush = false): void
    {
        $this->getEntityManager()->remove($alertRule);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
