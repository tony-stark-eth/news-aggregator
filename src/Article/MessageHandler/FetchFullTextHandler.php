<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Message\EnrichArticleMessage;
use App\Article\Message\FetchFullTextMessage;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\ArticleContentFetcherServiceInterface;
use App\Article\Service\DomainRateLimiterServiceInterface;
use App\Article\Service\ReadabilityExtractorServiceInterface;
use App\Article\ValueObject\FullTextStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class FetchFullTextHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ArticleContentFetcherServiceInterface $contentFetcher,
        private ReadabilityExtractorServiceInterface $readabilityExtractor,
        private DomainRateLimiterServiceInterface $domainRateLimiter,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        #[Autowire('%env(bool:FULL_TEXT_FETCH_ENABLED)%')]
        private bool $fullTextEnabled,
    ) {
    }

    public function __invoke(FetchFullTextMessage $message): void
    {
        $article = $this->articleRepository->findById($message->articleId);
        if (! $article instanceof Article) {
            return;
        }

        if ($article->getFullTextStatus() !== FullTextStatus::Pending) {
            return;
        }

        if (! $this->fullTextEnabled) {
            $this->skipArticle($article);

            return;
        }

        if (! $article->getSource()->isFullTextEnabled()) {
            $this->skipArticle($article);

            return;
        }

        $this->fetchAndExtract($article);
    }

    private function skipArticle(Article $article): void
    {
        $article->setFullTextStatus(FullTextStatus::Skipped);
        $this->articleRepository->flush();
        $this->dispatchEnrich($article);
    }

    private function fetchAndExtract(Article $article): void
    {
        try {
            $this->domainRateLimiter->waitForDomain($article->getUrl());
            $html = $this->contentFetcher->fetch($article->getUrl());
            $result = $this->readabilityExtractor->extract($html, $article->getUrl());

            if ($result->success) {
                $article->setContentFullText($result->textContent);
                $article->setContentFullHtml($result->htmlContent);
                $article->setContentText($result->textContent);
                $article->setFullTextStatus(FullTextStatus::Fetched);

                $this->logger->info('Full-text fetched for article {id}', [
                    'id' => $article->getId(),
                    'url' => $article->getUrl(),
                ]);
            } else {
                $article->setFullTextStatus(FullTextStatus::Failed);

                $this->logger->debug('Full-text extraction failed for article {id}', [
                    'id' => $article->getId(),
                    'url' => $article->getUrl(),
                ]);
            }
        } catch (\Throwable $e) {
            $article->setFullTextStatus(FullTextStatus::Failed);

            $this->logger->warning('Full-text fetch error for article {id}: {error}', [
                'id' => $article->getId(),
                'url' => $article->getUrl(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->articleRepository->flush();
        $this->dispatchEnrich($article);
    }

    private function dispatchEnrich(Article $article): void
    {
        $articleId = $article->getId();
        if ($articleId !== null) {
            $this->messageBus->dispatch(new EnrichArticleMessage($articleId));
        }
    }
}
