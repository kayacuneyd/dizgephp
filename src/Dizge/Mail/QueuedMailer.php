<?php

declare(strict_types=1);

namespace Dizge\Mail;

use Dizge\Jobs\FileQueue;

/** Queues an allowlisted `mail.send` payload for the cron worker. */
final class QueuedMailer
{
    public function __construct(private FileQueue $queue) {}
    public function queue(Message $message, string $idempotencyKey): string
    {
        return $this->queue->push('mail.send', $message->toArray(), $idempotencyKey);
    }
}
