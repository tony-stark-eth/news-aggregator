<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\ValueObject\ModelIdCollection;

interface ModelDiscoveryServiceInterface
{
    public function discoverFreeModels(): ModelIdCollection;
}
