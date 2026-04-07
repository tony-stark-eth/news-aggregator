<?php

declare(strict_types=1);

namespace App\Tests\Functional\Source;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepository;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Controller\FetchAllSourcesController;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepository;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(FetchAllSourcesController::class)]
final class FetchAllSourcesControllerTest extends WebTestCase
{
    public function testHtmxPostDispatchesAndReturnsQueuedBadge(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $this->createEnabledSource('FA Source 1', $category);
        $this->createEnabledSource('FA Source 2', $category);

        $csrfToken = $this->getCsrfTokenFromSourcesPage($client);

        $client->request('POST', '/sources/fetch-all', [], [], [
            'HTTP_HX-Request' => 'true',
            'HTTP_X-CSRF-Token' => $csrfToken,
        ]);

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Queued', $content);
        self::assertStringContainsString('sources', $content);
    }

    public function testNonHtmxPostRedirectsToSources(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $csrfToken = $this->getCsrfTokenFromSourcesPage($client);

        $client->request('POST', '/sources/fetch-all', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/sources');
    }

    public function testInvalidCsrfTokenReturnsForbiddenForHtmx(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/sources');

        $client->request('POST', '/sources/fetch-all', [], [], [
            'HTTP_HX-Request' => 'true',
            'HTTP_X-CSRF-Token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidCsrfTokenNonHtmxRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/sources');

        $client->request('POST', '/sources/fetch-all', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects('/sources');
    }

    public function testUnauthenticatedUserRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('POST', '/sources/fetch-all');

        self::assertResponseRedirects('/login');
    }

    private function getCsrfTokenFromSourcesPage(KernelBrowser $client): string
    {
        $client->request('GET', '/sources');

        $csrfManager = self::getContainer()->get('security.csrf.token_manager');

        return $csrfManager->getToken('fetch_all_sources')->getValue();
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

    private function createEnabledSource(string $name, Category $category): void
    {
        /** @var SourceRepository $repository */
        $repository = self::getContainer()->get(SourceRepositoryInterface::class);

        $uniqueUrl = 'https://fetch-all-' . uniqid() . '.example.com/feed.xml';
        $source = new Source($name, $uniqueUrl, $category, new \DateTimeImmutable());
        $repository->save($source, flush: true);
    }
}
