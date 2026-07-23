<?php

declare(strict_types=1);

namespace Dizge\Http;

/** Immutable HTTP request value. Validation and authorization are explicit later middleware concerns. */
final class Request
{
    /** @param array<string, mixed> $query @param array<string, mixed> $body @param array<string, string> $headers @param array<string, array<string,mixed>> $files */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $body = [],
        private array $headers = [],
        private array $files = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) $headers['remote-addr'] = $_SERVER['REMOTE_ADDR'];
        if (isset($headers['remote-addr'])) {
            $headers['remote-addr'] = TrustedProxy::resolveClientIp(
                $headers['remote-addr'],
                $headers['x-forwarded-for'] ?? null,
                TrustedProxy::fromEnv(),
            );
        }
        $body = $_POST;
        if (str_starts_with(strtolower($headers['content-type'] ?? ''), 'application/json')) {
            try { $body = json_decode(file_get_contents('php://input') ?: '{}', true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException $exception) { throw new HttpException(400, 'Malformed JSON request body.', 'Invalid JSON'); }
        }
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', is_string($path) ? $path : '/', $_GET, is_array($body) ? $body : [], $headers, is_array($_FILES) ? $_FILES : []);
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }

    public function query(string $key, mixed $fallback = null): mixed
    {
        return $this->query[$key] ?? $fallback;
    }

    public function input(string $key, mixed $fallback = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $fallback;
    }

    /** @return array<string,mixed>|null Trusted only after application-level upload validation. */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public function header(string $name, ?string $fallback = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $fallback;
    }

    private static function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '/' ? $normalized : rtrim($normalized, '/');
    }
}
