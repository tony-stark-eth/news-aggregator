<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\ValueObject\EvaluationResult;

interface NotificationDispatchServiceInterface
{
    /**
     * @param list<string> $matchedKeywords
     */
    public function dispatch(
        AlertRule $rule,
        Article $article,
        array $matchedKeywords,
        ?EvaluationResult $evaluation = null,
    ): void;
}
