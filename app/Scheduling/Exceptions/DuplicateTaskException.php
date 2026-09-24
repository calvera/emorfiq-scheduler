<?php

namespace App\Scheduling\Exceptions;

class DuplicateTaskException extends SchedulerException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('A scheduled task named "%s" is already registered.', $name));
    }
}
