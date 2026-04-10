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
}
