<?php

namespace App\Console\Commands;

use App\Scheduling\Contracts\SchedulerInterface;
use App\Scheduling\Exceptions\TaskNotFoundException;
use App\Scheduling\RunReport;
use App\Scheduling\ScheduledTask;
use App\Scheduling\TaskRunResult;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Psr\Clock\ClockInterface;

#[Signature('app:scheduler:run
    {--task= : Run only the named task, regardless of its cron expression}
    {--dry-run : List the tasks that are due without running them}')]
#[Description('Run the scheduled tasks that are due at the current minute')]
class SchedulerRunCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SchedulerInterface $scheduler, ClockInterface $clock): int
    {
        $now = $clock->now();

        $taskName = $this->option('task');

        if (is_string($taskName) && $taskName !== '') {
            return $this->runSingleTask($scheduler, $taskName, $now);
        }

        if ($this->option('dry-run')) {
            return $this->listDueTasks($scheduler->dueTasks($now));
        }

        $report = $scheduler->run($now);

        if ($report->count() === 0) {
            $this->components->info('No scheduled tasks are due.');

            return self::SUCCESS;
        }

        $this->renderReport($report);

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function runSingleTask(SchedulerInterface $scheduler, string $taskName, DateTimeImmutable $now): int
    {
        try {
            $task = $scheduler->task($taskName);
        } catch (TaskNotFoundException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        if ($this->option('dry-run')) {
            return $this->listDueTasks([$task]);
        }

        $result = $scheduler->runTask($taskName, $now);

        $this->renderResults([$result]);

        return $result->status->isFailure() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<ScheduledTask>  $tasks
     */
    private function listDueTasks(array $tasks): int
    {
        if ($tasks === []) {
            $this->components->info('No scheduled tasks are due.');

            return self::SUCCESS;
        }

        $this->table(
            ['Task', 'Cron', 'Class', 'Overlap guard'],
            array_map(static fn (ScheduledTask $task): array => [
                $task->name,
                (string) $task->expression,
                $task->taskClass,
                $task->withoutOverlapping ? sprintf('lock (%ds)', $task->lockTtlSeconds) : 'none',
            ], $tasks),
        );

        return self::SUCCESS;
    }

    private function renderReport(RunReport $report): void
    {
        $this->renderResults($report->results);

        if ($report->hasFailures()) {
            $this->components->error(sprintf('%d of %d scheduled task(s) failed.', count($report->failed()), $report->count()));
        }
    }

    /**
     * @param  list<TaskRunResult>  $results
     */
    private function renderResults(array $results): void
    {
        $this->table(
            ['Task', 'Status', 'Duration', 'Error'],
            array_map(static fn (TaskRunResult $result): array => [
                $result->task->name,
                $result->status->value,
                sprintf('%d ms', $result->durationMs()),
                $result->exception?->getMessage() ?? '',
            ], $results),
        );
    }
}
