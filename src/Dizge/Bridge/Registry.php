<?php
declare(strict_types=1);
namespace Dizge\Bridge;
/** Registered server handlers; routes/bootstrap are re-evaluated per PHP request. */
final class Registry
{
    /** @var array<string, callable(array<string,mixed>): Patch|null> */ private array $handlers = [];
    /** @var list<array{id:string,selector:string,event:string}> */ private array $bindings = [];
    /** @param callable(array<string,mixed>): Patch|null $handler */
    public function on(string $id, string $selector, string $event, callable $handler): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $id) || !str_starts_with($selector, '#') || !preg_match('/^[a-z][\w:-]*$/', $event)) throw new \InvalidArgumentException('Invalid bridge binding.');
        $this->handlers[$id] = $handler; $this->bindings[] = compact('id', 'selector', 'event');
    }
    /** @param array<string,mixed> $detail */
    public function dispatch(string $id, array $detail): ?Patch
    {
        if (!isset($this->handlers[$id])) throw new \InvalidArgumentException('Unknown bridge handler.');
        return ($this->handlers[$id])($detail);
    }
    /** @return list<array{id:string,selector:string,event:string}> */ public function bindings(): array { return $this->bindings; }
}
