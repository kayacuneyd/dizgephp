<?php

declare(strict_types=1);

namespace Dizge\Mail;

final class FakeTransport implements Transport
{
    /** @var list<Message> */ private array $sent = [];
    public function send(Message $message): void { $this->sent[] = $message; }
    /** @return list<Message> */ public function sent(): array { return $this->sent; }
}
