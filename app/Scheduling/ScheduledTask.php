<?php

namespace App\Scheduling;

use App\Scheduling\Contracts\TaskInterface;
use App\Scheduling\Exceptions\SchedulerException;
use DateTimeZone;

/**
 * Immutable definition of a scheduled task.
 */
final readonly class ScheduledTask
{
    /**
     * @param  non-empty-string  $name  Unique identifier; also used as the mutex key.
     * @param  class-string<TaskInterface>  $taskClass  Resolved from the container at execution time.
     * @param  bool  $withoutOverlapping  Guard execution with MutexInterface.
     * @param  positive-int  $lockTtlSeconds  After this the lock is treated as abandoned (crash protection).
     * @param  DateTimeZone|null  $timezone  Null means the scheduler's default timezone.
     *
     * @throws SchedulerException On an empty name or invalid task class / TTL.
     */
    public function __construct(
        public string $name,
        public CronExpression $expression,
        public string $taskClass,
        public bool $withoutOverlapping = true,
        public int $lockTtlSeconds = 3600,
        public ?DateTimeZone $timezone = null,
        public ?string $description = null,
    ) {
        if (trim($name) === '') {
            throw new SchedulerException('A scheduled task must have a non-empty name.');
        }

        if (! is_a($taskClass, TaskInterface::class, true)) {
            throw new SchedulerException(sprintf(
                'Task class "%s" for scheduled task "%s" must implement %s.',
                $taskClass,
                $name,
                TaskInterface::class,
            ));
        }

        if ($lockTtlSeconds < 1) {
            throw new SchedulerException(sprintf(
                'Lock TTL for scheduled task "%s" must be a positive number of seconds, got %d.',
                $name,
                $lockTtlSeconds,
            ));
        }
    }
}
