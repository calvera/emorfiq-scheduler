<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduler Driver
    |--------------------------------------------------------------------------
    |
    | Which SchedulerInterface adapter evaluates cron expressions. The task
    | definitions, runner and mutex are independent of this choice.
    |
    | Supported: "laravel"
    |
    */

    'driver' => env('SCHEDULER_DRIVER', 'laravel'),

    /*
    |--------------------------------------------------------------------------
    | Default Timezone
    |--------------------------------------------------------------------------
    |
    | Cron expressions of tasks without an explicit timezone are evaluated in
    | this timezone. Null falls back to the application timezone.
    |
    */

    'timezone' => env('SCHEDULER_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Mutex
    |--------------------------------------------------------------------------
    |
    | Overlap protection for scheduled tasks. The database driver relies on the
    | primary key of the locks table for atomic acquisition and therefore works
    | across multiple servers sharing the same database.
    |
    | Supported drivers: "database"
    |
    */

    'mutex' => [
        'driver' => env('SCHEDULER_MUTEX_DRIVER', 'database'),
        'connection' => env('SCHEDULER_MUTEX_CONNECTION'),
        'table' => env('SCHEDULER_MUTEX_TABLE', 'scheduler_locks'),
        'default_ttl' => (int) env('SCHEDULER_MUTEX_DEFAULT_TTL', 3600),
    ],

];
