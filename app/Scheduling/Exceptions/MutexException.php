<?php

namespace App\Scheduling\Exceptions;

use Throwable;

class MutexException extends SchedulerException
{
    public static function storageFailure(string $operation, string $key, Throwable $previous): self
    {
        return new self(
            sprintf('Mutex storage failed during "%s" for key "%s": %s', $operation, $key, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
