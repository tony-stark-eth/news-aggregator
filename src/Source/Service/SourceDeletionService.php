<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;

final readonly class SourceDeletionService implements SourceDeletionServiceInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
    ) {
    }

    public function delete(Source $source): void
    {
        $this->sourceRepository->remove($source, flush: true);
    }

    public function deleteMany(array $ids): int
    {
        $sources = $this->sourceRepository->findByIds($ids);

        foreach ($sources as $source) {
            $this->sourceRepository->remove($source);
        }

        $this->sourceRepository->flush();

        return count($sources);
    }
}
