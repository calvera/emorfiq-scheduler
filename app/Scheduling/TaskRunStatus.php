<?php

namespace App\Scheduling;

enum TaskRunStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case SkippedLocked = 'skipped_locked';

    public function isFailure(): bool
    {
        return $this === self::Failed;
    }
}
