<?php

declare(strict_types=1);

namespace Dizge;

/**
 * Minimal explicit dependency container. It never reflects or auto-wires constructor arguments:
 * dependencies must be registered with bind() or singleton().
 */
final class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $bindings = [];
    /** @var array<string, mixed> */
    private array $instances = [];

    /** @param callable(self): mixed $factory */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** @param callable(self): mixed $factory */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = function (self $container) use ($id, $factory): mixed {
            return $this->instances[$id] ??= $factory($container);
        };
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) return $this->instances[$id];
        if (isset($this->bindings[$id])) return ($this->bindings[$id])($this);
        if (class_exists($id) && (new \ReflectionClass($id))->getConstructor() === null) return new $id();
        throw new \LogicException(sprintf('No explicit binding exists for "%s".', $id));
    }
}
