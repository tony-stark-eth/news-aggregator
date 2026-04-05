<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Service\DeduplicationServiceInterface;
use App\Article\Service\ScoringServiceInterface;
use App\Article\ValueObject\ArticleCollection;
use App\Article\ValueObject\ArticleFingerprint;
use App\Article\ValueObject\FetchResult;
use App\Article\ValueObject\PersistItemResult;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\Service\TranslationServiceInterface;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Service\ArticleMatcherServiceInterface;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Exception\FeedFetchException;
use App\Source\Message\FetchSourceMessage;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedItemCollection;
use App\Source\Service\FeedParserServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class FetchSourceHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeedFetcherServiceInterface $feedFetcher,
        private FeedParserServiceInterface $feedParser,
        private DeduplicationServiceInterface $deduplication,
        private CategorizationServiceInterface $categorization,
        private SummarizationServiceInterface $summarization,
        private TranslationServiceInterface $translation,
        private KeywordExtractionServiceInterface $keywordExtraction,
        private ScoringServiceInterface $scoring,
        private ArticleMatcherServiceInterface $articleMatcher,
        private MessageBusInterface $messageBus,
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

            $result = $this->processItems($items, $source, $message->sourceId, $now);

            if ($result->source instanceof Source) {
                $result->source->recordSuccess($now);
                $this->entityManager->flush();
                $this->dispatchAlerts($result->newArticles);
                $this->logger->info('Fetched {source}: {count} new articles from {total} items', [
                    'source' => $result->source->getName(),
                    'count' => $result->persistedCount,
                    'total' => \count($items),
                ]);
            }
        } catch (FeedFetchException $e) {
            $source->recordFailure($e->getMessage());
            $this->entityManager->flush();

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

    /**
     * Attempts to build and persist one article. Returns null if the EM is
     * irrecoverably broken and the loop must abort, or a PersistItemResult
     * otherwise (article is null when the item was skipped due to an error).
     */
    private function persistItem(
        FeedItem $item,
        Source $source,
        int $sourceId,
        \DateTimeImmutable $now,
        ?string $fingerprint,
    ): ?PersistItemResult {
        try {
            $article = $this->buildArticle($item, $source, $now, $fingerprint);
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            return new PersistItemResult($article, $source);
        } catch (\Throwable $e) {
            $this->logger->debug('Skipped article "{url}": {error}', [
                'url' => $item->url,
                'error' => $e->getMessage(),
            ]);

            if (! $this->entityManager->isOpen()) {
                return null;
            }

            $this->entityManager->clear();
            $source = $this->entityManager->find(Source::class, $sourceId);

            return $source !== null ? new PersistItemResult(null, $source) : null;
        }
    }

    private function buildArticle(
        FeedItem $item,
        Source $source,
        \DateTimeImmutable $now,
        ?string $fingerprint,
    ): Article {
        $article = new Article($item->title, $item->url, $source, $now);
        $article->setContentRaw($item->contentRaw);
        $article->setContentText($item->contentText);
        $article->setPublishedAt($item->publishedAt);
        $article->setFingerprint($fingerprint);

        $catResult = $this->categorization->categorize($item->title, $item->contentText);
        $this->applyCategory($article, $catResult, $source);

        if ($item->contentText !== null) {
            $sumResult = $this->summarization->summarize($item->contentText);
            $this->applyEnrichment($article, $catResult, $sumResult);
        }

        $keywords = $this->keywordExtraction->extract($item->title, $item->contentText);
        if ($keywords !== []) {
            $article->setKeywords($keywords);
        }

        $this->applyTranslation($article, $source);

        $article->setScore($this->scoring->score($article));

        return $article;
    }

    private function applyCategory(Article $article, EnrichmentResult $catResult, Source $source): void
    {
        if ($catResult->value !== null) {
            $category = $this->entityManager
                ->getRepository(Category::class)
                ->findOneBy([
                    'slug' => $catResult->value,
                ]);
            if ($category !== null) {
                $article->setCategory($category);
            }
        }

        if (! $article->getCategory() instanceof Category) {
            $article->setCategory($source->getCategory());
        }
    }

    private function applyEnrichment(Article $article, EnrichmentResult $catResult, EnrichmentResult $sumResult): void
    {
        if ($sumResult->value === null) {
            return;
        }

        $article->setSummary($sumResult->value);

        $aiResult = $sumResult->method === EnrichmentMethod::Ai ? $sumResult : null;
        $aiResult ??= $catResult->method === EnrichmentMethod::Ai ? $catResult : null;

        $article->setEnrichmentMethod($aiResult instanceof EnrichmentResult ? EnrichmentMethod::Ai : EnrichmentMethod::RuleBased);
        $article->setAiModelUsed($aiResult?->modelUsed);
    }

    private function applyTranslation(Article $article, Source $source): void
    {
        $language = $source->getLanguage();
        if ($language === null || $language === 'en') {
            return;
        }

        $article->setTitleOriginal($article->getTitle());
        $translated = $this->translation->translate($article->getTitle(), $language, 'en');
        $article->setTitle($translated);

        $summary = $article->getSummary();
        if ($summary !== null) {
            $article->setSummaryOriginal($summary);
            $translatedSummary = $this->translation->translate($summary, $language, 'en');
            $article->setSummary($translatedSummary);
        }
    }

    private function dispatchAlerts(ArticleCollection $articles): void
    {
        foreach ($articles as $article) {
            $articleId = $article->getId();
            if ($articleId === null) {
                continue;
            }

            $matches = $this->articleMatcher->match($article);
            foreach ($matches as $match) {
                $ruleId = $match->alertRule->getId();
                if ($ruleId === null) {
                    continue;
                }

                $this->messageBus->dispatch(
                    new SendNotificationMessage($ruleId, $articleId, $match->matchedKeywords),
                );
            }
        }
    }
}
