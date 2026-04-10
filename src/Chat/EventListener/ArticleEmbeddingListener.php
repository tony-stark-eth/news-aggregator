<?php

declare(strict_types=1);

namespace App\Chat\EventListener;

use App\Article\Entity\Article;
use App\Chat\Message\GenerateEmbeddingMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, entity: Article::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Article::class)]
final readonly class ArticleEmbeddingListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function postPersist(Article $article): void
    {
        $this->dispatchIfNeeded($article, 'postPersist');
    }

    public function postUpdate(Article $article): void
    {
        $this->dispatchIfNeeded($article, 'postUpdate');
    }

    private function dispatchIfNeeded(Article $article, string $event): void
    {
        $id = $article->getId();
        if ($id === null) {
            return;
        }

        // Skip if already has embedding
        if ($article->getEmbedding() !== null) {
            return;
        }

        try {
            $this->messageBus->dispatch(new GenerateEmbeddingMessage($id));
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to dispatch embedding message during {event} for article {id}: {error}', [
                'event' => $event,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
