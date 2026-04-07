<?php

declare(strict_types=1);

namespace App\Source\Scheduler;

use App\Source\Message\FetchSourceMessage;
use App\Source\Repository\SourceRepositoryInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('fetch')]
final class FetchScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly int $defaultIntervalMinutes = 15,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        $sources = $this->sourceRepository->findEnabled();

        foreach ($sources as $source) {
            $id = $source->getId();
            if ($id === null) {
                continue;
            }

            $intervalMinutes = $source->getFetchIntervalMinutes()
                ?? $source->getCategory()->getFetchIntervalMinutes()
                ?? $this->defaultIntervalMinutes;

            $schedule->add(
                RecurringMessage::every(sprintf('%d minutes', $intervalMinutes), new FetchSourceMessage($id)),
            );
        }

        return $schedule;
    }
}
