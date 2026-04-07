<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Service\DomainRateLimiterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[CoversClass(DomainRateLimiterService::class)]
final class DomainRateLimiterServiceTest extends TestCase
{
    public function testConsumesTokenForDomain(): void
    {
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->expects(self::once())->method('ensureAccepted');

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())
            ->method('consume')
            ->willReturn($rateLimit);

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
        $rateLimit = $this->createStub(RateLimit::class);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

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
        $rateLimit = $this->createStub(RateLimit::class);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->with('unknown')
            ->willReturn($limiter);

        $service = new DomainRateLimiterService($factory);
        $service->waitForDomain('not-a-valid-url');
    }
}
