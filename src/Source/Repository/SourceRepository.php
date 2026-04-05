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
}
