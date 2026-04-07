<?php

declare(strict_types=1);

namespace App\Article\Service;

use App\Article\ValueObject\ReadabilityResult;

interface ReadabilityExtractorServiceInterface
{
    public function extract(string $html, string $url): ReadabilityResult;
}
