<?php

use App\Scheduling\CronExpression;
use App\Scheduling\Exceptions\SchedulerException;
use App\Scheduling\ScheduledTask;
use Tests\Fixtures\Scheduling\RecordingTask;

it('rejects an empty name', function () {
    expect(fn () => new ScheduledTask('  ', CronExpression::everyMinute(), RecordingTask::class))
        ->toThrow(SchedulerException::class, 'non-empty name');
});

it('rejects a non-positive lock ttl', function () {
    expect(fn () => new ScheduledTask('demo', CronExpression::everyMinute(), RecordingTask::class, lockTtlSeconds: 0))
        ->toThrow(SchedulerException::class, 'positive');
});

it('defaults to overlap protection with a one hour lock', function () {
    $task = new ScheduledTask('demo', CronExpression::everyMinute(), RecordingTask::class);

    expect($task->withoutOverlapping)->toBeTrue()
        ->and($task->lockTtlSeconds)->toBe(3600)
        ->and($task->timezone)->toBeNull();
});
