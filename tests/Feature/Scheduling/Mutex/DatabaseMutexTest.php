<?php

use App\Scheduling\Adapters\Laravel\LaravelClock;
use App\Scheduling\Mutex\DatabaseMutex;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

function makeDatabaseMutex(): DatabaseMutex
{
    return new DatabaseMutex(DB::connection(), new LaravelClock);
}

it('acquires a lock and records it in the database', function () {
    $this->freezeTime();
    $mutex = makeDatabaseMutex();

    $lock = $mutex->acquire('reports.daily', 600);

    expect($lock)->not->toBeNull()
        ->and($lock->expiresAt()->getTimestamp())->toBe(now()->addSeconds(600)->getTimestamp())
        ->and($mutex->isLocked('reports.daily'))->toBeTrue();
    $this->assertDatabaseHas('scheduler_locks', ['key' => 'reports.daily', 'owner' => $lock->owner()]);
});

it('refuses a second acquisition while the lock is held', function () {
    $mutex = makeDatabaseMutex();
    $first = $mutex->acquire('reports.daily', 600);

    $second = $mutex->acquire('reports.daily', 600);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();
});

it('allows different keys to be locked independently', function () {
    $mutex = makeDatabaseMutex();

    $first = $mutex->acquire('reports.daily', 600);
    $second = $mutex->acquire('reports.weekly', 600);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull();
});

it('lets an expired lock be taken over', function () {
    $this->freezeTime();
    $mutex = makeDatabaseMutex();
    $stale = $mutex->acquire('reports.daily', 60);
    $this->travel(61)->seconds();

    $fresh = $mutex->acquire('reports.daily', 60);

    expect($fresh)->not->toBeNull()
        ->and($fresh->owner())->not->toBe($stale->owner());
    $this->assertDatabaseCount('scheduler_locks', 1);
});

it('releases the lock and makes the key available again', function () {
    $mutex = makeDatabaseMutex();
    $lock = $mutex->acquire('reports.daily', 600);

    $released = $lock->release();

    expect($released)->toBeTrue()
        ->and($lock->release())->toBeFalse()
        ->and($mutex->isLocked('reports.daily'))->toBeFalse()
        ->and($mutex->acquire('reports.daily', 600))->not->toBeNull();
});

it('does not release a lock that was taken over by another owner', function () {
    $this->freezeTime();
    $mutex = makeDatabaseMutex();
    $stale = $mutex->acquire('reports.daily', 60);
    $this->travel(61)->seconds();
    $fresh = $mutex->acquire('reports.daily', 600);

    $released = $stale->release();

    expect($released)->toBeFalse()
        ->and($mutex->isLocked('reports.daily'))->toBeTrue();
    $this->assertDatabaseHas('scheduler_locks', ['key' => 'reports.daily', 'owner' => $fresh->owner()]);
});

it('refreshes the expiry while still owned', function () {
    $this->freezeTime();
    $mutex = makeDatabaseMutex();
    $lock = $mutex->acquire('reports.daily', 60);
    $this->travel(30)->seconds();

    $refreshed = $lock->refresh(600);

    expect($refreshed)->toBeTrue()
        ->and($lock->expiresAt()->getTimestamp())->toBe(now()->addSeconds(600)->getTimestamp());
});

it('reports a lost lock when refreshing after it expired', function () {
    $this->freezeTime();
    $mutex = makeDatabaseMutex();
    $lock = $mutex->acquire('reports.daily', 60);
    $this->travel(61)->seconds();

    $refreshed = $lock->refresh(600);

    expect($refreshed)->toBeFalse();
});

it('force-releases a lock regardless of owner', function () {
    $mutex = makeDatabaseMutex();
    $lock = $mutex->acquire('reports.daily', 600);

    $mutex->forceRelease('reports.daily');

    expect($mutex->isLocked('reports.daily'))->toBeFalse()
        ->and($lock->release())->toBeFalse();
});

it('purges only expired locks', function () {
    $this->freezeTime();
    $mutex = makeDatabaseMutex();
    $mutex->acquire('short', 60);
    $mutex->acquire('long', 3600);
    $this->travel(61)->seconds();

    $purged = $mutex->purgeExpired();

    expect($purged)->toBe(1)
        ->and($mutex->isLocked('short'))->toBeFalse()
        ->and($mutex->isLocked('long'))->toBeTrue();
});
