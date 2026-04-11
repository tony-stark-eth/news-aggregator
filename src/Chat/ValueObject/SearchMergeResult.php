<?php

declare(strict_types=1);

namespace App\Chat\ValueObject;

/**
 * Result of merging semantic + keyword search scores.
 */
final readonly class SearchMergeResult
{
    /**
     * @param array<int, float>        $scores  article ID => combined score
     * @param array<int, SearchSource> $sources article ID => search source
     */
    public function __construct(
        public array $scores,
        public array $sources,
    ) {
    }
}
