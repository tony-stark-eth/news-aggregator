<?php

declare(strict_types=1);

namespace App\Notification\Command;

use App\Notification\Dto\AlertRuleFixture;
use App\Notification\Entity\AlertRule;
use App\Notification\Service\AlertRuleFixtureLoaderInterface;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-alert-rules',
    description: 'Load alert rules from a YAML fixture file or directory',
)]
final class LoadAlertRulesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly AlertRuleFixtureLoaderInterface $fixtureLoader,
        private readonly string $adminEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to YAML file or directory');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without persisting');
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Remove rules not present in the fixture');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $path */
        $path = $input->getArgument('path');
        $dryRun = (bool) $input->getOption('dry-run');
        $purge = (bool) $input->getOption('purge');

        $user = $this->findAdminUser($io);

        if (! $user instanceof User) {
            return Command::FAILURE;
        }

        $fixtures = $this->fixtureLoader->loadFromPath($path);
        $stats = $this->upsertRules($io, $fixtures, $user);

        if ($purge) {
            $stats['purged'] = $this->purgeAbsentRules($io, $fixtures, $user);
        }

        $this->finalize($io, $stats, $dryRun);

        return Command::SUCCESS;
    }

    private function findAdminUser(SymfonyStyle $io): ?User
    {
        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $this->adminEmail,
        ]);

        if (! $user instanceof User) {
            $io->error(sprintf('Admin user "%s" not found.', $this->adminEmail));
        }

        return $user;
    }

    /**
     * @param list<AlertRuleFixture> $fixtures
     *
     * @return array{created: int, updated: int, purged: int}
     */
    private function upsertRules(SymfonyStyle $io, array $fixtures, User $user): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'purged' => 0,
        ];
        $repository = $this->entityManager->getRepository(AlertRule::class);

        foreach ($fixtures as $fixture) {
            /** @var AlertRule|null $existing */
            $existing = $repository->findOneBy([
                'name' => $fixture->name,
                'user' => $user,
            ]);

            if ($existing instanceof AlertRule) {
                $this->updateRule($existing, $fixture);
                $io->text(sprintf('  Updated: %s', $fixture->name));
                ++$stats['updated'];
            } else {
                $this->createRule($fixture, $user);
                $io->text(sprintf('  Created: %s', $fixture->name));
                ++$stats['created'];
            }
        }

        return $stats;
    }

    private function updateRule(AlertRule $rule, AlertRuleFixture $fixture): void
    {
        $rule->setKeywords($fixture->keywords);
        $rule->setContextPrompt($fixture->contextPrompt);
        $rule->setUrgency($fixture->urgency);
        $rule->setSeverityThreshold($fixture->severityThreshold);
        $rule->setCooldownMinutes($fixture->cooldownMinutes);
        $rule->setCategories($fixture->categories);
        $rule->setEnabled($fixture->enabled);
        $rule->setUpdatedAt($this->clock->now());
    }

    private function createRule(AlertRuleFixture $fixture, User $user): void
    {
        $rule = new AlertRule($fixture->name, $fixture->type, $user, $this->clock->now());
        $rule->setKeywords($fixture->keywords);
        $rule->setContextPrompt($fixture->contextPrompt);
        $rule->setUrgency($fixture->urgency);
        $rule->setSeverityThreshold($fixture->severityThreshold);
        $rule->setCooldownMinutes($fixture->cooldownMinutes);
        $rule->setCategories($fixture->categories);
        $rule->setEnabled($fixture->enabled);
        $this->entityManager->persist($rule);
    }

    /**
     * @param list<AlertRuleFixture> $fixtures
     */
    private function purgeAbsentRules(SymfonyStyle $io, array $fixtures, User $user): int
    {
        $fixtureNames = array_map(static fn (AlertRuleFixture $f): string => $f->name, $fixtures);
        /** @var list<AlertRule> $existingRules */
        $existingRules = $this->entityManager->getRepository(AlertRule::class)->findBy([
            'user' => $user,
        ]);
        $purged = 0;

        foreach ($existingRules as $rule) {
            if (! in_array($rule->getName(), $fixtureNames, true)) {
                $io->text(sprintf('  Purged: %s', $rule->getName()));
                $this->entityManager->remove($rule);
                ++$purged;
            }
        }

        return $purged;
    }

    /**
     * @param array{created: int, updated: int, purged: int} $stats
     */
    private function finalize(SymfonyStyle $io, array $stats, bool $dryRun): void
    {
        if ($dryRun) {
            $io->warning(sprintf(
                'Dry run: %d to create, %d to update, %d to purge. No changes persisted.',
                $stats['created'],
                $stats['updated'],
                $stats['purged'],
            ));

            return;
        }

        $this->entityManager->flush();
        $io->success(sprintf(
            'Done: %d created, %d updated, %d purged.',
            $stats['created'],
            $stats['updated'],
            $stats['purged'],
        ));
    }
}
