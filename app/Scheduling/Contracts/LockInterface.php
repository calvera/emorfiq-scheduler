<?php

namespace App\Scheduling\Contracts;

use DateTimeImmutable;

/**
 * A lock handle returned by MutexInterface::acquire(). Every operation is owner-scoped:
 * it only succeeds while this handle is still the recorded owner of the key.
 */
interface LockInterface
{
    /**
     * @return non-empty-string
     */
    public function key(): string;

    /**
     * Random token identifying the holder of this lock.
     *
     * @return non-empty-string
     */
    public function owner(): string;

    public function expiresAt(): DateTimeImmutable;

    /**
     * Extend the lifetime of the lock while still owned by this handle.
     *
     * @param  positive-int  $ttlSeconds
     * @return bool False when the lock was lost in the meantime (expired and taken over, or force-released).
     */
    public function refresh(int $ttlSeconds): bool;

    /**
     * Release the lock while still owned by this handle. Idempotent.
     *
     * @return bool True when this call actually removed the lock.
     */
    public function release(): bool;

    /**
     * Whether this handle believes it still holds the lock (not released, not known to be lost).
     */
    public function isAcquired(): bool;
}
