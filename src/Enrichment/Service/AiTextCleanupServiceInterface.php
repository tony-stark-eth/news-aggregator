<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface AiTextCleanupServiceInterface
{
    public function clean(string $text): string;
}
