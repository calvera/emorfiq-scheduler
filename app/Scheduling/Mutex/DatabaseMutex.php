<?php

namespace App\Scheduling\Mutex;

use App\Scheduling\Contracts\LockInterface;
use App\Scheduling\Contracts\MutexInterface;
use App\Scheduling\Exceptions\MutexException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Mutex backed by a database table with a primary key on the lock key.
 *
 * Atomicity comes from the unique constraint: the INSERT of a colliding key fails,
 * so two concurrent acquirers can never both succeed. Expired rows are removed
 * right before inserting, which lets a crashed holder's lock be taken over.
 */
final class DatabaseMutex implements MutexInterface
{
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ClockInterface $clock,
        private readonly string $table = 'scheduler_locks',
    ) {}

    public function acquire(string $key, int $ttlSeconds): ?LockInterface
    {
        $now = $this->now();
        $owner = bin2hex(random_bytes(16));
        $expiresAt = $now->modify(sprintf('+%d seconds', $ttlSeconds));

        try {
            $this->query()
                ->where('key', $key)
                ->where('expires_at', '<=', $this->format($now))
                ->delete();

            $this->query()->insert([
                'key' => $key,
                'owner' => $owner,
                'acquired_at' => $this->format($now),
                'expires_at' => $this->format($expiresAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        } catch (Throwable $exception) {
            throw MutexException::storageFailure('acquire', $key, $exception);
        }

        return new DatabaseLock($this, $key, $owner, $expiresAt);
    }

    public function isLocked(string $key): bool
    {
        try {
            return $this->query()
                ->where('key', $key)
                ->where('expires_at', '>', $this->format($this->now()))
                ->exists();
        } catch (Throwable $exception) {
            throw MutexException::storageFailure('isLocked', $key, $exception);
        }
    }

    public function forceRelease(string $key): void
    {
        try {
            $this->query()->where('key', $key)->delete();
        } catch (Throwable $exception) {
            throw MutexException::storageFailure('forceRelease', $key, $exception);
        }
    }

    public function purgeExpired(): int
    {
        try {
            return $this->query()
                ->where('expires_at', '<=', $this->format($this->now()))
                ->delete();
        } catch (Throwable $exception) {
            throw MutexException::storageFailure('purgeExpired', '*', $exception);
        }
    }

    /**
     * Release the lock only if the given owner still holds it.
     *
     * @internal Used by DatabaseLock.
     */
    public function releaseOwned(string $key, string $owner): bool
    {
        try {
            return $this->query()
                ->where('key', $key)
                ->where('owner', $owner)
                ->delete() > 0;
        } catch (Throwable $exception) {
            throw MutexException::storageFailure('release', $key, $exception);
        }
    }

    /**
     * Extend the lock only if the given owner still holds it.
     *
     * @internal Used by DatabaseLock.
     *
     * @return DateTimeImmutable|null The new expiry, or null when the owner no longer holds the lock.
     */
    public function refreshOwned(string $key, string $owner, int $ttlSeconds): ?DateTimeImmutable
    {
        $now = $this->now();
        $expiresAt = $now->modify(sprintf('+%d seconds', $ttlSeconds));

        try {
            $updated = $this->query()
                ->where('key', $key)
                ->where('owner', $owner)
                ->where('expires_at', '>', $this->format($now))
                ->update(['expires_at' => $this->format($expiresAt)]);
        } catch (Throwable $exception) {
            throw MutexException::storageFailure('refresh', $key, $exception);
        }

        return $updated > 0 ? $expiresAt : null;
    }

    private function query(): Builder
    {
        return $this->connection->table($this->table);
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format(self::DATETIME_FORMAT);
    }
}
