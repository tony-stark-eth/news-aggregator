<?php

declare(strict_types=1);

namespace App\Tests\Functional\Digest;

use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use App\Digest\Repository\DigestConfigRepository;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Digest\Repository\DigestLogRepository;
use App\Digest\Repository\DigestLogRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class ViewDigestLogControllerTest extends WebTestCase
{
    public function testGetRendersLogDetail(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $logId = $this->createLogAndReturnId($user);

        $client->request('GET', '/digests/log/' . $logId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Digest Log Detail');
    }

    public function testGetForNonExistentLogRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/digests/log/99999');

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

    private function createLogAndReturnId(User $user): int
    {
        /** @var DigestConfigRepository $configRepository */
        $configRepository = self::getContainer()->get(DigestConfigRepositoryInterface::class);

        $config = new DigestConfig('Log Test', '0 8 * * *', $user, new \DateTimeImmutable());
        $configRepository->save($config, flush: true);

        /** @var DigestLogRepository $logRepository */
        $logRepository = self::getContainer()->get(DigestLogRepositoryInterface::class);

        $log = new DigestLog($config, new \DateTimeImmutable(), 5, 'Test content', true);
        $log->setArticleTitles(['Article 1', 'Article 2']);
        $logRepository->save($log, flush: true);

        $id = $log->getId();
        self::assertNotNull($id);

        return $id;
    }
}
