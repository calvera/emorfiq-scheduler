<?php

namespace App\Scheduling;

use App\Scheduling\Contracts\SchedulerInterface;

/**
 * The single place where the application declares its scheduled tasks.
 *
 * Keep this class free of framework code so the definitions survive a switch
 * of the scheduler adapter.
 */
final class ScheduleDefinition
{
    public function register(SchedulerInterface $scheduler): void
    {
        // Example:
        //
        // $scheduler->schedule(new ScheduledTask(
        //     name: 'reports.daily',
        //     expression: CronExpression::fromString('0 3 * * *'),
        //     taskClass: GenerateDailyReportTask::class,
        //     withoutOverlapping: true,
        //     lockTtlSeconds: 1800,
        //     description: 'Generate the daily report',
        // ));
    }
}
