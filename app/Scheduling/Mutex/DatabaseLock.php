<?php

namespace App\Scheduling\Mutex;

use App\Scheduling\Contracts\LockInterface;
use DateTimeImmutable;

final class DatabaseLock implements LockInterface
{
    private bool $acquired = true;

    /**
     * @param  non-empty-string  $key
     * @param  non-empty-string  $owner
     */
    public function __construct(
        private readonly DatabaseMutex $mutex,
        private readonly string $key,
        private readonly string $owner,
        private DateTimeImmutable $expiresAt,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function refresh(int $ttlSeconds): bool
    {
        if (! $this->acquired) {
            return false;
        }

        $expiresAt = $this->mutex->refreshOwned($this->key, $this->owner, $ttlSeconds);

        if ($expiresAt === null) {
            $this->acquired = false;

            return false;
        }

        $this->expiresAt = $expiresAt;

        return true;
    }

    public function release(): bool
    {
        if (! $this->acquired) {
            return false;
        }

        $this->acquired = false;

        return $this->mutex->releaseOwned($this->key, $this->owner);
    }

    public function isAcquired(): bool
    {
        return $this->acquired;
    }
}
