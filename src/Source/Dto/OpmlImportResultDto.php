<?php

declare(strict_types=1);

namespace App\Source\Dto;

final readonly class OpmlImportResultDto
{
    /**
     * @param list<string> $importedNames
     * @param list<string> $skippedNames
     */
    public function __construct(
        public int $importedCount,
        public int $skippedCount,
        public array $importedNames,
        public array $skippedNames,
    ) {
    }
}
