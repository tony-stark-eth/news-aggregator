<?php

declare(strict_types=1);

namespace App\Tests\Functional\Source;

use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepository;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepository;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class DeleteSourceControllerTest extends WebTestCase
{
    public function testDeleteRemovesSourceWithArticles(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $feedUrl = 'https://delete-test-' . uniqid() . '.example.com/feed.xml';
        $source = new Source('Delete Test Source', $feedUrl, $category, new \DateTimeImmutable());
        $this->getSourceRepository()->save($source, flush: true);

        $sourceId = $source->getId();
        self::assertNotNull($sourceId);

        $article = new Article(
            'Test article',
            'https://delete-test-' . uniqid() . '.example.com/article',
            $source,
            new \DateTimeImmutable(),
        );
        $this->getEntityManager()->persist($article);
        $this->getEntityManager()->flush();
        $articleId = $article->getId();
        self::assertNotNull($articleId);

        $client->request(
            'POST',
            '/sources/' . $sourceId . '/delete',
            server: [
                'HTTP_HX-Request' => 'true',
                'HTTP_X-CSRF-Token' => $this->getCsrfToken('delete_source'),
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertNull($this->getSourceRepository()->findById($sourceId));
        self::assertNull($this->getEntityManager()->find(Article::class, $articleId));
    }

    public function testBulkDeleteRemovesSelectedSources(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $category = $this->getOrCreateCategory();
        $sourceA = $this->createSource('Bulk A', $category);
        $sourceB = $this->createSource('Bulk B', $category);

        $client->request(
            'POST',
            '/sources/delete-bulk',
            [
                'ids' => [$sourceA->getId(), $sourceB->getId()],
            ],
            server: [
                'HTTP_HX-Request' => 'true',
                'HTTP_X-CSRF-Token' => $this->getCsrfToken('delete_sources_bulk'),
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('HX-Refresh', 'true');
        self::assertNull($this->getSourceRepository()->findById((int) $sourceA->getId()));
        self::assertNull($this->getSourceRepository()->findById((int) $sourceB->getId()));
    }

    public function testBulkDeleteWithoutSelectionReturnsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request(
            'POST',
            '/sources/delete-bulk',
            [],
            server: [
                'HTTP_HX-Request' => 'true',
                'HTTP_X-CSRF-Token' => $this->getCsrfToken('delete_sources_bulk'),
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Select at least one source', (string) $client->getResponse()->getContent());
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

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function createSource(string $name, Category $category): Source
    {
        $source = new Source(
            $name,
            'https://bulk-delete-' . uniqid() . '.example.com/feed.xml',
            $category,
            new \DateTimeImmutable(),
        );
        $this->getSourceRepository()->save($source, flush: true);

        return $source;
    }

    private function getCsrfToken(string $tokenId): string
    {
        /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
        $tokenManager = self::getContainer()->get('security.csrf.token_manager');

        return $tokenManager->getToken($tokenId)->getValue();
    }
}
