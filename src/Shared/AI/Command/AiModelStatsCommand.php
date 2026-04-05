<?php

declare(strict_types=1);

namespace App\Shared\AI\Command;

use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\AI\ValueObject\ModelId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai-model-stats',
    description: 'Display AI model quality statistics and available free models',
)]
final class AiModelStatsCommand extends Command
{
    public function __construct(
        private readonly ModelQualityTrackerInterface $qualityTracker,
        private readonly ModelDiscoveryServiceInterface $modelDiscovery,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Show quality stats
        $stats = $this->qualityTracker->getAllStats();
        if ($stats->isEmpty()) {
            $io->info('No model quality data yet.');
        } else {
            $rows = [];
            foreach ($stats as $modelId => $data) {
                $rows[] = [
                    $modelId,
                    $data->accepted,
                    $data->rejected,
                    sprintf('%.1f%%', $data->acceptanceRate * 100),
                ];
            }

            $io->table(
                ['Model', 'Accepted', 'Rejected', 'Rate'],
                $rows,
            );
        }

        // Show available free models
        $freeModels = $this->modelDiscovery->discoverFreeModels();
        if ($freeModels->isEmpty()) {
            $io->warning('No free models discovered (circuit breaker may be open).');
        } else {
            $io->section(sprintf('Available free models (%d)', $freeModels->count()));
            $io->listing(array_map(static fn (ModelId $model): string => (string) $model, $freeModels->toArray()));
        }

        return Command::SUCCESS;
    }
}
