<?php

declare(strict_types=1);

namespace App\Tests\Functional\Digest;

use App\Digest\Repository\DigestConfigRepository;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class CreateDigestConfigControllerTest extends WebTestCase
{
    public function testGetRendersForm(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/digests/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"]');
        self::assertSelectorExists('input[name="schedule"]');
    }

    public function testPostCreatesConfigAndRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('POST', '/digests/new', [
            'name' => 'Daily Tech Summary',
            'schedule' => '0 8 * * *',
            'article_limit' => 15,
            'enabled' => '1',
        ]);

        self::assertResponseRedirects('/digests');

        $repository = $this->getDigestConfigRepository();
        $config = $repository->findByNameAndUser('Daily Tech Summary', $user);

        self::assertNotNull($config);
        self::assertSame('0 8 * * *', $config->getSchedule());
        self::assertSame(15, $config->getArticleLimit());
        self::assertTrue($config->isEnabled());
    }

    public function testPostWithEmptyNameShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('POST', '/digests/new', [
            'name' => '',
            'schedule' => '0 8 * * *',
        ]);

        self::assertResponseRedirects('/digests/new');
    }

    public function testPostWithEmptyScheduleShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('POST', '/digests/new', [
            'name' => 'Test Digest',
            'schedule' => '',
        ]);

        self::assertResponseRedirects('/digests/new');
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
}
