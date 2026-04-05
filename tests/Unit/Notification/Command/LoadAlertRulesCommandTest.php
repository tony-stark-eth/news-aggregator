<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Command;

use App\Notification\Command\LoadAlertRulesCommand;
use App\Notification\Dto\AlertRuleFixture;
use App\Notification\Entity\AlertRule;
use App\Notification\Service\AlertRuleFixtureLoaderInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LoadAlertRulesCommand::class)]
#[UsesClass(AlertRuleFixture::class)]
#[UsesClass(AlertRule::class)]
final class LoadAlertRulesCommandTest extends TestCase
{
    private MockObject&EntityManagerInterface $em;

    private MockObject&AlertRuleFixtureLoaderInterface $loader;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->loader = $this->createMock(AlertRuleFixtureLoaderInterface::class);

        $command = new LoadAlertRulesCommand(
            $this->em,
            new MockClock('2026-04-05 10:00:00'),
            $this->loader,
            'admin@test.com',
        );

        $this->tester = new CommandTester($command);
    }

    public function testCreatesNewRules(): void
    {
        $this->stubNoExistingRules();
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Created: Rule A', $this->tester->getDisplay());
        self::assertStringContainsString('1 created', $this->tester->getDisplay());
    }

    public function testUpdatesExistingRules(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $existingRule = new AlertRule('Rule A', AlertRuleType::Keyword, $user, new \DateTimeImmutable());

        $this->stubExistingRules($existingRule, $user);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Updated: Rule A', $this->tester->getDisplay());
        self::assertStringContainsString('1 updated', $this->tester->getDisplay());
    }

    public function testDryRunDoesNotFlush(): void
    {
        $this->stubNoExistingRules();
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->em->expects(self::never())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Dry run', $this->tester->getDisplay());
    }

    public function testPurgeRemovesAbsentRules(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $orphanRule = new AlertRule('Orphan', AlertRuleType::Keyword, $user, new \DateTimeImmutable());

        $this->stubExistingRulesForPurge($orphanRule, $user);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->em->expects(self::once())->method('remove')->with($orphanRule);

        $this->tester->execute([
            'path' => '/fixtures',
            '--purge' => true,
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Purged: Orphan', $this->tester->getDisplay());
    }

    public function testFailsWhenAdminNotFound(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);

        $this->loader->method('loadFromPath')->willReturn([]);

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
        self::assertStringContainsString('not found', $this->tester->getDisplay());
    }

    private function fixture(string $name): AlertRuleFixture
    {
        return new AlertRuleFixture(
            name: $name,
            type: AlertRuleType::Keyword,
            keywords: ['test'],
            contextPrompt: null,
            urgency: AlertUrgency::Medium,
            severityThreshold: 5,
            cooldownMinutes: 60,
            categories: [],
            enabled: true,
        );
    }

    private function stubNoExistingRules(): void
    {
        $ruleRepo = $this->createStub(EntityRepository::class);
        $ruleRepo->method('findOneBy')->willReturn(null);
        $ruleRepo->method('findBy')->willReturn([]);

        $userRepo = $this->createStub(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(new User('admin@test.com', 'hashed'));

        $this->em->method('getRepository')->willReturnCallback(
            static fn (string $class): Stub => $class === User::class ? $userRepo : $ruleRepo,
        );
    }

    private function stubExistingRules(AlertRule $rule, User $user): void
    {
        $ruleRepo = $this->createStub(EntityRepository::class);
        $ruleRepo->method('findOneBy')->willReturn($rule);
        $ruleRepo->method('findBy')->willReturn([$rule]);

        $userRepo = $this->createStub(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $this->em->method('getRepository')->willReturnCallback(
            static fn (string $class): Stub => $class === User::class ? $userRepo : $ruleRepo,
        );
    }

    private function stubExistingRulesForPurge(AlertRule $orphanRule, User $user): void
    {
        $ruleRepo = $this->createStub(EntityRepository::class);
        $ruleRepo->method('findOneBy')->willReturn(null);
        $ruleRepo->method('findBy')->willReturn([$orphanRule]);

        $userRepo = $this->createStub(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $this->em->method('getRepository')->willReturnCallback(
            static fn (string $class): Stub => $class === User::class ? $userRepo : $ruleRepo,
        );
    }
}
