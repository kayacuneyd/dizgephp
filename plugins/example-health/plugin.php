<?php

declare(strict_types=1);

use Dizge\Cms\ExtensionRegistry;
use Dizge\Http\Request;
use Dizge\Http\Response;

return static function (ExtensionRegistry $extensions): void {
    $extensions->registerRoute('GET', '/api/v1/extensions/example-health', static function (Request $request): Response {
        return Response::api(['extension' => 'example-health', 'status' => 'ok']);
    });
};
