<?php

declare(strict_types=1);

namespace App\Source\Command;

use App\Source\Repository\SourceRepositoryInterface;
use App\Source\ValueObject\SourceHealth;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-sources',
    description: 'Check and display health status of all feed sources',
)]
final class CheckSourcesCommand extends Command
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sources = $this->sourceRepository->findAll();

        if ($sources === []) {
            $io->warning('No sources configured. Run app:seed-data first.');

            return Command::SUCCESS;
        }

        $rows = [];
        $unhealthy = 0;
        foreach ($sources as $source) {
            $health = $source->getHealthStatus();
            $healthLabel = match ($health) {
                SourceHealth::Healthy => '<fg=green>healthy</>',
                SourceHealth::Degraded => '<fg=yellow>degraded</>',
                SourceHealth::Failing => '<fg=red>failing</>',
                SourceHealth::Disabled => '<fg=gray>disabled</>',
            };

            if ($health !== SourceHealth::Healthy) {
                $unhealthy++;
            }

            $rows[] = [
                $source->getName(),
                $source->getFeedUrl(),
                $healthLabel,
                $source->getErrorCount(),
                $source->getLastErrorMessage() ?? '-',
                $source->getLastFetchedAt()?->format('Y-m-d H:i') ?? 'never',
                $source->isEnabled() ? 'yes' : 'no',
            ];
        }

        $io->table(
            ['Name', 'Feed URL', 'Health', 'Errors', 'Last Error', 'Last Fetched', 'Enabled'],
            $rows,
        );

        if ($unhealthy > 0) {
            $io->warning(sprintf('%d source(s) are not healthy.', $unhealthy));
        } else {
            $io->success('All sources are healthy.');
        }

        return Command::SUCCESS;
    }
}
