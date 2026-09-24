<?php

namespace Tests\Fixtures\Scheduling;

use App\Scheduling\Contracts\TaskInterface;
use RuntimeException;

final class FailingTask implements TaskInterface
{
    public const string MESSAGE = 'Task exploded.';

    public function execute(): void
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
