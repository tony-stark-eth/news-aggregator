<?php

declare(strict_types=1);

namespace App\Shared\Service;

interface QueueDepthServiceInterface
{
    public function getEnrichQueueDepth(): int;
}
