<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\ValueObject\EvaluationResult;

interface AiAlertEvaluationServiceInterface
{
    public function evaluate(Article $article, AlertRule $rule): ?EvaluationResult;
}
