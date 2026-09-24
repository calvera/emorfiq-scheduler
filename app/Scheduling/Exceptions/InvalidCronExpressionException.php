<?php

namespace App\Scheduling\Exceptions;

class InvalidCronExpressionException extends SchedulerException
{
    public static function forExpression(string $expression, string $reason): self
    {
        return new self(sprintf('Invalid cron expression "%s": %s', $expression, $reason));
    }
}
