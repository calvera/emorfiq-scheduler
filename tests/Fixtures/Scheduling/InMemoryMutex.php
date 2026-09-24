<?php

namespace Tests\Fixtures\Scheduling;

use App\Scheduling\Contracts\LockInterface;
use App\Scheduling\Contracts\MutexInterface;
use DateTimeImmutable;

/**
 * Process-local mutex for unit tests. Locks never expire.
 */
final class InMemoryMutex implements MutexInterface
{
    /**
     * @var array<string, string> key => owner
     */
    public array $held = [];

    /**
     * @var list<string>
     */
    public array $released = [];

    public function acquire(string $key, int $ttlSeconds): ?LockInterface
    {
        if (isset($this->held[$key])) {
            return null;
        }

        $owner = bin2hex(random_bytes(8));
        $this->held[$key] = $owner;

        return new InMemoryLock($this, $key, $owner, new DateTimeImmutable("+{$ttlSeconds} seconds"));
    }

    public function isLocked(string $key): bool
    {
        return isset($this->held[$key]);
    }

    public function forceRelease(string $key): void
    {
        unset($this->held[$key]);
    }

    public function purgeExpired(): int
    {
        return 0;
    }

    public function releaseOwned(string $key, string $owner): bool
    {
        if (($this->held[$key] ?? null) !== $owner) {
            return false;
        }

        unset($this->held[$key]);
        $this->released[] = $key;

        return true;
    }
}
