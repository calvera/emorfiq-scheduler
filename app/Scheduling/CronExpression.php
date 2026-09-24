<?php

namespace App\Scheduling;

use App\Scheduling\Exceptions\InvalidCronExpressionException;
use Stringable;

/**
 * Five-field crontab expression: minute hour day-of-month month day-of-week.
 *
 * Only the syntax is validated here (field count and allowed characters); range
 * semantics are left to the concrete scheduler adapter, which owns the cron parser.
 */
final readonly class CronExpression implements Stringable
{
    private const int FIELD_COUNT = 5;

    private const string FIELD_PATTERN = '/^[0-9A-Za-z*\/,\-?]+$/';

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

        foreach ($fields as $index => $field) {
            if (preg_match(self::FIELD_PATTERN, $field) !== 1) {
                throw InvalidCronExpressionException::forExpression(
                    $value,
                    sprintf('field %d ("%s") contains unsupported characters', $index + 1, $field),
                );
            }
        }

        $this->value = $normalized;
    }

    /**
     * @throws InvalidCronExpressionException
     */
    public static function fromString(string $expression): self
    {
        return new self($expression);
    }

    public static function everyMinute(): self
    {
        return new self('* * * * *');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
