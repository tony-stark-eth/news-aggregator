<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class DashboardE2ETest extends PantherTestCase
{
    public function testLoginAndDashboard(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost:8443',
        ]);
        $client->request('GET', '/login');

        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        self::assertSelectorTextContains('body', 'News Aggregator');
    }

    public function testCategoryFilter(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost:8443',
        ]);
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        $client->clickLink('Tech');
        self::assertStringContainsString('category=tech', $client->getCurrentURL());
    }

    public function testThemeToggle(): void
    {
        $client = self::createPantherClient([
            'external_base_uri' => 'https://localhost:8443',
        ]);
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => 'demo@localhost',
            '_password' => 'demo',
        ]);

        $client->executeScript("document.getElementById('theme-toggle').click()");
        $theme = $client->executeScript("return document.documentElement.getAttribute('data-theme')");
        self::assertSame('winter', $theme);
    }
}
