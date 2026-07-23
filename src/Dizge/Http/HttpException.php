<?php

declare(strict_types=1);

namespace Dizge\Http;

final class HttpException extends \RuntimeException
{
    public function __construct(private int $status, string $message, private string $title = 'Request failed')
    {
        parent::__construct($message);
    }

    public function status(): int { return $this->status; }
    public function title(): string { return $this->title; }
}
