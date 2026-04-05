<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\ValueObject\ArticleCollection;
use App\Notification\Message\SendNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AlertDispatchService implements AlertDispatchServiceInterface
{
    public function __construct(
        private ArticleMatcherServiceInterface $articleMatcher,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function dispatchAlerts(ArticleCollection $articles): void
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
