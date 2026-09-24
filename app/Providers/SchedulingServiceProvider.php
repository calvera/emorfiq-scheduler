<?php

namespace App\Providers;

use App\Scheduling\Adapters\Laravel\LaravelClock;
use App\Scheduling\Adapters\Laravel\LaravelScheduler;
use App\Scheduling\Contracts\MutexInterface;
use App\Scheduling\Contracts\SchedulerInterface;
use App\Scheduling\Exceptions\SchedulerException;
use App\Scheduling\Mutex\DatabaseMutex;
use App\Scheduling\ScheduleDefinition;
use App\Scheduling\TaskRunner;
use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClockInterface::class, LaravelClock::class);

        $this->app->singleton(MutexInterface::class, function (Application $app): MutexInterface {
            $driver = $app->make('config')->get('scheduler.mutex.driver');

            return match ($driver) {
                'database' => new DatabaseMutex(
                    $app->make(ConnectionResolverInterface::class)->connection(
                        $app->make('config')->get('scheduler.mutex.connection'),
                    ),
                    $app->make(ClockInterface::class),
                    $app->make('config')->get('scheduler.mutex.table', 'scheduler_locks'),
                ),
                default => throw new SchedulerException(sprintf('Unsupported scheduler mutex driver "%s".', $driver)),
            };
        });

        $this->app->singleton(TaskRunner::class, fn (Application $app): TaskRunner => new TaskRunner(
            $app->make(MutexInterface::class),
            $app->make(ContainerInterface::class),
            $app->make(ClockInterface::class),
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(SchedulerInterface::class, function (Application $app): SchedulerInterface {
            $config = $app->make('config');
            $driver = $config->get('scheduler.driver');
            $timezone = $config->get('scheduler.timezone') ?? $config->get('app.timezone', 'UTC');

            $scheduler = match ($driver) {
                'laravel' => new LaravelScheduler(
                    $app->make(Schedule::class),
                    $app->make(TaskRunner::class),
                    new DateTimeZone($timezone),
                ),
                default => throw new SchedulerException(sprintf('Unsupported scheduler driver "%s".', $driver)),
            };

            $app->make(ScheduleDefinition::class)->register($scheduler);

            return $scheduler;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
