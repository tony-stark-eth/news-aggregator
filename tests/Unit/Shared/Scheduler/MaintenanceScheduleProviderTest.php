<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Scheduler;

use App\Shared\Scheduler\MaintenanceScheduleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

#[CoversClass(MaintenanceScheduleProvider::class)]
final class MaintenanceScheduleProviderTest extends TestCase
{
    public function testScheduleContainsFourRecurringMessages(): void
    {
        $provider = new MaintenanceScheduleProvider();
        $schedule = $provider->getSchedule();

        self::assertCount(4, $schedule->getRecurringMessages());
    }

    public function testScheduleIncludesAllMaintenanceTasks(): void
    {
        $provider = new MaintenanceScheduleProvider();
        $schedule = $provider->getSchedule();

        $commands = $this->extractCommandInputs($schedule->getRecurringMessages());

        self::assertContains('app:search-reindex', $commands);
        self::assertContains('app:cleanup', $commands);
        self::assertContains('app:embed-articles --limit=500', $commands);
        self::assertContains('app:backfill-sentiment --limit=500', $commands);
    }

    /**
     * @param array<RecurringMessage> $recurringMessages
     *
     * @return list<string>
     */
    private function extractCommandInputs(array $recurringMessages): array
    {
        $context = new MessageContext(
            'test',
            'test-id',
            new PeriodicalTrigger('1 day'),
            new \DateTimeImmutable(),
        );

        $commands = [];
        foreach ($recurringMessages as $recurring) {
            foreach ($recurring->getMessages($context) as $message) {
                if ($message instanceof RunCommandMessage) {
                    $commands[] = $message->input;
                }
            }
        }

        return $commands;
    }
}
