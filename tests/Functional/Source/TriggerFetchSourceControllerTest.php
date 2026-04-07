<?php

declare(strict_types=1);

namespace App\Tests\Functional\Source;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepository;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepository;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class TriggerFetchSourceControllerTest extends WebTestCase
{
    public function testHtmxPostDispatchesMessageAndReturnsQueuedBadge(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $sourceId = $this->createSourceAndReturnId('Trigger Test', $category);

        $csrfToken = $this->getCsrfTokenFromSourcesPage($client);

        $client->request('POST', '/sources/' . $sourceId . '/fetch', [], [], [
            'HTTP_HX-Request' => 'true',
            'HTTP_X-CSRF-Token' => $csrfToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Queued', (string) $client->getResponse()->getContent());
    }

    public function testNonHtmxPostRedirectsWithFlash(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $sourceId = $this->createSourceAndReturnId('Trigger Redirect', $category);

        $csrfToken = $this->getCsrfTokenFromSourcesPage($client);

        $client->request('POST', '/sources/' . $sourceId . '/fetch', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/sources');
    }

    public function testInvalidCsrfTokenReturnsForbiddenForHtmx(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $sourceId = $this->createSourceAndReturnId('Trigger CSRF', $category);

        // Visit sources page first to establish session
        $client->request('GET', '/sources');

        $client->request('POST', '/sources/' . $sourceId . '/fetch', [], [], [
            'HTTP_HX-Request' => 'true',
            'HTTP_X-CSRF-Token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonExistentSourceReturnsNotFoundForHtmx(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        // Create a source so the page has a fetch button to extract CSRF from
        $category = $this->getOrCreateCategory();
        $this->createSourceAndReturnId('CSRF Source', $category);
        $csrfToken = $this->getCsrfTokenFromSourcesPage($client);

        $client->request('POST', '/sources/99999/fetch', [], [], [
            'HTTP_HX-Request' => 'true',
            'HTTP_X-CSRF-Token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnauthenticatedUserRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('POST', '/sources/1/fetch');

        self::assertResponseRedirects('/login');
    }

    private function getCsrfTokenFromSourcesPage(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/sources');
        $fetchBtn = $crawler->filter('button[hx-post$="/fetch"]')->first();

        /** @var array<string, string> $headers */
        $headers = json_decode($fetchBtn->attr('hx-headers') ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        return $headers['X-CSRF-Token'];
    }

    private function getOrCreateUser(): User
    {
        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepositoryInterface::class);

        $user = $repository->findFirst();
        if (! $user instanceof User) {
            $user = new User('test@example.com', 'hashed');
            $repository->save($user, flush: true);
        }

        $user->setRoles(['ROLE_ADMIN']);
        $repository->save($user, flush: true);

        return $user;
    }

    private function getOrCreateCategory(): Category
    {
        /** @var CategoryRepository $repository */
        $repository = self::getContainer()->get(CategoryRepositoryInterface::class);

        $categories = $repository->findAll();
        if ($categories !== []) {
            return $categories[0];
        }

        $category = new Category('Test Category', 'test-cat', 0, '#000000');
        $repository->save($category, flush: true);

        return $category;
    }

    private function createSourceAndReturnId(string $name, Category $category): int
    {
        /** @var SourceRepository $repository */
        $repository = self::getContainer()->get(SourceRepositoryInterface::class);

        $uniqueUrl = 'https://trigger-test-' . uniqid() . '.example.com/feed.xml';
        $source = new Source($name, $uniqueUrl, $category, new \DateTimeImmutable());
        $repository->save($source, flush: true);

        $id = $source->getId();
        self::assertNotNull($id);

        return $id;
    }
}
