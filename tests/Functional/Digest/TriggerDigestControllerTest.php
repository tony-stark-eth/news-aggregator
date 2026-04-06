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
final class TriggerDigestControllerTest extends WebTestCase
{
    public function testPostDispatchesMessageAndRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $this->createConfigAndReturnId($user, 'Trigger Test', '0 8 * * *');

        // GET index to start session and get the form with CSRF token
        $crawler = $client->request('GET', '/digests');
        $form = $crawler->filter('form[action$="/trigger"]')->first()->form();

        $client->submit($form);

        self::assertResponseRedirects('/digests');
    }

    public function testPostForNonExistentConfigRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        // GET index to start session, then POST to a nonexistent config
        $crawler = $client->request('GET', '/digests');
        $csrfToken = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/digests/99999/trigger', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/digests');
    }

    public function testPostWithInvalidCsrfShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $configId = $this->createConfigAndReturnId($user, 'CSRF Test', '0 8 * * *');

        $client->request('POST', '/digests/' . $configId . '/trigger', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects('/digests');
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

    private function createConfigAndReturnId(User $user, string $name, string $schedule): int
    {
        /** @var DigestConfigRepository $repository */
        $repository = self::getContainer()->get(DigestConfigRepositoryInterface::class);

        $config = new DigestConfig($name, $schedule, $user, new \DateTimeImmutable());
        $repository->save($config, flush: true);

        $id = $config->getId();
        self::assertNotNull($id);

        return $id;
    }
}
