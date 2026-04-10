<?php

declare(strict_types=1);

namespace App\Chat\Service;

interface EmbeddingServiceInterface
{
    /**
     * Generate an embedding vector for the given text.
     *
     * @return list<float>|null The embedding vector, or null on failure
     */
    public function embed(string $text): ?array;
}
