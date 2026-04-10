<?php

declare(strict_types=1);

namespace App\Article\Command;

use App\Article\Message\EnrichArticleMessage;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\ValueObject\EnrichmentStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:backfill-translations',
    description: 'Dispatch enrichment for articles missing translations',
)]
final class BackfillTranslationsCommand extends Command
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to dispatch', '50');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show count without dispatching');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $limitStr */
        $limitStr = $input->getOption('limit');
        $limit = (int) $limitStr;
        $dryRun = $input->getOption('dry-run') === true;

        $articles = $this->articleRepository->findWithoutTranslations($limit);

        if ($articles === []) {
            $io->success('All articles have translations.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d articles without translations (limit: %d).', count($articles), $limit));

        if ($dryRun) {
            $io->note('Dry run — no messages dispatched.');

            return Command::SUCCESS;
        }

        // Reset enrichmentStatus then set to Pending so EnrichArticleHandler will process them
        // (handler skips articles with null status as "legacy complete")
        foreach ($articles as $article) {
            $article->resetEnrichmentStatus();
            $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        }
        $this->articleRepository->flush();

        $dispatched = 0;
        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id === null) {
                continue;
            }

            $this->messageBus->dispatch(new EnrichArticleMessage($id));
            ++$dispatched;
        }

        $io->success(sprintf('Dispatched %d enrichment messages to async_enrich queue.', $dispatched));

        return Command::SUCCESS;
    }
}
