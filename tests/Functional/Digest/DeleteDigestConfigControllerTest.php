<?php

declare(strict_types=1);

namespace App\Tests\Functional\Digest;

use App\Digest\Entity\DigestConfig;
use App\Digest\Repository\DigestConfigRepository;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class DeleteDigestConfigControllerTest extends WebTestCase
{
    public function testPostDeletesConfigAndRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $configId = $this->createConfigAndReturnId($user, 'To Delete', '0 8 * * *');

        // Use container CSRF manager instead of fragile DOM extraction
        $client->request('GET', '/digests');
        $csrfManager = self::getContainer()->get('security.csrf.token_manager');
        $token = $csrfManager->getToken('delete_digest_config')->getValue();

        $client->request('POST', '/digests/' . $configId . '/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/digests');

        // Clear identity map to force fresh DB query
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $repository = $this->getDigestConfigRepository();
        self::assertNull($repository->findById($configId));
    }

    public function testPostWithInvalidCsrfShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $configId = $this->createConfigAndReturnId($user, 'Keep Me', '0 8 * * *');

        $client->request('POST', '/digests/' . $configId . '/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects('/digests');

        $repository = $this->getDigestConfigRepository();
        self::assertNotNull($repository->findById($configId));
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
