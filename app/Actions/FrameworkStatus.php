<?php

declare(strict_types=1);

namespace App\Actions;

use Dizge\Application;

/** Shared use-case: web and API controllers select presentation, not business data. */
final class FrameworkStatus
{
    public function __construct(private Application $application) {}

    /** @return array{name:string,environment:string,modules:array<string,mixed>} */
    public function handle(): array
    {
        return [
            'name' => (string) $this->application->config()->get('app.name', 'DizgePHP'),
            'environment' => (string) $this->application->config()->get('app.environment', 'production'),
            'modules' => (array) $this->application->config()->get('modules', []),
        ];
    }
}
