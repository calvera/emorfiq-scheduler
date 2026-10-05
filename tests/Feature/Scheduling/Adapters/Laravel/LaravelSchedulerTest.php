<?php

use App\Scheduling\Adapters\Laravel\LaravelScheduler;
use App\Scheduling\CronExpression;
use App\Scheduling\Exceptions\DuplicateTaskException;
use App\Scheduling\Exceptions\TaskNotFoundException;
use App\Scheduling\ScheduledTask;
use App\Scheduling\TaskRunner;
use App\Scheduling\TaskRunStatus;
use Illuminate\Console\Scheduling\Schedule;
use Psr\Log\NullLogger;
use Tests\Fixtures\Scheduling\FailingTask;
use Tests\Fixtures\Scheduling\FixedClock;
use Tests\Fixtures\Scheduling\InMemoryMutex;
use Tests\Fixtures\Scheduling\RecordingTask;

beforeEach(function () {
    RecordingTask::reset();
});

function makeLaravelScheduler(?InMemoryMutex $mutex = null, string $timezone = 'UTC'): LaravelScheduler
{
    $runner = new TaskRunner(
        $mutex ?? new InMemoryMutex,
        app(),
        new FixedClock(new DateTimeImmutable('2026-01-01 03:00:00', new DateTimeZone('UTC'))),
        new NullLogger,
    );

    return new LaravelScheduler(app(Schedule::class), $runner, new DateTimeZone($timezone));
}

function at(string $dateTime, string $timezone = 'UTC'): DateTimeImmutable
{
    return new DateTimeImmutable($dateTime, new DateTimeZone($timezone));
}

it('registers tasks and exposes them in registration order', function () {
    $scheduler = makeLaravelScheduler();
    $daily = new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class);
    $hourly = new ScheduledTask('hourly', new CronExpression('0 * * * *'), RecordingTask::class);

    $scheduler->schedule($daily);
    $scheduler->schedule($hourly);

    expect($scheduler->tasks())->toBe([$daily, $hourly])
        ->and($scheduler->task('hourly'))->toBe($hourly);
});

it('rejects a duplicate task name', function () {
    $scheduler = makeLaravelScheduler();
    $scheduler->schedule(new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class));

    expect(fn () => $scheduler->schedule(new ScheduledTask('daily', CronExpression::everyMinute(), RecordingTask::class)))
        ->toThrow(DuplicateTaskException::class);
});

it('throws when asking for an unknown task', function () {
    $scheduler = makeLaravelScheduler();

    expect(fn () => $scheduler->task('missing'))->toThrow(TaskNotFoundException::class);
});

it('exposes registered tasks to the underlying Laravel schedule', function () {
    $scheduler = makeLaravelScheduler();

    $scheduler->schedule(new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class));

    $events = app(Schedule::class)->events();
    expect($events)->toHaveCount(1)
        ->and($events[0]->getExpression())->toBe('0 3 * * *')
        ->and($events[0]->description)->toBe('daily')
        ->and($events[0]->withoutOverlapping)->toBeFalse();
});

it('returns only the tasks whose cron matches the given minute', function () {
    $scheduler = makeLaravelScheduler();
    $daily = new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class);
    $hourly = new ScheduledTask('hourly', new CronExpression('0 * * * *'), RecordingTask::class);
    $quarterly = new ScheduledTask('quarterly', new CronExpression('15 * * * *'), RecordingTask::class);
    $scheduler->schedule($daily);
    $scheduler->schedule($hourly);
    $scheduler->schedule($quarterly);

    $dueAtThree = $scheduler->dueTasks(at('2026-01-01 03:00:42'));
    $dueAtFour = $scheduler->dueTasks(at('2026-01-01 04:00:00'));
    $dueAtQuarterPast = $scheduler->dueTasks(at('2026-01-01 04:15:00'));

    expect($dueAtThree)->toBe([$daily, $hourly])
        ->and($dueAtFour)->toBe([$hourly])
        ->and($dueAtQuarterPast)->toBe([$quarterly]);
});

it('evaluates the cron expression in the task timezone', function () {
    $scheduler = makeLaravelScheduler();
    $prague = new ScheduledTask(
        'prague',
        new CronExpression('0 3 * * *'),
        RecordingTask::class,
        timezone: new DateTimeZone('Europe/Prague'),
    );
    $scheduler->schedule($prague);

    // 03:00 in Prague (CET, UTC+1) is 02:00 UTC.
    $dueInPrague = $scheduler->dueTasks(at('2026-01-01 02:00:00'));
    $notDueInUtc = $scheduler->dueTasks(at('2026-01-01 03:00:00'));

    expect($dueInPrague)->toBe([$prague])
        ->and($notDueInUtc)->toBe([]);
});

it('falls back to the scheduler default timezone', function () {
    $scheduler = makeLaravelScheduler(timezone: 'Europe/Prague');
    $task = new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class);
    $scheduler->schedule($task);

    $due = $scheduler->dueTasks(at('2026-01-01 02:00:00'));

    expect($due)->toBe([$task]);
});

it('runs due tasks and reports the outcome of each', function () {
    $scheduler = makeLaravelScheduler();
    $scheduler->schedule(new ScheduledTask('ok', new CronExpression('0 3 * * *'), RecordingTask::class));
    $scheduler->schedule(new ScheduledTask('boom', new CronExpression('0 3 * * *'), FailingTask::class));
    $scheduler->schedule(new ScheduledTask('later', new CronExpression('30 3 * * *'), RecordingTask::class));

    $report = $scheduler->run(at('2026-01-01 03:00:00'));

    expect($report->count())->toBe(2)
        ->and($report->results[0]->status)->toBe(TaskRunStatus::Succeeded)
        ->and($report->results[1]->status)->toBe(TaskRunStatus::Failed)
        ->and($report->hasFailures())->toBeTrue()
        ->and($report->failed())->toHaveCount(1)
        ->and(RecordingTask::$executions)->toBe(1);
});

it('runs a named task regardless of its cron expression', function () {
    $scheduler = makeLaravelScheduler();
    $scheduler->schedule(new ScheduledTask('later', new CronExpression('30 3 * * *'), RecordingTask::class));

    $result = $scheduler->runTask('later', at('2026-01-01 03:00:00'));

    expect($result->status)->toBe(TaskRunStatus::Succeeded)
        ->and(RecordingTask::$executions)->toBe(1);
});

it('honours the mutex when running a named task', function () {
    $mutex = new InMemoryMutex;
    $mutex->acquire('later', 60);
    $scheduler = makeLaravelScheduler($mutex);
    $scheduler->schedule(new ScheduledTask('later', new CronExpression('30 3 * * *'), RecordingTask::class));

    $result = $scheduler->runTask('later', at('2026-01-01 03:00:00'));

    expect($result->status)->toBe(TaskRunStatus::SkippedLocked)
        ->and(RecordingTask::$executions)->toBe(0);
});
