<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class NavigationE2ETest extends PantherTestCase
{
    public function testAllNavLinksWork(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost:8443',
        ]);
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        $pages = ['/sources', '/alerts', '/notifications', '/digests', '/search', '/stats/ai', '/settings'];
        foreach ($pages as $page) {
            $client->request('GET', $page);
            self::assertSelectorExists('.navbar', sprintf('Page %s should have navbar', $page));
        }
    }
}
