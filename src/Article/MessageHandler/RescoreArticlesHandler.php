<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Message\RescoreArticlesMessage;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\ScoringServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RescoreArticlesHandler
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ScoringServiceInterface $scoringService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RescoreArticlesMessage $message): void
    {
        $offset = 0;
        $count = 0;

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

        $this->logger->info('Rescored {count} articles', [
            'count' => $count,
        ]);
    }
}
