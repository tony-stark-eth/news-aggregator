<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface SentimentScoringServiceInterface
{
    public function score(string $title, ?string $contentText): ?float;
}
