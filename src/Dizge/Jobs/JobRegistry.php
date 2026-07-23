<?php

declare(strict_types=1);

namespace Dizge\Jobs;

/** Explicit allowlist for queued work; queue payloads never name arbitrary PHP classes. */
final class JobRegistry
{
    /** @var array<string, callable(array<string,mixed>): void> */
    private array $handlers = [];
    /** @param callable(array<string,mixed>): void $handler */
    public function register(string $name, callable $handler): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,80}$/', $name)) throw new \InvalidArgumentException('Job name is invalid.');
        $this->handlers[$name] = $handler;
    }
    /** @param array<string,mixed> $payload */
    public function dispatch(string $name, array $payload): void
    {
        if (!isset($this->handlers[$name])) throw new \LogicException(sprintf('No job handler is registered for "%s".', $name));
        ($this->handlers[$name])($payload);
    }
    /** @return list<string> */
    public function names(): array { return array_keys($this->handlers); }
}
