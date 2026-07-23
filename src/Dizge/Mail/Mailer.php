<?php

declare(strict_types=1);

namespace Dizge\Mail;

final class Mailer
{
    public function __construct(private Transport $transport) {}
    public function send(Message $message): void { $this->transport->send($message); }
}
