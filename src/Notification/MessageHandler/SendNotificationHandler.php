<?php

declare(strict_types=1);

namespace App\Notification\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Notification\Entity\AlertRule;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\Service\AiAlertEvaluationServiceInterface;
use App\Notification\Service\NotificationDispatchServiceInterface;
use App\Notification\ValueObject\EvaluationResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendNotificationHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $alertRuleRepository,
        private ArticleRepositoryInterface $articleRepository,
        private NotificationDispatchServiceInterface $dispatchService,
        private AiAlertEvaluationServiceInterface $aiEvaluationService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $rule = $this->alertRuleRepository->findById($message->alertRuleId);
        $article = $this->articleRepository->findById($message->articleId);

        if (! $rule instanceof AlertRule || ! $article instanceof Article || ! $rule->isEnabled()) {
            return;
        }

        $evaluation = null;
        if ($rule->requiresAiEvaluation()) {
            $evaluation = $this->aiEvaluationService->evaluate($article, $rule);

            // Skip notification if AI severity is below threshold
            if ($evaluation instanceof EvaluationResult && $evaluation->severity < $rule->getSeverityThreshold()) {
                $this->logger->info('Alert "{rule}" skipped: AI severity {severity} < threshold {threshold}', [
                    'rule' => $rule->getName(),
                    'rule_id' => $rule->getId(),
                    'article_id' => $article->getId(),
                    'severity' => $evaluation->severity,
                    'threshold' => $rule->getSeverityThreshold(),
                ]);

                return;
            }
        }

        $this->dispatchService->dispatch($rule, $article, $message->matchedKeywords, $evaluation);

        $this->logger->info('Notification sent for alert "{rule}" on article "{article}"', [
            'rule' => $rule->getName(),
            'rule_id' => $rule->getId(),
            'article' => $article->getTitle(),
            'article_id' => $article->getId(),
        ]);
    }
}
