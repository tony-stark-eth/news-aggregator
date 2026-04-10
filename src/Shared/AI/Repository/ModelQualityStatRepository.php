<?php

declare(strict_types=1);

namespace App\Shared\AI\Repository;

use App\Shared\AI\Entity\ModelQualityStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModelQualityStat>
 */
final class ModelQualityStatRepository extends ServiceEntityRepository implements ModelQualityStatRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModelQualityStat::class);
    }

    public function findByModelId(string $modelId): ?ModelQualityStat
    {
        return $this->findOneBy([
            'modelId' => $modelId,
            'category' => 'enrichment',
        ]);
    }

    public function findByModelIdAndCategory(string $modelId, string $category): ?ModelQualityStat
    {
        return $this->findOneBy([
            'modelId' => $modelId,
            'category' => $category,
        ]);
    }

    /**
     * @return list<ModelQualityStat>
     */
    public function findAll(): array
    {
        /** @var list<ModelQualityStat> */
        return parent::findAll();
    }

    /**
     * @return list<ModelQualityStat>
     */
    public function findByCategory(string $category): array
    {
        /** @var list<ModelQualityStat> */
        return $this->findBy([
            'category' => $category,
        ]);
    }

    public function save(ModelQualityStat $stat, bool $flush = false): void
    {
        $this->getEntityManager()->persist($stat);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
