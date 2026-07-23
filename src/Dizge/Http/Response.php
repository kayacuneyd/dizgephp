<?php

declare(strict_types=1);

namespace Dizge\Http;

/** Explicit HTTP response. Route handlers return this value; they do not emit output directly. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $body,
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function raw(string $body, string $contentType, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => $contentType]);
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public static function notFound(): self
    {
        return self::html('<h1>404</h1><p>Not Found</p>', 404);
    }

    /** @param array<string,mixed> $payload */
    public static function api(array $payload, int $status = 200): self
    {
        return self::json($payload, $status)->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (!str_starts_with($location, '/')) throw new \InvalidArgumentException('Redirect locations must be local absolute paths.');
        return new self('', $status, ['Location' => $location]);
    }

    public function status(): int { return $this->status; }
    public function body(): string { return $this->body; }
    public function header(string $name): ?string { return $this->headers[$name] ?? null; }
    public function withHeader(string $name, string $value): self { $copy = clone $this; $copy->headers[$name] = $value; return $copy; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $this->body;
    }
}
