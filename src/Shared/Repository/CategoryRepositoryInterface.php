<?php

declare(strict_types=1);

namespace App\Shared\Repository;

use App\Shared\Entity\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    /**
     * @return list<Category>
     */
    public function findAll(): array;

    public function findBySlug(string $slug): ?Category;

    public function save(Category $category, bool $flush = false): void;

    public function flush(): void;
}
