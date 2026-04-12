<?php

declare(strict_types=1);

namespace App\Enrichment\Command;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Enrichment\Message\ScoreSentimentMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:backfill-sentiment',
    description: 'Dispatch sentiment scoring jobs for articles without a score',
)]
final class BackfillSentimentCommand extends Command
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to dispatch (0 = all)', '500');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show count without dispatching');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $limitStr */
        $limitStr = $input->getOption('limit');
        $limit = (int) $limitStr;
        $dryRun = $input->getOption('dry-run') === true;
        $effectiveLimit = $limit > 0 ? $limit : 100_000;

        $ids = $this->articleRepository->findIdsWithoutSentiment($effectiveLimit);

        if ($ids === []) {
            $io->success('All articles have sentiment scores.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->info(\sprintf('Found %d articles without sentiment (dry run).', \count($ids)));

            return Command::SUCCESS;
        }

        foreach ($ids as $id) {
            $this->messageBus->dispatch(new ScoreSentimentMessage($id));
        }

        $io->success(\sprintf('Dispatched %d sentiment scoring jobs.', \count($ids)));

        return Command::SUCCESS;
    }
}
