<?php

namespace App\Scheduling\Contracts;

use App\Scheduling\Exceptions\MutexException;

/**
 * Distributed mutual exclusion keyed by an arbitrary string.
 *
 * Acquisition must be atomic: two concurrent callers asking for the same key
 * must never both receive a lock.
 */
interface MutexInterface
{
    /**
     * Atomically acquire a lock for the given key.
     *
     * @param  non-empty-string  $key
     * @param  positive-int  $ttlSeconds  After this many seconds the lock is considered abandoned and may be taken over.
     * @return LockInterface|null Null when the key is currently held by someone else and has not expired.
     *
     * @throws MutexException On storage failure (never on a plain collision).
     */
    public function acquire(string $key, int $ttlSeconds): ?LockInterface;

    /**
     * Whether a non-expired lock currently exists for the key.
     *
     * @param  non-empty-string  $key
     *
     * @throws MutexException
     */
    public function isLocked(string $key): bool;

    /**
     * Remove the lock regardless of owner or expiry. Intended for operational tooling.
     *
     * @param  non-empty-string  $key
     *
     * @throws MutexException
     */
    public function forceRelease(string $key): void;

    /**
     * Remove all expired locks.
     *
     * @return int Number of removed locks.
     *
     * @throws MutexException
     */
    public function purgeExpired(): int;
}
