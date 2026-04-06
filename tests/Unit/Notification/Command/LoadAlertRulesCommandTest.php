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

    private MockClock $clock;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->alertRuleRepository = $this->createMock(AlertRuleRepositoryInterface::class);
        $this->loader = $this->createMock(AlertRuleFixtureLoaderInterface::class);
        $this->clock = new MockClock('2026-04-05 10:00:00');

        $command = new LoadAlertRulesCommand(
            $this->userRepository,
            $this->alertRuleRepository,
            $this->clock,
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
        self::assertStringContainsString('0 updated', $this->tester->getDisplay());
        self::assertStringContainsString('0 purged', $this->tester->getDisplay());
    }

    public function testCreatesMultipleNewRules(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(new User('admin@test.com', 'hashed'));
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([]);
        $this->loader->method('loadFromPath')->willReturn([
            $this->fixture('Rule A'),
            $this->fixture('Rule B'),
        ]);

        $this->alertRuleRepository->expects(self::exactly(2))->method('save');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Created: Rule A', $this->tester->getDisplay());
        self::assertStringContainsString('Created: Rule B', $this->tester->getDisplay());
        self::assertStringContainsString('2 created', $this->tester->getDisplay());
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
        self::assertStringContainsString('0 created', $this->tester->getDisplay());
    }

    public function testUpdateSetsAllFields(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $existingRule = new AlertRule('Rule A', AlertRuleType::Keyword, $user, new \DateTimeImmutable('2026-01-01'));

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn($existingRule);
        $this->alertRuleRepository->method('findByUser')->willReturn([$existingRule]);

        $fixture = new AlertRuleFixture(
            name: 'Rule A',
            type: AlertRuleType::Ai,
            keywords: ['updated-keyword'],
            contextPrompt: 'Updated context',
            urgency: AlertUrgency::High,
            severityThreshold: 8,
            cooldownMinutes: 120,
            categories: ['tech', 'science'],
            enabled: false,
        );
        $this->loader->method('loadFromPath')->willReturn([$fixture]);

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(['updated-keyword'], $existingRule->getKeywords());
        self::assertSame('Updated context', $existingRule->getContextPrompt());
        self::assertSame(AlertUrgency::High, $existingRule->getUrgency());
        self::assertSame(8, $existingRule->getSeverityThreshold());
        self::assertSame(120, $existingRule->getCooldownMinutes());
        self::assertSame(['tech', 'science'], $existingRule->getCategories());
        self::assertFalse($existingRule->isEnabled());
        self::assertSame($this->clock->now()->getTimestamp(), $existingRule->getUpdatedAt()->getTimestamp());
    }

    public function testCreateSetsAllFields(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([]);

        $fixture = new AlertRuleFixture(
            name: 'New Rule',
            type: AlertRuleType::Both,
            keywords: ['keyword1', 'keyword2'],
            contextPrompt: 'Test context prompt',
            urgency: AlertUrgency::Low,
            severityThreshold: 3,
            cooldownMinutes: 30,
            categories: ['politics'],
            enabled: true,
        );
        $this->loader->method('loadFromPath')->willReturn([$fixture]);

        $savedRule = null;
        $this->alertRuleRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (AlertRule $rule) use (&$savedRule): bool {
                $savedRule = $rule;

                return true;
            }));

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertInstanceOf(AlertRule::class, $savedRule);
        self::assertSame('New Rule', $savedRule->getName());
        self::assertSame(AlertRuleType::Both, $savedRule->getType());
        self::assertSame(['keyword1', 'keyword2'], $savedRule->getKeywords());
        self::assertSame('Test context prompt', $savedRule->getContextPrompt());
        self::assertSame(AlertUrgency::Low, $savedRule->getUrgency());
        self::assertSame(3, $savedRule->getSeverityThreshold());
        self::assertSame(30, $savedRule->getCooldownMinutes());
        self::assertSame(['politics'], $savedRule->getCategories());
        self::assertTrue($savedRule->isEnabled());
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
        self::assertStringContainsString('1 to create', $this->tester->getDisplay());
        self::assertStringContainsString('No changes persisted', $this->tester->getDisplay());
    }

    public function testDryRunWithUpdatesShowsCorrectStats(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $existingRule = new AlertRule('Rule A', AlertRuleType::Keyword, $user, new \DateTimeImmutable());

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn($existingRule);
        $this->alertRuleRepository->method('findByUser')->willReturn([$existingRule]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::never())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
            '--dry-run' => true,
        ]);

        self::assertStringContainsString('1 to update', $this->tester->getDisplay());
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
        self::assertStringContainsString('1 purged', $this->tester->getDisplay());
    }

    public function testPurgeKeepsRulesPresentInFixtures(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $ruleA = new AlertRule('Rule A', AlertRuleType::Keyword, $user, new \DateTimeImmutable());

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn($ruleA);
        $this->alertRuleRepository->method('findByUser')->willReturn([$ruleA]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::never())->method('remove');

        $this->tester->execute([
            'path' => '/fixtures',
            '--purge' => true,
        ]);

        self::assertStringNotContainsString('Purged', $this->tester->getDisplay());
    }

    public function testWithoutPurgeFlagDoesNotRemoveRules(): void
    {
        $user = new User('admin@test.com', 'hashed');
        $orphan = new AlertRule('Orphan', AlertRuleType::Keyword, $user, new \DateTimeImmutable());

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([$orphan]);
        $this->loader->method('loadFromPath')->willReturn([$this->fixture('Rule A')]);

        $this->alertRuleRepository->expects(self::never())->method('remove');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertStringNotContainsString('Purged', $this->tester->getDisplay());
    }

    public function testFailsWhenAdminNotFound(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->loader->method('loadFromPath')->willReturn([]);

        $this->alertRuleRepository->expects(self::never())->method('save');
        $this->alertRuleRepository->expects(self::never())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
        self::assertStringContainsString('not found', $this->tester->getDisplay());
        self::assertStringContainsString('admin@test.com', $this->tester->getDisplay());
    }

    public function testLoaderCalledWithProvidedPath(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(new User('admin@test.com', 'hashed'));
        $this->alertRuleRepository->method('findByNameAndUser')->willReturn(null);
        $this->alertRuleRepository->method('findByUser')->willReturn([]);

        $this->loader->expects(self::once())
            ->method('loadFromPath')
            ->with('/custom/path/rules.yaml')
            ->willReturn([]);

        $this->tester->execute([
            'path' => '/custom/path/rules.yaml',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
    }

    public function testEmptyFixturesProducesZeroStats(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(new User('admin@test.com', 'hashed'));
        $this->loader->method('loadFromPath')->willReturn([]);
        $this->alertRuleRepository->method('findByUser')->willReturn([]);

        $this->alertRuleRepository->expects(self::never())->method('save');
        $this->alertRuleRepository->expects(self::once())->method('flush');

        $this->tester->execute([
            'path' => '/fixtures',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('0 created', $this->tester->getDisplay());
        self::assertStringContainsString('0 updated', $this->tester->getDisplay());
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
