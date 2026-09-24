<?php

namespace App\Scheduling;

use DateTimeImmutable;
use Throwable;

final readonly class TaskRunResult
{
    public function __construct(
        public ScheduledTask $task,
        public TaskRunStatus $status,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $finishedAt,
        public ?Throwable $exception = null,
    ) {}

    public function durationMs(): int
    {
        $start = (float) $this->startedAt->format('U.u');
        $end = (float) $this->finishedAt->format('U.u');

        return max(0, (int) round(($end - $start) * 1000));
    }
}
