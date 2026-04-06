<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Notification\Entity\AlertRule;
use App\Notification\Repository\AlertRuleRepository;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class EditAlertRuleControllerTest extends WebTestCase
{
    public function testGetEditFormRendersWithPrefilledValues(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $ruleId = $this->createAlertRuleAndReturnId($user, 'Test Rule', AlertRuleType::Keyword);

        $client->request('GET', '/alerts/' . $ruleId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"][value="Test Rule"]');
        self::assertSelectorExists('select[name="type"]');
    }

    public function testPostUpdatesRuleAndRedirects(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $ruleId = $this->createAlertRuleAndReturnId($user, 'Old Name', AlertRuleType::Keyword);

        $client->request('POST', '/alerts/' . $ruleId . '/edit', [
            'name' => 'Updated Name',
            'type' => 'ai',
            'keywords' => 'foo, bar',
            'urgency' => 'high',
            'context_prompt' => 'Some context',
            'severity_threshold' => 8,
            'cooldown_minutes' => 30,
        ]);

        self::assertResponseRedirects('/alerts');

        $repository = $this->getAlertRuleRepository();
        $updated = $repository->findById($ruleId);

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->getName());
        self::assertSame(AlertRuleType::Ai, $updated->getType());
        self::assertSame(['foo', 'bar'], $updated->getKeywords());
        self::assertSame(8, $updated->getSeverityThreshold());
        self::assertSame(30, $updated->getCooldownMinutes());
    }

    public function testGetReturnsRedirectForNonExistentRule(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $client->request('GET', '/alerts/99999/edit');

        self::assertResponseRedirects('/alerts');
    }

    public function testPostWithEmptyNameShowsError(): void
    {
        $client = self::createClient();

        $user = $this->getOrCreateUser();
        $client->loginUser($user);

        $ruleId = $this->createAlertRuleAndReturnId($user, 'Some Rule', AlertRuleType::Keyword);

        $client->request('POST', '/alerts/' . $ruleId . '/edit', [
            'name' => '',
            'type' => 'keyword',
            'keywords' => '',
            'urgency' => 'medium',
            'severity_threshold' => 5,
            'cooldown_minutes' => 60,
        ]);

        self::assertResponseRedirects('/alerts/' . $ruleId . '/edit');
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

    private function getAlertRuleRepository(): AlertRuleRepository
    {
        /** @var AlertRuleRepository $repository */
        $repository = self::getContainer()->get(AlertRuleRepositoryInterface::class);

        return $repository;
    }

    private function createAlertRuleAndReturnId(User $user, string $name, AlertRuleType $type): int
    {
        $repository = $this->getAlertRuleRepository();

        $rule = new AlertRule($name, $type, $user, new \DateTimeImmutable());
        $rule->setKeywords(['test']);
        $repository->save($rule, flush: true);

        $id = $rule->getId();
        self::assertNotNull($id);

        return $id;
    }
}
