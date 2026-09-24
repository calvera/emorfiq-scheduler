<?php

use App\Scheduling\CronExpression;
use App\Scheduling\Exceptions\InvalidCronExpressionException;

it('accepts a valid five-field crontab expression', function (string $expression) {
    $cron = CronExpression::fromString($expression);

    expect((string) $cron)->toBe($expression);
})->with([
    'every minute' => '* * * * *',
    'daily at 3am' => '0 3 * * *',
    'ranges and steps' => '*/15 9-17 1,15 * MON-FRI',
    'question mark' => '0 0 ? * 1',
]);

it('normalizes surrounding and repeated whitespace', function () {
    $cron = CronExpression::fromString("  0   3 *\t* *  ");

    expect($cron->value)->toBe('0 3 * * *');
});

it('rejects an expression with the wrong number of fields', function (string $expression) {
    expect(fn () => CronExpression::fromString($expression))
        ->toThrow(InvalidCronExpressionException::class, 'expected 5 fields');
})->with([
    'four fields' => '0 3 * *',
    'six fields' => '0 0 3 * * *',
]);

it('rejects an empty expression', function () {
    expect(fn () => CronExpression::fromString('   '))
        ->toThrow(InvalidCronExpressionException::class, 'empty');
});

it('rejects unsupported characters', function () {
    expect(fn () => CronExpression::fromString('0 3 * * *; rm -rf /'))
        ->toThrow(InvalidCronExpressionException::class);
});

it('builds the every-minute expression', function () {
    expect(CronExpression::everyMinute()->value)->toBe('* * * * *');
});

it('compares expressions by value', function () {
    expect(CronExpression::fromString('0 3 * * *')->equals(CronExpression::fromString('0  3 * * *')))->toBeTrue()
        ->and(CronExpression::fromString('0 3 * * *')->equals(CronExpression::everyMinute()))->toBeFalse();
});
