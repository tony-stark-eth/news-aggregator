<?php

declare(strict_types=1);

namespace App\Chat\Command;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Chat\Service\EmbeddingServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:embed-articles',
    description: 'Backfill embeddings for articles that do not have one',
)]
final class EmbedArticlesCommand extends Command
{
    private const int DEFAULT_BATCH_SIZE = 50;

    private const int SLEEP_BETWEEN_BATCHES_MS = 2000;

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly EmbeddingServiceInterface $embeddingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Articles per batch', (string) self::DEFAULT_BATCH_SIZE);
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to process (0 = all)', '0');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show count without processing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $batchSizeStr */
        $batchSizeStr = $input->getOption('batch-size');
        /** @var string $limitStr */
        $limitStr = $input->getOption('limit');

        $batchSize = (int) $batchSizeStr;
        $limit = (int) $limitStr;
        $dryRun = $input->getOption('dry-run') === true;

        [$processed, $embedded] = $this->processBatches($output, $batchSize, $limit, $dryRun);

        $this->printSummary($io, $processed, $embedded, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * @return array{int, int}
     */
    private function processBatches(OutputInterface $output, int $batchSize, int $limit, bool $dryRun): array
    {
        $offset = 0;
        $processed = 0;
        $embedded = 0;
        $progressBar = $dryRun ? null : new ProgressBar($output);
        $progressBar?->start();

        while (true) {
            $fetchLimit = $this->calculateFetchLimit($batchSize, $limit, $processed);
            if ($fetchLimit <= 0) {
                break;
            }

            $articles = $this->articleRepository->findBatched($fetchLimit, $offset);
            if ($articles === []) {
                break;
            }

            [$batchProcessed, $batchEmbedded] = $this->processBatch($articles, $dryRun, $progressBar);
            $processed += $batchProcessed;
            $embedded += $batchEmbedded;
            $offset += $batchProcessed;

            $this->articleRepository->flush();
            $this->articleRepository->clear();

            $this->sleepBetweenBatches($dryRun, \count($articles), $fetchLimit);
        }

        $progressBar?->finish();

        return [$processed, $embedded];
    }

    /**
     * @param list<Article> $articles
     *
     * @return array{int, int}
     */
    private function processBatch(array $articles, bool $dryRun, ?ProgressBar $progressBar): array
    {
        $processed = 0;
        $embedded = 0;

        foreach ($articles as $article) {
            if ($article->getEmbedding() !== null || $dryRun) {
                $processed++;
                $progressBar?->advance();

                continue;
            }

            $embedding = $this->embeddingService->embed($this->buildEmbeddingText($article));
            if ($embedding !== null) {
                $article->setEmbedding('[' . implode(',', array_map(static fn (float $v): string => (string) $v, $embedding)) . ']');
                $embedded++;
            }

            $processed++;
            $progressBar?->advance();
        }

        return [$processed, $embedded];
    }

    private function calculateFetchLimit(int $batchSize, int $limit, int $processed): int
    {
        return $limit > 0 ? min($batchSize, $limit - $processed) : $batchSize;
    }

    private function sleepBetweenBatches(bool $dryRun, int $articleCount, int $fetchLimit): void
    {
        if (! $dryRun && $articleCount === $fetchLimit) {
            usleep(self::SLEEP_BETWEEN_BATCHES_MS * 1000);
        }
    }

    private function printSummary(SymfonyStyle $io, int $processed, int $embedded, bool $dryRun): void
    {
        $io->newLine(2);

        if ($dryRun) {
            $io->info(sprintf('Found %d articles without embeddings (dry run).', $processed));
        } else {
            $io->success(sprintf('Processed %d articles, generated %d embeddings.', $processed, $embedded));
        }
    }

    private function buildEmbeddingText(Article $article): string
    {
        $parts = [$article->getTitle()];

        $summary = $article->getSummary();
        if ($summary !== null && $summary !== '') {
            $parts[] = $summary;
        }

        $keywords = $article->getKeywords();
        if ($keywords !== null && $keywords !== []) {
            $parts[] = implode(' ', $keywords);
        }

        return implode(' ', $parts);
    }
}
