<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Entity\Source;

interface SourceDeletionServiceInterface
{
    public function delete(Source $source): void;

    /**
     * @param list<int> $ids
     */
    public function deleteMany(array $ids): int;
}
