<?php

declare(strict_types=1);

namespace App\Digest\Repository;

use App\Digest\Entity\DigestConfig;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DigestConfig>
 */
final class DigestConfigRepository extends ServiceEntityRepository implements DigestConfigRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DigestConfig::class);
    }

    public function findById(int $id): ?DigestConfig
    {
        return $this->find($id);
    }

    /**
     * @return list<DigestConfig>
     */
    public function findAll(): array
    {
        /** @var list<DigestConfig> */
        return parent::findAll();
    }

    /**
     * @return list<DigestConfig>
     */
    public function findEnabled(): array
    {
        /** @var list<DigestConfig> */
        return $this->findBy([
            'enabled' => true,
        ]);
    }

    public function findByNameAndUser(string $name, User $user): ?DigestConfig
    {
        return $this->findOneBy([
            'name' => $name,
            'user' => $user,
        ]);
    }

    public function save(DigestConfig $config, bool $flush = false): void
    {
        $this->getEntityManager()->persist($config);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DigestConfig $config, bool $flush = false): void
    {
        $this->getEntityManager()->remove($config);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
