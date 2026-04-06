<?php

declare(strict_types=1);

namespace App\Article\Mercure;

use App\Article\Event\ArticleCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class ArticleCreatedMercureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MercurePublisherServiceInterface $mercurePublisher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ArticleCreated::class => 'onArticleCreated',
        ];
    }

    public function onArticleCreated(ArticleCreated $event): void
    {
        $this->mercurePublisher->publishArticleCreated($event->article);
    }
}
