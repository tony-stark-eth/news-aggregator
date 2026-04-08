<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Service\DomainRateLimiterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;

#[CoversClass(DomainRateLimiterService::class)]
final class DomainRateLimiterServiceTest extends TestCase
{
    public function testReservesAndWaitsForDomain(): void
    {
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())
            ->method('reserve')
            ->willReturn($this->createReservation());

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->with('example.com')
            ->willReturn($limiter);

        $service = new DomainRateLimiterService($factory);
        $service->waitForDomain('https://example.com/article');
    }

    public function testExtractsDomainFromUrl(): void
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('reserve')->willReturn($this->createReservation());

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->with('news.ycombinator.com')
            ->willReturn($limiter);

        $service = new DomainRateLimiterService($factory);
        $service->waitForDomain('https://news.ycombinator.com/item?id=123');
    }

    public function testUsesUnknownForInvalidUrl(): void
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('reserve')->willReturn($this->createReservation());

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->with('unknown')
            ->willReturn($limiter);

        $service = new DomainRateLimiterService($factory);
        $service->waitForDomain('not-a-valid-url');
    }

    private function createReservation(): Reservation
    {
        // timeToAct = now (no wait needed), with a stub RateLimit
        $rateLimit = $this->createStub(RateLimit::class);

        return new Reservation(microtime(true), $rateLimit);
    }
}
