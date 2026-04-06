<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use App\Shared\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository implements CategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findById(int $id): ?Category
    {
        return $this->find($id);
    }

    /**
     * @return list<Category>
     */
    public function findAll(): array
    {
        /** @var list<Category> */
        return parent::findAll();
    }

    /**
     * @return list<Category>
     */
    public function findAllOrderedByWeight(): array
    {
        /** @var list<Category> */
        return $this->findBy([], [
            'weight' => 'ASC',
        ]);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }

    public function save(Category $category, bool $flush = false): void
    {
        $this->getEntityManager()->persist($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
