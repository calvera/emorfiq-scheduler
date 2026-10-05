<?php

namespace App\Scheduling\Contracts;

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
     */
    public function acquire(string $key, int $ttlSeconds): ?LockInterface;
}
