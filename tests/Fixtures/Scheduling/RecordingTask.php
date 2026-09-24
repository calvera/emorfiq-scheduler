<?php

namespace Tests\Fixtures\Scheduling;

use App\Scheduling\Contracts\TaskInterface;

final class RecordingTask implements TaskInterface
{
    public static int $executions = 0;

    public static function reset(): void
    {
        self::$executions = 0;
    }

    public function execute(): void
    {
        self::$executions++;
    }
}
