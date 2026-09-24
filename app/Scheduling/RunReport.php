<?php

namespace App\Scheduling;

use Countable;
use DateTimeImmutable;

final readonly class RunReport implements Countable
{
    /**
     * @param  list<TaskRunResult>  $results
     */
    public function __construct(
        public DateTimeImmutable $runAt,
        public array $results,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed() !== [];
    }

    /**
     * @return list<TaskRunResult>
     */
    public function failed(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (TaskRunResult $result): bool => $result->status->isFailure(),
        ));
    }

    public function count(): int
    {
        return count($this->results);
    }
}
