<?php

declare(strict_types=1);

namespace App\Digest\MessageHandler;

use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use App\Digest\Message\GenerateDigestMessage;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Digest\Repository\DigestLogRepositoryInterface;
use App\Digest\Service\DigestGeneratorServiceInterface;
use App\Digest\Service\DigestSummaryServiceInterface;
use App\Digest\ValueObject\GroupedArticles;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

#[AsMessageHandler]
final readonly class GenerateDigestHandler
{
    public function __construct(
        private DigestConfigRepositoryInterface $digestConfigRepository,
        private DigestLogRepositoryInterface $digestLogRepository,
        private DigestGeneratorServiceInterface $generator,
        private DigestSummaryServiceInterface $summary,
        private NotifierInterface $notifier,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateDigestMessage $message): void
    {
        $config = $this->digestConfigRepository->findById($message->digestConfigId);
        if (! $config instanceof DigestConfig) {
            return;
        }

        if (! $message->force && ! $config->isEnabled()) {
            return;
        }

        $groupedArticles = $this->generator->collectArticles($config);
        $totalArticles = $groupedArticles->totalCount();
        $now = $this->clock->now();

        if ($groupedArticles->isEmpty()) {
            $this->logSkippedRun($config, $now, $message->digestConfigId);

            return;
        }

        $articleTitles = $this->extractArticleTitles($groupedArticles);
        $content = $this->summary->generate($groupedArticles);

        $success = $this->sendNotification($config, $totalArticles, $content, $message->digestConfigId);

        $log = new DigestLog($config, $now, $totalArticles, $content, $success);
        $log->setArticleTitles($articleTitles);
        $this->digestLogRepository->save($log);

        $config->setLastRunAt($now);
        $this->digestConfigRepository->flush();

        $this->logger->info('Digest "{name}" generated: {count} articles', [
            'name' => $config->getName(),
            'digest_config_id' => $message->digestConfigId,
            'count' => $totalArticles,
            'delivery_success' => $success,
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractArticleTitles(
        GroupedArticles $groupedArticles,
    ): array {
        $titles = [];
        foreach ($groupedArticles->byCategory as $articles) {
            foreach ($articles as $article) {
                $titles[] = $article->getTitle();
            }
        }

        return $titles;
    }

    private function sendNotification(
        DigestConfig $config,
        int $totalArticles,
        string $content,
        int $digestConfigId,
    ): bool {
        try {
            $notification = new Notification(
                sprintf('[Digest] %s — %d articles', $config->getName(), $totalArticles),
                ['chat'],
            );
            $notification->content($content);
            $this->notifier->send($notification);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Digest delivery failed: {error}', [
                'error' => $e->getMessage(),
                'name' => $config->getName(),
                'digest_config_id' => $digestConfigId,
            ]);

            return false;
        }
    }

    private function logSkippedRun(
        DigestConfig $config,
        \DateTimeImmutable $now,
        int $digestConfigId,
    ): void {
        $log = new DigestLog(
            $config,
            $now,
            0,
            'Skipped: no new articles found since last run.',
            false,
        );
        $log->setArticleTitles([]);
        $this->digestLogRepository->save($log);

        $config->setLastRunAt($now);
        $this->digestConfigRepository->flush();

        $this->logger->info('Digest "{name}" skipped: no articles found', [
            'name' => $config->getName(),
            'digest_config_id' => $digestConfigId,
        ]);
    }
}
