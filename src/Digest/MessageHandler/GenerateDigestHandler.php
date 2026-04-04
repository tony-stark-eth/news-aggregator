<?php

declare(strict_types=1);

namespace App\Digest\MessageHandler;

use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use App\Digest\Message\GenerateDigestMessage;
use App\Digest\Service\DigestGeneratorService;
use App\Digest\Service\DigestSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

#[AsMessageHandler]
final readonly class GenerateDigestHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DigestGeneratorService $generator,
        private DigestSummaryService $summary,
        private NotifierInterface $notifier,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateDigestMessage $message): void
    {
        $config = $this->entityManager->find(DigestConfig::class, $message->digestConfigId);
        if ($config === null || ! $config->isEnabled()) {
            return;
        }

        $groupedArticles = $this->generator->collectArticles($config);
        $totalArticles = array_sum(array_map('count', $groupedArticles));

        if ($totalArticles === 0) {
            $this->logger->info('Digest "{name}" skipped: no articles found', [
                'name' => $config->getName(),
            ]);
            return;
        }

        $content = $this->summary->generate($groupedArticles);
        $now = $this->clock->now();

        // Send notification
        $success = true;
        try {
            $notification = new Notification(
                sprintf('[Digest] %s — %d articles', $config->getName(), $totalArticles),
                ['chat'],
            );
            $notification->content($content);
            $this->notifier->send($notification);
        } catch (\Throwable $e) {
            $success = false;
            $this->logger->warning('Digest delivery failed: {error}', [
                'error' => $e->getMessage(),
            ]);
        }

        // Log
        $log = new DigestLog($config, $now, $totalArticles, $content, $success);
        $this->entityManager->persist($log);

        $config->setLastRunAt($now);
        $this->entityManager->flush();

        $this->logger->info('Digest "{name}" generated: {count} articles', [
            'name' => $config->getName(),
            'count' => $totalArticles,
        ]);
    }
}
