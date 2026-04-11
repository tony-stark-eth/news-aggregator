<?php

declare(strict_types=1);

namespace App\Chat\Command;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Chat\Message\GenerateEmbeddingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:embed-articles',
    description: 'Dispatch embedding jobs for articles that do not have one',
)]
final class EmbedArticlesCommand extends Command
{
    private const int DEFAULT_LIMIT = 200;

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to dispatch (0 = all)', (string) self::DEFAULT_LIMIT);
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show count without dispatching');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $limitStr */
        $limitStr = $input->getOption('limit');
        $limit = (int) $limitStr;
        $dryRun = $input->getOption('dry-run') === true;

        $effectiveLimit = $limit > 0 ? $limit : 10_000;
        $ids = $this->articleRepository->findIdsWithoutEmbeddings($effectiveLimit);

        if ($ids === []) {
            $io->success('No articles need embeddings.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->info(\sprintf('Found %d articles without embeddings (dry run).', \count($ids)));

            return Command::SUCCESS;
        }

        foreach ($ids as $id) {
            $this->messageBus->dispatch(new GenerateEmbeddingMessage($id));
        }

        $io->success(\sprintf('Dispatched %d embedding jobs.', \count($ids)));

        return Command::SUCCESS;
    }
}
