<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\DeduplicationServiceInterface;
use App\Article\ValueObject\ArticleCollection;
use App\Article\ValueObject\ArticleFingerprint;
use App\Article\ValueObject\FetchResult;
use App\Article\ValueObject\PersistItemResult;
use App\Enrichment\Service\ArticleEnrichmentServiceInterface;
use App\Notification\Service\AlertDispatchServiceInterface;
use App\Source\Entity\Source;
use App\Source\Exception\FeedFetchException;
use App\Source\Message\FetchSourceMessage;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedItemCollection;
use App\Source\Service\FeedParserServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FetchSourceHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private SourceRepositoryInterface $sourceRepository,
        private EntityManagerInterface $entityManager,
        private FeedFetcherServiceInterface $feedFetcher,
        private FeedParserServiceInterface $feedParser,
        private DeduplicationServiceInterface $deduplication,
        private ArticleEnrichmentServiceInterface $enrichment,
        private AlertDispatchServiceInterface $alertDispatch,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchSourceMessage $message): void
    {
        $source = $this->sourceRepository->findById($message->sourceId);
        if (! $source instanceof Source || ! $source->isEnabled()) {
            return;
        }

        try {
            $feedContent = $this->feedFetcher->fetch($source->getFeedUrl());
            $items = $this->feedParser->parse($feedContent);
            $now = $this->clock->now();

            $result = $this->processItems($items, $source, $message->sourceId, $now);

            if ($result->source instanceof Source) {
                $result->source->recordSuccess($now);
                $this->articleRepository->flush();
                $this->alertDispatch->dispatchAlerts($result->newArticles);
                $this->logger->info('Fetched {source}: {count} new articles from {total} items', [
                    'source' => $result->source->getName(),
                    'count' => $result->persistedCount,
                    'total' => \count($items),
                ]);
            }
        } catch (FeedFetchException $e) {
            $source->recordFailure($e->getMessage());
            $this->sourceRepository->flush();

            $this->logger->warning('Feed fetch failed for {source}: {error}', [
                'source' => $source->getName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processItems(FeedItemCollection $items, Source $source, int $sourceId, \DateTimeImmutable $now): FetchResult
    {
        $persisted = 0;
        $newArticles = [];

        foreach ($items as $item) {
            $fingerprint = $item->contentText !== null
                ? ArticleFingerprint::fromContent($item->contentText)->value
                : null;

            if ($this->deduplication->isDuplicate($item->url, $item->title, $fingerprint)) {
                continue;
            }

            $itemResult = $this->persistItem($item, $source, $sourceId, $now, $fingerprint);
            if (! $itemResult instanceof PersistItemResult) {
                return new FetchResult($persisted, new ArticleCollection($newArticles), null);
            }

            $source = $itemResult->source;
            if ($itemResult->article instanceof Article) {
                $newArticles[] = $itemResult->article;
                $persisted++;
            }
        }

        return new FetchResult($persisted, new ArticleCollection($newArticles), $source);
    }

    private function persistItem(
        FeedItem $item,
        Source $source,
        int $sourceId,
        \DateTimeImmutable $now,
        ?string $fingerprint,
    ): ?PersistItemResult {
        try {
            $article = new Article($item->title, $item->url, $source, $now);
            $article->setContentRaw($item->contentRaw);
            $article->setContentText($item->contentText);
            $article->setPublishedAt($item->publishedAt);
            $article->setFingerprint($fingerprint);

            $this->enrichment->enrich($article, $item, $source);
            $this->articleRepository->save($article, flush: true);

            return new PersistItemResult($article, $source);
        } catch (\Throwable $e) {
            $this->logger->debug('Skipped article "{url}": {error}', [
                'url' => $item->url,
                'error' => $e->getMessage(),
            ]);

            if (! $this->entityManager->isOpen()) {
                return null;
            }

            $this->articleRepository->clear();
            $source = $this->sourceRepository->findById($sourceId);

            return $source instanceof Source ? new PersistItemResult(null, $source) : null;
        }
    }
}
