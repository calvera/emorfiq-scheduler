<?php

namespace App\Scheduling\Contracts;

/**
 * A unit of work that can be registered with the scheduler.
 *
 * Implementations are resolved from the PSR-11 container right before execution,
 * so constructor dependencies are injected as usual.
 */
interface TaskInterface
{
    /**
     * Perform the task. Failure is signalled by throwing any Throwable.
     */
    public function execute(): void;
}
