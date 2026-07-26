<?php

declare(strict_types=1);

namespace App\Actions;

use Dizge\Cms\ContactSubmissionRepository;
use Dizge\Http\Request;
use Dizge\Mail\Mailer;
use Dizge\Mail\Message;
use Dizge\Validation\Validator;

final class SendContactMail
{
    /** @param list<string> $recipients */
    public function __construct(private Mailer $mailer, private array $recipients, private ContactSubmissionRepository $submissions) {}

    /** @return array<string, mixed> */
    public function execute(Request $request): array
    {
        $input = [
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'subject' => trim((string) $request->input('subject')),
            'message' => trim((string) $request->input('message')),
        ];

        (new Validator())->validate($input, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|min:10|max:5000',
        ]);

        $from = $input['email'];
        $body = "Ad: {$input['name']}\nE-posta: {$input['email']}\nKonu: {$input['subject']}\n\n{$input['message']}";

        foreach ($this->recipients as $recipient) {
            $this->mailer->send(new Message($recipient, "[DizgePHP] {$input['subject']}", $body));
        }
        $this->submissions->record($input);

        return $input;
    }
}
