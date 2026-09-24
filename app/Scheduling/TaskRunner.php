<?php

namespace App\Scheduling;

use App\Scheduling\Contracts\LockInterface;
use App\Scheduling\Contracts\MutexInterface;
use App\Scheduling\Contracts\TaskInterface;
use App\Scheduling\Exceptions\SchedulerException;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Executes a single ScheduledTask: acquires the mutex, resolves the task from the
 * container, runs it and captures the outcome. Depends on PSR interfaces only.
 */
final class TaskRunner
{
    public function __construct(
        private readonly MutexInterface $mutex,
        private readonly ContainerInterface $container,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(ScheduledTask $task): TaskRunResult
    {
        $startedAt = $this->clock->now();
        $lock = null;

        if ($task->withoutOverlapping) {
            $lock = $this->mutex->acquire($task->name, $task->lockTtlSeconds);

            if ($lock === null) {
                $this->logger->info('Scheduled task skipped: lock is held by another process.', [
                    'task' => $task->name,
                ]);

                return new TaskRunResult($task, TaskRunStatus::SkippedLocked, $startedAt, $this->clock->now());
            }
        }

        try {
            $this->resolveTask($task)->execute();

            return new TaskRunResult($task, TaskRunStatus::Succeeded, $startedAt, $this->clock->now());
        } catch (Throwable $exception) {
            $this->logger->error('Scheduled task failed.', [
                'task' => $task->name,
                'task_class' => $task->taskClass,
                'exception' => $exception,
            ]);

            return new TaskRunResult($task, TaskRunStatus::Failed, $startedAt, $this->clock->now(), $exception);
        } finally {
            $this->releaseQuietly($lock, $task);
        }
    }

    /**
     * @throws SchedulerException When the container does not yield a TaskInterface instance.
     */
    private function resolveTask(ScheduledTask $task): TaskInterface
    {
        $instance = $this->container->get($task->taskClass);

        if (! $instance instanceof TaskInterface) {
            throw new SchedulerException(sprintf(
                'Container resolved "%s" for task "%s", but it does not implement %s.',
                get_debug_type($instance),
                $task->name,
                TaskInterface::class,
            ));
        }

        return $instance;
    }

    private function releaseQuietly(?LockInterface $lock, ScheduledTask $task): void
    {
        if ($lock === null) {
            return;
        }

        try {
            $lock->release();
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to release scheduled task lock.', [
                'task' => $task->name,
                'exception' => $exception,
            ]);
        }
    }
}
