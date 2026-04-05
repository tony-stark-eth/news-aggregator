<?php

declare(strict_types=1);

namespace App\Digest\Command;

use App\Digest\Entity\DigestConfig;
use App\Digest\Message\GenerateDigestMessage;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;

#[AsCommand(
    name: 'app:process-digests',
    description: 'Check and dispatch due digest generations',
)]
final class ProcessDigestsCommand extends Command
{
    public function __construct(
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configs = $this->digestConfigRepository->findEnabled();

        $dispatched = 0;
        $now = $this->clock->now();

        foreach ($configs as $config) {
            $configId = $config->getId();
            if ($configId === null) {
                continue;
            }

            if (! $this->isDue($config, $now)) {
                continue;
            }

            $this->messageBus->dispatch(new GenerateDigestMessage($configId));
            $io->info(sprintf('Dispatched digest: %s', $config->getName()));
            $dispatched++;
        }

        $io->success(sprintf('Dispatched %d digest(s).', $dispatched));

        return Command::SUCCESS;
    }

    private function isDue(DigestConfig $config, \DateTimeImmutable $now): bool
    {
        $lastRunAt = $config->getLastRunAt();
        if (! $lastRunAt instanceof \DateTimeImmutable) {
            return true; // Never run, always due
        }

        try {
            $trigger = CronExpressionTrigger::fromSpec($config->getSchedule());
            $nextRun = $trigger->getNextRunDate($lastRunAt);

            if (! $nextRun instanceof \DateTimeImmutable) {
                return false;
            }

            return $nextRun <= $now;
        } catch (\Throwable) {
            return false;
        }
    }
}
