<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\ValueObject\ArticleCollection;

interface AlertDispatchServiceInterface
{
    public function dispatchAlerts(ArticleCollection $articles): void;
}
