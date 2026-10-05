<?php

namespace App\Scheduling;

use App\Scheduling\Contracts\SchedulerInterface;

/**
 * Application scheduled-task registrations.
 */
final class ScheduleDefinition
{
    public function register(SchedulerInterface $scheduler): void
    {
        // Example:
        //
        // $scheduler->schedule(new ScheduledTask(
        //     name: 'reports.daily',
        //     expression: new CronExpression('0 3 * * *'),
        //     taskClass: GenerateDailyReportTask::class,
        //     withoutOverlapping: true,
        //     lockTtlSeconds: 1800,
        //     description: 'Generate the daily report',
        // ));
    }
}
