<?php

declare(strict_types=1);

namespace App\Shared\Command;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup',
    description: 'Delete old articles and logs based on retention policy',
)]
final class CleanupCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly int $retentionArticleDays = 90,
        private readonly int $retentionLogDays = 30,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $articleCutoff = $this->clock->now()->modify(sprintf('-%d days', $this->retentionArticleDays));

        // Delete old user_article_read entries first (FK constraint)
        $readDeleted = $this->connection->executeStatement(
            'DELETE FROM user_article_read WHERE read_at < :cutoff',
            [
                'cutoff' => $articleCutoff->format('Y-m-d H:i:s'),
            ],
        );
        $io->info(sprintf('Deleted %d old read states.', (int) $readDeleted));

        // Delete old articles
        $articleDeleted = $this->connection->executeStatement(
            'DELETE FROM article WHERE fetched_at < :cutoff',
            [
                'cutoff' => $articleCutoff->format('Y-m-d H:i:s'),
            ],
        );
        $io->info(sprintf('Deleted %d old articles.', (int) $articleDeleted));

        $logCutoff = $this->clock->now()->modify(sprintf('-%d days', $this->retentionLogDays));

        // Delete old notification logs
        $notificationLogDeleted = $this->connection->executeStatement(
            'DELETE FROM notification_log WHERE sent_at < :cutoff',
            [
                'cutoff' => $logCutoff->format('Y-m-d H:i:s'),
            ],
        );
        $io->info(sprintf('Deleted %d old notification logs.', (int) $notificationLogDeleted));

        // Delete old digest logs
        $digestLogDeleted = $this->connection->executeStatement(
            'DELETE FROM digest_log WHERE generated_at < :cutoff',
            [
                'cutoff' => $logCutoff->format('Y-m-d H:i:s'),
            ],
        );
        $io->info(sprintf('Deleted %d old digest logs.', (int) $digestLogDeleted));

        $io->success(sprintf(
            'Cleanup complete. Retention: %d days articles, %d days logs.',
            $this->retentionArticleDays,
            $this->retentionLogDays,
        ));

        return Command::SUCCESS;
    }
}
