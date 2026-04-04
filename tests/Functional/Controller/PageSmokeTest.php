<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke test: protected routes redirect to /login; login page returns 200.
 */
#[CoversNothing]
final class PageSmokeTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedRoutesProvider(): iterable
    {
        yield 'dashboard' => ['/'];
        yield 'sources' => ['/sources'];
        yield 'alerts' => ['/alerts'];
        yield 'notifications' => ['/notifications'];
        yield 'digests' => ['/digests'];
        yield 'search' => ['/search'];
        yield 'ai stats' => ['/stats/ai'];
        yield 'settings' => ['/settings'];
    }

    #[DataProvider('protectedRoutesProvider')]
    public function testProtectedRouteRedirectsToLogin(string $url): void
    {
        $client = self::createClient();
        $client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageReturns200(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }
}
