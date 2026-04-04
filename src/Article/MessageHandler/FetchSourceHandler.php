<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\ValueObject\ArticleFingerprint;
use App\Source\Entity\Source;
use App\Source\Exception\FeedFetchException;
use App\Source\Message\FetchSourceMessage;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedParserServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FetchSourceHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeedFetcherServiceInterface $feedFetcher,
        private FeedParserServiceInterface $feedParser,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchSourceMessage $message): void
    {
        $source = $this->entityManager->find(Source::class, $message->sourceId);
        if ($source === null || ! $source->isEnabled()) {
            return;
        }

        try {
            $feedContent = $this->feedFetcher->fetch($source->getFeedUrl());
            $items = $this->feedParser->parse($feedContent);
            $now = $this->clock->now();
            $persisted = 0;

            foreach ($items as $item) {
                if ($this->articleExists($item->url)) {
                    continue;
                }

                $article = new Article($item->title, $item->url, $source, $now);
                $article->setContentRaw($item->contentRaw);
                $article->setContentText($item->contentText);
                $article->setPublishedAt($item->publishedAt);

                if ($item->contentText !== null) {
                    $fingerprint = ArticleFingerprint::fromContent($item->contentText);
                    $article->setFingerprint($fingerprint->value);
                }

                $article->setCategory($source->getCategory());
                $this->entityManager->persist($article);
                $persisted++;
            }

            $source->recordSuccess($now);
            $this->entityManager->flush();

            $this->logger->info('Fetched {source}: {count} new articles from {total} items', [
                'source' => $source->getName(),
                'count' => $persisted,
                'total' => count($items),
            ]);
        } catch (FeedFetchException $e) {
            $source->recordFailure($e->getMessage());
            $this->entityManager->flush();

            $this->logger->warning('Feed fetch failed for {source}: {error}', [
                'source' => $source->getName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function articleExists(string $url): bool
    {
        return $this->entityManager
            ->getRepository(Article::class)
            ->findOneBy([
                'url' => $url,
            ]) !== null;
    }
}
