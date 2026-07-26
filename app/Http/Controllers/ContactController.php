<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendContactMail;
use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Validation\ValidationException;

final class ContactController extends Controller
{
    public function __construct(private SendContactMail $action) {}

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $success = $request->query('sent') === '1';
        return view('promo/contact.php', ['success' => $success, 'errors' => [], 'old' => []]);
    }

    /** @param array<string,string> $params */
    public function submit(Request $request, array $params): Response
    {
        try {
            $this->action->execute($request);
            return Response::redirect('/contact?sent=1');
        } catch (ValidationException $e) {
            return view('promo/contact.php', [
                'success' => false,
                'errors' => $e->errors()->all(),
                'old' => [
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'subject' => $request->input('subject'),
                    'message' => $request->input('message'),
                ],
            ]);
        }
    }
}
