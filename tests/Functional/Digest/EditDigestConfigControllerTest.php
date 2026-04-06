<?php

declare(strict_types=1);

namespace App\Tests\Functional\Digest;

use App\Digest\Entity\DigestConfig;
use App\Digest\Repository\DigestConfigRepository;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class EditDigestConfigControllerTest extends WebTestCase
{
    public function testGetRendersPrefilledForm(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $configId = $this->createConfigAndReturnId($user, 'Test Digest', '0 8 * * *');

        $client->request('GET', '/digests/' . $configId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"][value="Test Digest"]');
        self::assertSelectorExists('input[name="schedule"][value="0 8 * * *"]');
    }

    public function testPostUpdatesConfigAndRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $configId = $this->createConfigAndReturnId($user, 'Old Name', '0 8 * * *');

        $client->request('POST', '/digests/' . $configId . '/edit', [
            'name' => 'Updated Name',
            'schedule' => '0 9 * * 1-5',
            'article_limit' => 20,
            'enabled' => '1',
        ]);

        self::assertResponseRedirects('/digests');

        $repository = $this->getDigestConfigRepository();
        $updated = $repository->findById($configId);

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->getName());
        self::assertSame('0 9 * * 1-5', $updated->getSchedule());
        self::assertSame(20, $updated->getArticleLimit());
    }

    public function testGetReturnsRedirectForNonExistentConfig(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/digests/99999/edit');

        self::assertResponseRedirects('/digests');
    }

    public function testPostWithEmptyNameShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $configId = $this->createConfigAndReturnId($user, 'Some Digest', '0 8 * * *');

        $client->request('POST', '/digests/' . $configId . '/edit', [
            'name' => '',
            'schedule' => '0 8 * * *',
        ]);

        self::assertResponseRedirects('/digests/' . $configId . '/edit');
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

    private function getDigestConfigRepository(): DigestConfigRepository
    {
        /** @var DigestConfigRepository $repository */
        $repository = self::getContainer()->get(DigestConfigRepositoryInterface::class);

        return $repository;
    }

    private function createConfigAndReturnId(User $user, string $name, string $schedule): int
    {
        $repository = $this->getDigestConfigRepository();

        $config = new DigestConfig($name, $schedule, $user, new \DateTimeImmutable());
        $repository->save($config, flush: true);

        $id = $config->getId();
        self::assertNotNull($id);

        return $id;
    }
}
