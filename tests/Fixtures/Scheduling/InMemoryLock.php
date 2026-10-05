<?php

namespace Tests\Fixtures\Scheduling;

use App\Scheduling\Contracts\LockInterface;
use DateTimeImmutable;

final class InMemoryLock implements LockInterface
{
    public function __construct(
        private readonly InMemoryMutex $mutex,
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
        if (! isset($this->mutex->held[$this->key]) || $this->mutex->held[$this->key] !== $this->owner) {
            return false;
        }

        $this->expiresAt = new DateTimeImmutable("+{$ttlSeconds} seconds");

        return true;
    }

    public function release(): bool
    {
        return $this->mutex->releaseOwned($this->key, $this->owner);
    }
}
