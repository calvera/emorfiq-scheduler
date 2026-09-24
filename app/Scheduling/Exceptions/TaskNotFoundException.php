<?php

namespace App\Scheduling\Exceptions;

class TaskNotFoundException extends SchedulerException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('No scheduled task named "%s" is registered.', $name));
    }
}
