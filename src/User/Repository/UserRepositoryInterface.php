<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\User\Entity\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findFirst(): ?User;

    public function save(User $user, bool $flush = false): void;

    public function flush(): void;
}
