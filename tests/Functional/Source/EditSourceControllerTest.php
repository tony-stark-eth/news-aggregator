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
final class EditSourceControllerTest extends WebTestCase
{
    public function testGetEditFormRendersWithPrefilledValues(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $uniqueUrl = 'https://edit-test-get-' . uniqid() . '.example.com/feed.xml';
        $sourceId = $this->createSourceAndReturnId('Test Source', $uniqueUrl, $category);

        $client->request('GET', '/sources/' . $sourceId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"][value="Test Source"]');
        self::assertSelectorExists('input[name="feed_url"]');
    }

    public function testPostUpdatesSourceAndRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $uniqueId = uniqid();
        $oldUrl = 'https://edit-test-post-old-' . $uniqueId . '.example.com/feed.xml';
        $newUrl = 'https://edit-test-post-new-' . $uniqueId . '.example.com/feed.xml';
        $sourceId = $this->createSourceAndReturnId('Old Name', $oldUrl, $category);

        $csrfToken = $this->getCsrfTokenFromEditPage($client, $sourceId);

        $client->request('POST', '/sources/' . $sourceId . '/edit', [
            '_token' => $csrfToken,
            'name' => 'Updated Name',
            'feed_url' => $newUrl,
            'site_url' => 'https://edit-test.example.com',
            'category_id' => $category->getId(),
            'language' => 'de',
            'enabled' => '1',
        ]);

        self::assertResponseRedirects('/sources');

        $repository = $this->getSourceRepository();
        $updated = $repository->findById($sourceId);

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->getName());
        self::assertSame($newUrl, $updated->getFeedUrl());
        self::assertSame('https://edit-test.example.com', $updated->getSiteUrl());
        self::assertSame('de', $updated->getLanguage());
    }

    public function testPostWithEmptyNameShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $uniqueUrl = 'https://edit-test-empty-' . uniqid() . '.example.com/feed.xml';
        $sourceId = $this->createSourceAndReturnId('Some Source', $uniqueUrl, $category);

        $csrfToken = $this->getCsrfTokenFromEditPage($client, $sourceId);

        $client->request('POST', '/sources/' . $sourceId . '/edit', [
            '_token' => $csrfToken,
            'name' => '',
            'feed_url' => $uniqueUrl,
            'category_id' => $category->getId(),
        ]);

        self::assertResponseRedirects('/sources/' . $sourceId . '/edit');
    }

    public function testPostWithInvalidFeedUrlShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $uniqueUrl = 'https://edit-test-invalid-' . uniqid() . '.example.com/feed.xml';
        $sourceId = $this->createSourceAndReturnId('Source', $uniqueUrl, $category);

        $csrfToken = $this->getCsrfTokenFromEditPage($client, $sourceId);

        $client->request('POST', '/sources/' . $sourceId . '/edit', [
            '_token' => $csrfToken,
            'name' => 'Source',
            'feed_url' => 'not-a-valid-url',
            'category_id' => $category->getId(),
        ]);

        self::assertResponseRedirects('/sources/' . $sourceId . '/edit');
    }

    public function testGetReturnsRedirectForNonExistentSource(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/sources/99999/edit');

        self::assertResponseRedirects('/sources');
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

    private function getSourceRepository(): SourceRepository
    {
        /** @var SourceRepository $repository */
        $repository = self::getContainer()->get(SourceRepositoryInterface::class);

        return $repository;
    }

    private function createSourceAndReturnId(string $name, string $feedUrl, Category $category): int
    {
        $repository = $this->getSourceRepository();

        $source = new Source($name, $feedUrl, $category, new \DateTimeImmutable());
        $repository->save($source, flush: true);

        $id = $source->getId();
        self::assertNotNull($id);

        return $id;
    }

    private function getCsrfTokenFromEditPage(KernelBrowser $client, int $sourceId): string
    {
        $crawler = $client->request('GET', '/sources/' . $sourceId . '/edit');

        return $crawler->filter('input[name="_token"]')->attr('value') ?? '';
    }
}
