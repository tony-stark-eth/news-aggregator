<?php

declare(strict_types=1);

namespace App\Shared\AI\Command;

use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use App\Shared\AI\ValueObject\ModelQualityCategory;
use App\Shared\AI\ValueObject\ModelQualityStatsMap;
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

        $this->showCategoryStats($io, 'Enrichment', ModelQualityCategory::Enrichment);
        $this->showCategoryStats($io, 'Chat', ModelQualityCategory::Chat);
        $this->showCategoryStats($io, 'Embedding', ModelQualityCategory::Embedding);

        $this->showModelPool($io, 'free', $this->modelDiscovery->discoverFreeModels());
        $this->showModelPool($io, 'tool-calling', $this->modelDiscovery->discoverToolCallingModels());
        $this->showModelPool($io, 'embedding', $this->modelDiscovery->discoverEmbeddingModels());

        return Command::SUCCESS;
    }

    private function showCategoryStats(SymfonyStyle $io, string $title, ModelQualityCategory $category): void
    {
        $stats = $this->qualityTracker->getStatsByCategory($category);

        $io->section($title . ' Model Quality');

        if ($stats->isEmpty()) {
            $io->text('No data yet.');

            return;
        }

        $this->renderStatsTable($io, $stats);
    }

    private function renderStatsTable(SymfonyStyle $io, ModelQualityStatsMap $stats): void
    {
        $rows = [];
        foreach ($stats as $modelId => $data) {
            $rows[] = [
                $modelId,
                $data->accepted,
                $data->rejected,
                sprintf('%.1f%%', $data->acceptanceRate * 100),
            ];
        }

        $io->table(['Model', 'Accepted', 'Rejected', 'Rate'], $rows);
    }

    private function showModelPool(SymfonyStyle $io, string $poolName, ModelIdCollection $models): void
    {
        if ($models->isEmpty()) {
            $io->warning(sprintf('No %s models discovered (circuit breaker may be open).', $poolName));

            return;
        }

        $io->section(sprintf('Available %s models (%d)', $poolName, $models->count()));
        $io->listing(array_map(static fn (ModelId $model): string => (string) $model, $models->toArray()));
    }
}
