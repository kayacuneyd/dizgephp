<?php

declare(strict_types=1);

namespace Dizge\Mail;

interface Transport { public function send(Message $message): void; }
