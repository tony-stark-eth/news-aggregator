<?php

declare(strict_types=1);

namespace App\Shared\Search\EventListener;

use App\Article\Entity\Article;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, entity: Article::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Article::class)]
#[AsEntityListener(event: Events::preRemove, entity: Article::class)]
final readonly class ArticleIndexListener
{
    public function __construct(
        private ArticleSearchServiceInterface $searchService,
        private LoggerInterface $logger,
    ) {
    }

    public function postPersist(Article $article): void
    {
        $this->indexSafely($article, 'postPersist');
    }

    public function postUpdate(Article $article): void
    {
        $this->indexSafely($article, 'postUpdate');
    }

    public function preRemove(Article $article): void
    {
        $id = $article->getId();
        if ($id === null) {
            return;
        }

        try {
            $this->searchService->remove($id);
        } catch (\Throwable $e) {
            $this->logger->warning('Search de-index failed for article {id}: {error}', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function indexSafely(Article $article, string $event): void
    {
        try {
            $this->searchService->index($article);
        } catch (\Throwable $e) {
            $this->logger->warning('Search index failed during {event} for article "{title}": {error}', [
                'event' => $event,
                'title' => $article->getTitle(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
