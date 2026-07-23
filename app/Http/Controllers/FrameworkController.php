<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FrameworkStatus;
use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;

final class FrameworkController extends Controller
{
    public function __construct(private FrameworkStatus $status) {}

    /** @param array<string,string> $params */
    public function web(Request $request, array $params): Response
    {
        $data = $this->status->handle();
        return view('framework.php', ['title' => $data['name'], 'framework' => $data]);
    }

    /** @param array<string,string> $params */
    public function api(Request $request, array $params): Response
    {
        return Response::api(['data' => $this->status->handle()]);
    }
}
