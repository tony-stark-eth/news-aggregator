<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Entity\NotificationLog;
use App\Notification\Repository\NotificationLogRepositoryInterface;
use App\Notification\ValueObject\AlertUrgency;
use App\Notification\ValueObject\DeliveryStatus;
use App\Notification\ValueObject\EvaluationResult;
use Psr\Clock\ClockInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

final readonly class NotificationDispatchService implements NotificationDispatchServiceInterface
{
    public function __construct(
        private NotifierInterface $notifier,
        private NotificationLogRepositoryInterface $notificationLogRepository,
        private ClockInterface $clock,
        private string $notifierDsn = '',
    ) {
    }

    /**
     * @param list<string> $matchedKeywords
     */
    public function dispatch(
        AlertRule $rule,
        Article $article,
        array $matchedKeywords,
        ?EvaluationResult $evaluation = null,
    ): void {
        $deliveryStatus = $this->sendNotification($rule, $article, $matchedKeywords, $evaluation);

        $matchType = $evaluation instanceof EvaluationResult ? 'ai' : 'keyword';
        $log = new NotificationLog($rule, $article, $matchType, $deliveryStatus === DeliveryStatus::Sent, $this->clock->now());
        $log->setDeliveryStatus($deliveryStatus);
        if ($evaluation instanceof EvaluationResult) {
            $log->setAiSeverity($evaluation->severity);
            $log->setAiExplanation($evaluation->explanation);
            $log->setAiModelUsed($evaluation->modelUsed);
        }

        $this->notificationLogRepository->save($log, flush: true);
    }

    public function hasTransport(): bool
    {
        return $this->notifierDsn !== '' && $this->notifierDsn !== 'null://null';
    }

    /**
     * @param list<string> $matchedKeywords
     */
    private function sendNotification(
        AlertRule $rule,
        Article $article,
        array $matchedKeywords,
        ?EvaluationResult $evaluation,
    ): DeliveryStatus {
        if (! $this->hasTransport()) {
            return DeliveryStatus::Skipped;
        }

        $subject = sprintf('[%s] %s', strtoupper($rule->getUrgency()->value), $article->getTitle());
        $content = $this->buildContent($rule, $article, $matchedKeywords, $evaluation);

        $importance = match ($rule->getUrgency()) {
            AlertUrgency::High => Notification::IMPORTANCE_URGENT,
            AlertUrgency::Medium => Notification::IMPORTANCE_HIGH,
            AlertUrgency::Low => Notification::IMPORTANCE_MEDIUM,
        };

        $notification = new Notification($subject, ['chat']);
        $notification->content($content);
        $notification->importance($importance);

        try {
            $this->notifier->send($notification);

            return DeliveryStatus::Sent;
        } catch (\Throwable) {
            return DeliveryStatus::Failed;
        }
    }

    /**
     * @param list<string> $matchedKeywords
     */
    private function buildContent(
        AlertRule $rule,
        Article $article,
        array $matchedKeywords,
        ?EvaluationResult $evaluation,
    ): string {
        $parts = [
            sprintf('Rule: %s', $rule->getName()),
            sprintf('Keywords: %s', implode(', ', $matchedKeywords)),
            sprintf('URL: %s', $article->getUrl()),
        ];

        if ($article->getSummary() !== null) {
            $parts[] = sprintf('Summary: %s', $article->getSummary());
        }

        if ($evaluation instanceof EvaluationResult) {
            $parts[] = sprintf('AI Severity: %d/10', $evaluation->severity);
            $parts[] = sprintf('AI Analysis: %s', $evaluation->explanation);
        }

        return implode("\n", $parts);
    }
}
