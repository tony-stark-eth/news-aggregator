<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use App\Shared\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
final class SettingRepository extends ServiceEntityRepository implements SettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->findOneBy([
            'key' => $key,
        ]);
    }

    /**
     * @return list<Setting>
     */
    public function findAll(): array
    {
        /** @var list<Setting> */
        return parent::findAll();
    }

    public function save(Setting $setting, bool $flush = false): void
    {
        $this->getEntityManager()->persist($setting);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
