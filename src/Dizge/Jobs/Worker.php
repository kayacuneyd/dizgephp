<?php

declare(strict_types=1);

namespace Dizge\Jobs;

final class Worker
{
    public function __construct(private FileQueue $queue, private JobRegistry $registry) {}
    public function runOnce(): bool
    {
        $job = $this->queue->claim(); if ($job === null) return false;
        try { $this->registry->dispatch((string) $job['name'], (array) $job['payload']); $this->queue->complete($job); }
        catch (\Throwable $exception) { $this->queue->fail($job, $exception); }
        return true;
    }
}
