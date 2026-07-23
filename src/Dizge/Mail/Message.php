<?php

declare(strict_types=1);

namespace Dizge\Mail;

final class Message
{
    public function __construct(public readonly string $to, public readonly string $subject, public readonly string $text) {}
    /** @return array{to:string,subject:string,text:string} */
    public function toArray(): array { return ['to' => $this->to, 'subject' => $this->subject, 'text' => $this->text]; }
    /** @param array{to:string,subject:string,text:string} $data */
    public static function fromArray(array $data): self { return new self($data['to'], $data['subject'], $data['text']); }
}
