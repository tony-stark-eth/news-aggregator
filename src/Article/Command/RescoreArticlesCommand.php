<?php

declare(strict_types=1);

namespace App\Article\Command;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\ScoringServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rescore-articles',
    description: 'Recalculate scores for all articles',
)]
final class RescoreArticlesCommand extends Command
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ScoringServiceInterface $scoringService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = 0;
        $offset = 0;

        do {
            $articles = $this->articleRepository->findBatched(self::BATCH_SIZE, $offset);

            foreach ($articles as $article) {
                $article->setScore($this->scoringService->score($article));
                $count++;
            }

            $this->articleRepository->flush();
            $this->articleRepository->clear();
            $offset += self::BATCH_SIZE;
        } while (\count($articles) === self::BATCH_SIZE);

        $io->success(sprintf('Rescored %d articles.', $count));

        return Command::SUCCESS;
    }
}
