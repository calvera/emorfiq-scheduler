<?php

namespace Tests\Fixtures\Scheduling;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Minimal PSR-11 container: instantiates any registered class without arguments,
 * or returns a preconfigured instance.
 */
final class ArrayContainer implements ContainerInterface
{
    /**
     * @param  array<string, object>  $instances
     */
    public function __construct(private array $instances = []) {}

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (class_exists($id)) {
            return new $id;
        }

        throw new class(sprintf('No entry for "%s".', $id)) extends RuntimeException implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || class_exists($id);
    }
}
