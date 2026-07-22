<?php

declare(strict_types=1);

namespace Dizge\Mail;

/** Minimal local transport; SMTP is an adapter concern, never an unvalidated header passthrough. */
final class NativeTransport implements Transport
{
    public function __construct(private string $from) {}
    public function send(Message $message): void
    {
        if (!filter_var($message->to, FILTER_VALIDATE_EMAIL) || str_contains($message->subject, "\n") || str_contains($this->from, "\n")) throw new \InvalidArgumentException('Mail message is invalid.');
        if (!mail($message->to, $message->subject, $message->text, ['From' => $this->from, 'Content-Type' => 'text/plain; charset=UTF-8'])) throw new \RuntimeException('Mail transport rejected the message.');
    }
}
