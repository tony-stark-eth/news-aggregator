<?php

declare(strict_types=1);

namespace App\Chat\Service;

interface ChatModelResolverInterface
{
    /**
     * Resolve the best available model for chat.
     *
     * @return non-empty-string
     */
    public function resolveModel(): string;

    /**
     * Resolve an ordered list of models to try for chat (failover chain).
     *
     * @return list<non-empty-string>
     */
    public function resolveModelChain(): array;
}
