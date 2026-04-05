<?php

declare(strict_types=1);

namespace App\Shared\Search\Command;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:search-reindex', description: 'Reindex all articles into the search engine')]
final class SearchReindexCommand extends Command
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ArticleSearchServiceInterface $searchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $offset = 0;
        $count = 0;

        do {
            $articles = $this->articleRepository->findBatched(self::BATCH_SIZE, $offset);

            foreach ($articles as $article) {
                $this->searchService->index($article);
                $count++;
            }

            $this->articleRepository->clear();
            $offset += self::BATCH_SIZE;
        } while (\count($articles) === self::BATCH_SIZE);

        $io->success(sprintf('Reindexed %d articles.', $count));

        return Command::SUCCESS;
    }
}
