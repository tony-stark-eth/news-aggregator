<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Mercure\MercurePublisherServiceInterface;
use App\Article\Message\EnrichArticleMessage;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\ValueObject\EnrichmentStatus;
use App\Enrichment\Service\ArticleEnrichmentServiceInterface;
use App\Source\Service\FeedItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnrichArticleHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ArticleEnrichmentServiceInterface $enrichment,
        private MercurePublisherServiceInterface $mercurePublisher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EnrichArticleMessage $message): void
    {
        $article = $this->articleRepository->findById($message->articleId);
        if (! $article instanceof Article) {
            $this->logger->warning('EnrichArticleHandler: article {id} not found', [
                'id' => $message->articleId,
            ]);

            return;
        }

        if ($this->isAlreadyComplete($article)) {
            $this->logger->debug('EnrichArticleHandler: article {id} already complete, skipping', [
                'id' => $message->articleId,
            ]);

            return;
        }

        $item = $this->reconstructFeedItem($article);

        $this->enrichment->enrich($article, $item, $article->getSource());

        $article->setEnrichmentStatus(EnrichmentStatus::Complete);
        $this->articleRepository->flush();

        $this->mercurePublisher->publishEnrichmentComplete($article);

        $this->logger->info('EnrichArticleHandler: enrichment complete for article {id}', [
            'id' => $message->articleId,
        ]);
    }

    private function isAlreadyComplete(Article $article): bool
    {
        $status = $article->getEnrichmentStatus();

        return $status === EnrichmentStatus::Complete || ! $status instanceof EnrichmentStatus;
    }

    private function reconstructFeedItem(Article $article): FeedItem
    {
        return new FeedItem(
            $article->getTitleOriginal() ?? $article->getTitle(),
            $article->getUrl(),
            $article->getContentRaw(),
            $article->getContentText(),
            $article->getPublishedAt(),
        );
    }
}
