<?php

namespace App\Scheduling;

use App\Scheduling\Exceptions\InvalidCronExpressionException;
use Stringable;

/**
 * Five-field crontab expression. Field ranges are validated by the scheduler adapter.
 */
final readonly class CronExpression implements Stringable
{
    private const int FIELD_COUNT = 5;

    /**
     * @var non-empty-string
     */
    public string $value;

    /**
     * @throws InvalidCronExpressionException
     */
    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if ($normalized === '') {
            throw InvalidCronExpressionException::forExpression($value, 'expression is empty');
        }

        $fields = explode(' ', $normalized);

        if (count($fields) !== self::FIELD_COUNT) {
            throw InvalidCronExpressionException::forExpression(
                $value,
                sprintf('expected %d fields, got %d', self::FIELD_COUNT, count($fields)),
            );
        }

        $this->value = $normalized;
    }

    public static function everyMinute(): self
    {
        return new self('* * * * *');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
