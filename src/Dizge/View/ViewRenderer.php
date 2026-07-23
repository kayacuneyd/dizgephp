<?php

declare(strict_types=1);

namespace Dizge\View;

/** Small PHP view renderer. Templates are PHP files; values must use the global e() helper. */
final class ViewRenderer
{
    public function __construct(private string $viewPath) {}

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        if (str_contains($template, '..') || !preg_match('/^[A-Za-z0-9_\/.-]+\.php$/', $template)) {
            throw new \InvalidArgumentException('Invalid view template name.');
        }
        $file = $this->viewPath . '/' . ltrim($template, '/');
        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('View "%s" was not found.', $template));
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
