<?php

declare(strict_types=1);

namespace App\Digest\Service;

use App\Digest\ValueObject\GroupedArticles;

interface DigestSummaryServiceInterface
{
    public function generate(GroupedArticles $groupedArticles): string;
}
