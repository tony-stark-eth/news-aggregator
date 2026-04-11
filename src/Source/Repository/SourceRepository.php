<?php

declare(strict_types=1);

namespace App\Source\Repository;

use App\Source\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
final class SourceRepository extends ServiceEntityRepository implements SourceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    public function findById(int $id): ?Source
    {
        return $this->find($id);
    }

    /**
     * @return list<Source>
     */
    public function findAll(): array
    {
        /** @var list<Source> */
        return parent::findAll();
    }

    /**
     * @return list<Source>
     */
    public function findEnabled(): array
    {
        /** @var list<Source> */
        return $this->findBy([
            'enabled' => true,
        ]);
    }

    public function findByFeedUrl(string $feedUrl): ?Source
    {
        return $this->findOneBy([
            'feedUrl' => $feedUrl,
        ]);
    }

    public function countEnabled(): int
    {
        return $this->count([
            'enabled' => true,
        ]);
    }

    public function save(Source $source, bool $flush = false): void
    {
        $this->getEntityManager()->persist($source);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Source $source, bool $flush = false): void
    {
        $this->getEntityManager()->remove($source);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function findMostRecentFetchedAt(): ?\DateTimeImmutable
    {
        /** @var string|null $result */
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.lastFetchedAt)')
            ->where('s.enabled = true')
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? new \DateTimeImmutable($result) : null;
    }

    public function countAll(): int
    {
        return $this->count();
    }

    public function countDisabled(): int
    {
        return $this->count([
            'enabled' => false,
        ]);
    }

    public function countByHealth(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        /** @var array{healthy: int|string, degraded: int|string, failing: int|string, disabled_health: int|string}|false $result */
        $result = $conn->executeQuery("
            SELECT
                COUNT(*) FILTER (WHERE health_status = 'healthy') AS healthy,
                COUNT(*) FILTER (WHERE health_status = 'degraded') AS degraded,
                COUNT(*) FILTER (WHERE health_status = 'failing') AS failing,
                COUNT(*) FILTER (WHERE health_status = 'disabled') AS disabled_health
            FROM source
        ")->fetchAssociative();

        if ($result === false) {
            return [
                'healthy' => 0,
                'degraded' => 0,
                'failing' => 0,
                'disabled_health' => 0,
            ];
        }

        return [
            'healthy' => (int) $result['healthy'],
            'degraded' => (int) $result['degraded'],
            'failing' => (int) $result['failing'],
            'disabled_health' => (int) $result['disabled_health'],
        ];
    }

    /**
     * @return list<Source>
     */
    public function findAllOrderedByHealth(): array
    {
        /** @var list<Source> */
        return $this->createQueryBuilder('s')
            ->orderBy("CASE s.healthStatus
                WHEN 'failing' THEN 0
                WHEN 'degraded' THEN 1
                WHEN 'disabled' THEN 2
                WHEN 'healthy' THEN 3
                ELSE 4 END", 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
