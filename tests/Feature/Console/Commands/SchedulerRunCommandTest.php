<?php

use App\Scheduling\Contracts\MutexInterface;
use App\Scheduling\Contracts\SchedulerInterface;
use App\Scheduling\CronExpression;
use App\Scheduling\ScheduledTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Fixtures\Scheduling\FailingTask;
use Tests\Fixtures\Scheduling\RecordingTask;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    RecordingTask::reset();
});

it('runs the tasks that are due and reports success', function () {
    $this->travelTo('2026-01-01 03:00:00');
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class),
    );
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('later', new CronExpression('30 3 * * *'), RecordingTask::class),
    );

    $this->artisan('app:scheduler:run')
        ->expectsTable(['Task', 'Status', 'Duration', 'Error'], [
            ['daily', 'succeeded', '0 ms', ''],
        ])
        ->assertSuccessful();

    expect(RecordingTask::$executions)->toBe(1);
});

it('reports that nothing is due', function () {
    $this->travelTo('2026-01-01 03:05:00');
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class),
    );

    $this->artisan('app:scheduler:run')
        ->expectsOutputToContain('No scheduled tasks are due.')
        ->assertSuccessful();

    expect(RecordingTask::$executions)->toBe(0);
});

it('exits with a failure code when a task fails', function () {
    $this->travelTo('2026-01-01 03:00:00');
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('boom', new CronExpression('0 3 * * *'), FailingTask::class),
    );

    $this->artisan('app:scheduler:run')
        ->expectsTable(['Task', 'Status', 'Duration', 'Error'], [
            ['boom', 'failed', '0 ms', FailingTask::MESSAGE],
        ])
        ->expectsOutputToContain('1 of 1 scheduled task(s) failed.')
        ->assertFailed();
});

it('runs a single named task regardless of its cron expression', function () {
    $this->travelTo('2026-01-01 03:00:00');
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('later', new CronExpression('30 3 * * *'), RecordingTask::class),
    );

    $this->artisan('app:scheduler:run', ['--task' => 'later'])
        ->expectsTable(['Task', 'Status', 'Duration', 'Error'], [
            ['later', 'succeeded', '0 ms', ''],
        ])
        ->assertSuccessful();

    expect(RecordingTask::$executions)->toBe(1);
});

it('returns an invalid exit code for an unknown task name', function () {
    $this->artisan('app:scheduler:run', ['--task' => 'missing'])
        ->expectsOutputToContain('No scheduled task named "missing"')
        ->assertExitCode(2);
});

it('lists due tasks without running them in dry-run mode', function () {
    $this->travelTo('2026-01-01 03:00:00');
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class),
    );

    $this->artisan('app:scheduler:run', ['--dry-run' => true])
        ->expectsTable(['Task', 'Cron', 'Class', 'Overlap guard'], [
            ['daily', '0 3 * * *', RecordingTask::class, 'lock (3600s)'],
        ])
        ->assertSuccessful();

    expect(RecordingTask::$executions)->toBe(0);
});

it('skips a task whose lock is held by another process', function () {
    $this->travelTo('2026-01-01 03:00:00');
    app(SchedulerInterface::class)->schedule(
        new ScheduledTask('daily', new CronExpression('0 3 * * *'), RecordingTask::class),
    );
    app(MutexInterface::class)->acquire('daily', 600);

    $this->artisan('app:scheduler:run')
        ->expectsTable(['Task', 'Status', 'Duration', 'Error'], [
            ['daily', 'skipped_locked', '0 ms', ''],
        ])
        ->assertSuccessful();

    expect(RecordingTask::$executions)->toBe(0);
});
