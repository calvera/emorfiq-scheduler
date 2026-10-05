<?php

namespace App\Scheduling\Contracts;

use DateTimeImmutable;

/**
 * A lock handle returned by MutexInterface::acquire(). Operations are owner-scoped.
 */
interface LockInterface
{
    /**
     * @return non-empty-string
     */
    public function key(): string;

    /**
     * @return non-empty-string
     */
    public function owner(): string;

    public function expiresAt(): DateTimeImmutable;

    /**
     * @param  positive-int  $ttlSeconds
     * @return bool False when the lock was lost in the meantime.
     */
    public function refresh(int $ttlSeconds): bool;

    /**
     * Release while still owned by this handle.
     *
     * @return bool True when this call actually removed the lock.
     */
    public function release(): bool;
}
