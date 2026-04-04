<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\ValueObject\MatchResultCollection;

interface ArticleMatcherServiceInterface
{
    public function match(Article $article): MatchResultCollection;
}
