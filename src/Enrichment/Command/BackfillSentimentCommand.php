<?php

declare(strict_types=1);

namespace App\Enrichment\Command;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Enrichment\Service\SentimentScoringServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-sentiment',
    description: 'Score existing articles without sentiment using rule-based analysis',
)]
final class BackfillSentimentCommand extends Command
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly SentimentScoringServiceInterface $sentimentScoring,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to process', '500');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show count without scoring');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $limitStr */
        $limitStr = $input->getOption('limit');
        $limit = (int) $limitStr;
        $dryRun = $input->getOption('dry-run') === true;

        $articles = $this->articleRepository->findWithoutSentiment($limit);

        if ($articles === []) {
            $io->success('All articles have sentiment scores.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d articles without sentiment (limit: %d).', \count($articles), $limit));

        if ($dryRun) {
            $io->note('Dry run — no articles scored.');

            return Command::SUCCESS;
        }

        $scored = 0;
        $skipped = 0;

        foreach ($articles as $article) {
            $score = $this->sentimentScoring->score(
                $article->getTitle(),
                $article->getContentText() ?? $article->getContentFullText(),
            );

            if ($score !== null) {
                $article->setSentimentScore($score);
                ++$scored;
            } else {
                ++$skipped;
            }
        }

        $this->articleRepository->flush();

        $io->success(sprintf('Scored %d articles, %d skipped (no sentiment keywords).', $scored, $skipped));

        return Command::SUCCESS;
    }
}
