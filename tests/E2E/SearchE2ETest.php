<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class SearchE2ETest extends PantherTestCase
{
    public function testSearchPage(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost:8443',
        ]);
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        $client->request('GET', '/search?q=nonexistent_xyz');
        self::assertSelectorTextContains('body', 'No results');
    }
}
