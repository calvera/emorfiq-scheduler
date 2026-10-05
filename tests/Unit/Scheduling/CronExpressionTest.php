<?php

use App\Scheduling\CronExpression;
use App\Scheduling\Exceptions\InvalidCronExpressionException;

it('accepts a valid five-field crontab expression', function (string $expression) {
    $cron = new CronExpression($expression);

    expect((string) $cron)->toBe($expression);
})->with([
    'every minute' => '* * * * *',
    'daily at 3am' => '0 3 * * *',
    'ranges and steps' => '*/15 9-17 1,15 * MON-FRI',
    'question mark' => '0 0 ? * 1',
]);

it('normalizes surrounding and repeated whitespace', function () {
    $cron = new CronExpression("  0   3 *\t* *  ");

    expect($cron->value)->toBe('0 3 * * *');
});

it('rejects an expression with the wrong number of fields', function (string $expression) {
    expect(fn () => new CronExpression($expression))
        ->toThrow(InvalidCronExpressionException::class, 'expected 5 fields');
})->with([
    'four fields' => '0 3 * *',
    'six fields' => '0 0 3 * * *',
]);

it('rejects an empty expression', function () {
    expect(fn () => new CronExpression('   '))
        ->toThrow(InvalidCronExpressionException::class, 'empty');
});

it('builds the every-minute expression', function () {
    expect(CronExpression::everyMinute()->value)->toBe('* * * * *');
});
