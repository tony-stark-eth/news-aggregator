<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class QueueDepthService implements QueueDepthServiceInterface
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function getEnrichQueueDepth(): int
    {
        try {
            /** @var int|string|false $count */
            $count = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue',
                [
                    'queue' => 'enrich',
                ],
            );

            return is_numeric($count) ? (int) $count : 0;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to query enrich queue depth: {error}', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
