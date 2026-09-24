<?php

namespace App\Scheduling\Contracts;

use App\Scheduling\Exceptions\DuplicateTaskException;
use App\Scheduling\Exceptions\TaskNotFoundException;
use App\Scheduling\RunReport;
use App\Scheduling\ScheduledTask;
use App\Scheduling\TaskRunResult;
use DateTimeImmutable;

/**
 * Framework-agnostic task scheduler.
 *
 * Implementations (adapters) are responsible for cron evaluation only; the actual
 * execution, locking and error handling is delegated to the shared TaskRunner.
 */
interface SchedulerInterface
{
    /**
     * Register a task definition.
     *
     * @throws DuplicateTaskException When a task with the same name is already registered.
     */
    public function schedule(ScheduledTask $task): void;

    /**
     * All registered tasks in registration order.
     *
     * @return list<ScheduledTask>
     */
    public function tasks(): array;

    /**
     * @param  non-empty-string  $name
     *
     * @throws TaskNotFoundException
     */
    public function task(string $name): ScheduledTask;

    /**
     * Tasks whose cron expression matches the minute of $now (evaluated in the task's timezone).
     *
     * @return list<ScheduledTask>
     */
    public function dueTasks(DateTimeImmutable $now): array;

    /**
     * Run every due task sequentially, each under the mutex. Task exceptions are
     * captured into the report and never propagate.
     */
    public function run(DateTimeImmutable $now): RunReport;

    /**
     * Run a single task by name regardless of its cron expression. The mutex is still honoured.
     *
     * @param  non-empty-string  $name
     *
     * @throws TaskNotFoundException
     */
    public function runTask(string $name, DateTimeImmutable $now): TaskRunResult;
}
