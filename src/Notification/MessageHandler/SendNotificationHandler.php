<?php

declare(strict_types=1);

namespace App\Notification\MessageHandler;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\Service\AiAlertEvaluationService;
use App\Notification\Service\NotificationDispatchService;
use App\Notification\ValueObject\EvaluationResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationDispatchService $dispatchService,
        private AiAlertEvaluationService $aiEvaluationService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $rule = $this->entityManager->find(AlertRule::class, $message->alertRuleId);
        $article = $this->entityManager->find(Article::class, $message->articleId);

        if ($rule === null || $article === null || ! $rule->isEnabled()) {
            return;
        }

        $evaluation = null;
        if ($rule->requiresAiEvaluation()) {
            $evaluation = $this->aiEvaluationService->evaluate($article, $rule);

            // Skip notification if AI severity is below threshold
            if ($evaluation instanceof EvaluationResult && $evaluation->severity < $rule->getSeverityThreshold()) {
                $this->logger->info('Alert "{rule}" skipped: AI severity {severity} < threshold {threshold}', [
                    'rule' => $rule->getName(),
                    'severity' => $evaluation->severity,
                    'threshold' => $rule->getSeverityThreshold(),
                ]);

                return;
            }
        }

        $this->dispatchService->dispatch($rule, $article, $message->matchedKeywords, $evaluation);

        $this->logger->info('Notification sent for alert "{rule}" on article "{article}"', [
            'rule' => $rule->getName(),
            'article' => $article->getTitle(),
        ]);
    }
}
