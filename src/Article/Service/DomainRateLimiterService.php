<?php

declare(strict_types=1);

namespace App\Article\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class DomainRateLimiterService implements DomainRateLimiterServiceInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.fulltext_domain')]
        private RateLimiterFactoryInterface $rateLimiterFactory,
    ) {
    }

    public function waitForDomain(string $url): void
    {
        $domain = $this->extractDomain($url);
        $limiter = $this->rateLimiterFactory->create($domain);
        $reservation = $limiter->reserve();
        $reservation->wait();
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return \is_string($host) ? $host : 'unknown';
    }
}
