<?php

declare(strict_types=1);

namespace App\Notification\EventSubscriber;

use App\Article\Event\ArticleCreated;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Service\ArticleMatcherServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ArticleCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ArticleMatcherServiceInterface $articleMatcher,
        private MessageBusInterface $messageBus,
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
        $article = $event->article;
        $articleId = $article->getId();
        if ($articleId === null) {
            return;
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
