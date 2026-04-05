<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Command;

use App\Notification\Command\LoadAlertRulesCommand;
use App\Notification\Dto\AlertRuleFixture;
use App\Notification\Entity\AlertRule;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\Service\AlertRuleFixtureLoaderInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use App\User\Entity\User;
use App\User\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LoadAlertRulesCommand::class)]
#[UsesClass(AlertRuleFixture::class)]
#[UsesClass(AlertRule::class)]
final class LoadAlertRulesCommandTest extends TestCase
{
    private MockObject&UserRepositoryInterface $userRepository;

    private MockObject&AlertRuleRepositoryInterface $alertRuleRepository;

    private MockObject&AlertRuleFixtureLoaderInterface $loader;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->alertRuleRepository = $this->createMock(AlertRuleRepositoryInterface::class);
        $this->loader = $this->createMock(AlertRuleFixtureLoaderInterface::class);

        $command = new LoadAlertRulesCommand(
            $this->userRepository,
            $this->alertRuleRepository,
            new MockClock('2026-04-05 10:00:00'),
            $this->loader,
            'admin@test.com',
        );

        $this->tester = new CommandTester($command);
    }

    public function testCreatesNewRules(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(new User('admin@test.com', 'hashed'));
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::once())->method('save');
        $this->alertRuleRepository->expects(self::once())->method('flush');

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

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn($existingRule);
        $this->alertRuleRepository->method('findByUser')->willReturn([$existingRule]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::never())->method('save');
        $this->alertRuleRepository->expects(self::once())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Updated: Rule A', $this->tester->getDisplay());
        self::assertStringContainsString('1 updated', $this->tester->getDisplay());
    }

    public function testDryRunDoesNotFlush(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(new User('admin@test.com', 'hashed'));
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::never())->method('flush');

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

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([$orphanRule]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::once())->method('remove')->with($orphanRule);

        $this->tester->execute([
            'path' => '/fixtures',
            '--purge' => true,
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Purged: Orphan', $this->tester->getDisplay());
    }

    public function testFailsWhenAdminNotFound(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);
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
}
