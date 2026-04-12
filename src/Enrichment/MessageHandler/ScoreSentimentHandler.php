<?php

declare(strict_types=1);

namespace App\Enrichment\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Enrichment\Message\ScoreSentimentMessage;
use App\Enrichment\Service\SentimentScoringServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ScoreSentimentHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private SentimentScoringServiceInterface $sentimentScoring,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScoreSentimentMessage $message): void
    {
        $article = $this->articleRepository->findById($message->articleId);
        if (! $article instanceof Article) {
            $this->logger->warning('Article not found for sentiment scoring', [
                'article_id' => $message->articleId,
            ]);

            return;
        }

        if ($article->getSentimentScore() !== null) {
            return;
        }

        $score = $this->sentimentScoring->score(
            $article->getTitle(),
            $article->getContentText() ?? $article->getContentFullText(),
        );

        if ($score === null) {
            return;
        }

        $article->setSentimentScore($score);
        $this->articleRepository->save($article, flush: true);

        $this->logger->debug('Scored sentiment for article {id}: {score}', [
            'id' => $message->articleId,
            'score' => $score,
        ]);
    }
}
