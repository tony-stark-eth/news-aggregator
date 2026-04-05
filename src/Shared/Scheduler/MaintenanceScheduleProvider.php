<?php

declare(strict_types=1);

namespace App\Shared\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('maintenance')]
final class MaintenanceScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        $schedule->add(
            RecurringMessage::every('1 day', new RunCommandMessage('app:search-reindex')),
        );

        $schedule->add(
            RecurringMessage::every('1 day', new RunCommandMessage('app:cleanup')),
        );

        return $schedule;
    }
}
