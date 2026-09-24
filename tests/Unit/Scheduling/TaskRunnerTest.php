<?php

use App\Scheduling\CronExpression;
use App\Scheduling\ScheduledTask;
use App\Scheduling\TaskRunner;
use App\Scheduling\TaskRunStatus;
use Psr\Log\NullLogger;
use Tests\Fixtures\Scheduling\ArrayContainer;
use Tests\Fixtures\Scheduling\FailingTask;
use Tests\Fixtures\Scheduling\FixedClock;
use Tests\Fixtures\Scheduling\InMemoryMutex;
use Tests\Fixtures\Scheduling\RecordingTask;

beforeEach(function () {
    RecordingTask::reset();
});

function makeRunner(InMemoryMutex $mutex, ?ArrayContainer $container = null): TaskRunner
{
    return new TaskRunner(
        $mutex,
        $container ?? new ArrayContainer,
        new FixedClock(new DateTimeImmutable('2026-01-01 03:00:00', new DateTimeZone('UTC'))),
        new NullLogger,
    );
}

it('executes the task and releases the lock on success', function () {
    $mutex = new InMemoryMutex;
    $task = new ScheduledTask('demo', CronExpression::everyMinute(), RecordingTask::class);

    $result = makeRunner($mutex)->run($task);

    expect($result->status)->toBe(TaskRunStatus::Succeeded)
        ->and($result->exception)->toBeNull()
        ->and(RecordingTask::$executions)->toBe(1)
        ->and($mutex->isLocked('demo'))->toBeFalse()
        ->and($mutex->released)->toBe(['demo']);
});

it('captures a failing task and still releases the lock', function () {
    $mutex = new InMemoryMutex;
    $task = new ScheduledTask('demo', CronExpression::everyMinute(), FailingTask::class);

    $result = makeRunner($mutex)->run($task);

    expect($result->status)->toBe(TaskRunStatus::Failed)
        ->and($result->exception)->toBeInstanceOf(RuntimeException::class)
        ->and($result->exception->getMessage())->toBe(FailingTask::MESSAGE)
        ->and($mutex->isLocked('demo'))->toBeFalse();
});

it('skips the task when the lock is held elsewhere', function () {
    $mutex = new InMemoryMutex;
    $mutex->acquire('demo', 60);
    $task = new ScheduledTask('demo', CronExpression::everyMinute(), RecordingTask::class);

    $result = makeRunner($mutex)->run($task);

    expect($result->status)->toBe(TaskRunStatus::SkippedLocked)
        ->and(RecordingTask::$executions)->toBe(0)
        ->and($mutex->isLocked('demo'))->toBeTrue();
});

it('does not touch the mutex when overlap protection is disabled', function () {
    $mutex = new InMemoryMutex;
    $mutex->acquire('demo', 60);
    $task = new ScheduledTask('demo', CronExpression::everyMinute(), RecordingTask::class, withoutOverlapping: false);

    $result = makeRunner($mutex)->run($task);

    expect($result->status)->toBe(TaskRunStatus::Succeeded)
        ->and(RecordingTask::$executions)->toBe(1)
        ->and($mutex->released)->toBe([]);
});

it('fails when the container resolves something that is not a task', function () {
    $mutex = new InMemoryMutex;
    $container = new ArrayContainer([RecordingTask::class => new stdClass]);
    $task = new ScheduledTask('demo', CronExpression::everyMinute(), RecordingTask::class);

    $result = makeRunner($mutex, $container)->run($task);

    expect($result->status)->toBe(TaskRunStatus::Failed)
        ->and($result->exception->getMessage())->toContain('does not implement')
        ->and($mutex->isLocked('demo'))->toBeFalse();
});
