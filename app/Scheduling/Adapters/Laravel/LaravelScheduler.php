<?php

namespace App\Scheduling\Adapters\Laravel;

use App\Scheduling\Contracts\SchedulerInterface;
use App\Scheduling\Exceptions\DuplicateTaskException;
use App\Scheduling\Exceptions\TaskNotFoundException;
use App\Scheduling\RunReport;
use App\Scheduling\ScheduledTask;
use App\Scheduling\TaskRunner;
use App\Scheduling\TaskRunResult;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * SchedulerInterface adapter on top of Illuminate's Schedule.
 *
 * Laravel is used purely for cron expression evaluation (and to expose the tasks
 * to `schedule:list`). Overlap protection deliberately does NOT use Laravel's
 * EventMutex (its check-then-act is not atomic); TaskRunner owns the mutex instead.
 */
final class LaravelScheduler implements SchedulerInterface
{
    /**
     * @var array<non-empty-string, ScheduledTask>
     */
    private array $tasks = [];

    /**
     * @var array<non-empty-string, Event>
     */
    private array $events = [];

    public function __construct(
        private readonly Schedule $schedule,
        private readonly TaskRunner $runner,
        private readonly DateTimeZone $defaultTimezone,
    ) {}

    public function schedule(ScheduledTask $task): void
    {
        if (isset($this->tasks[$task->name])) {
            throw DuplicateTaskException::forName($task->name);
        }

        $event = $this->schedule
            ->call(fn (): TaskRunResult => $this->runner->run($task))
            ->cron((string) $task->expression)
            ->timezone(($task->timezone ?? $this->defaultTimezone)->getName())
            ->name($task->name);

        if ($task->description !== null) {
            $event->description($task->description);
        }

        $this->tasks[$task->name] = $task;
        $this->events[$task->name] = $event;
    }

    public function tasks(): array
    {
        return array_values($this->tasks);
    }

    public function task(string $name): ScheduledTask
    {
        return $this->tasks[$name] ?? throw TaskNotFoundException::forName($name);
    }

    public function dueTasks(DateTimeImmutable $now): array
    {
        $currentMinute = intdiv($now->getTimestamp(), 60);
        $due = [];

        foreach ($this->tasks as $name => $task) {
            $nextRun = $this->events[$name]->nextRunDate($now, 0, true);

            if (intdiv($nextRun->getTimestamp(), 60) === $currentMinute) {
                $due[] = $task;
            }
        }

        return $due;
    }

    public function run(DateTimeImmutable $now): RunReport
    {
        $results = [];

        foreach ($this->dueTasks($now) as $task) {
            $results[] = $this->runner->run($task);
        }

        return new RunReport($now, $results);
    }

    public function runTask(string $name, DateTimeImmutable $now): TaskRunResult
    {
        return $this->runner->run($this->task($name));
    }
}
