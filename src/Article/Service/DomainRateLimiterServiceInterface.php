<?php

declare(strict_types=1);

namespace App\Article\Service;

use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

interface DomainRateLimiterServiceInterface
{
    /**
     * Consumes a rate limit token for the given domain.
     *
     * @throws RateLimitExceededException when the rate limit is exhausted
     */
    public function waitForDomain(string $url): void;
}
