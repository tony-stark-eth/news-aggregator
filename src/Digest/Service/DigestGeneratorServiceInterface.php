<?php

declare(strict_types=1);

namespace App\Digest\Service;

use App\Digest\Entity\DigestConfig;
use App\Digest\ValueObject\GroupedArticles;

interface DigestGeneratorServiceInterface
{
    public function collectArticles(DigestConfig $config): GroupedArticles;
}
