<?php

namespace App\Scheduling\Adapters\Laravel;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use Psr\Clock\ClockInterface;

/**
 * PSR-20 clock that honours Laravel's test time helpers (freezeTime, travelTo).
 */
final class LaravelClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return Date::now()->toDateTimeImmutable();
    }
}
