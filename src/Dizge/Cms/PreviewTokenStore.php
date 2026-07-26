<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Data\Database;

/** Opaque, revocable preview capability storage; raw tokens are never persisted. */
final class PreviewTokenStore
{
    public function __construct(private Database $database, private string $secret) {}
    /** @return array{token:string,expires_at:string} */
    public function issue(int $contentId, int $creatorId, int $seconds = 900): array
    {
        if ($this->secret === '') throw new \RuntimeException('Preview signing is not configured.');
        $token = bin2hex(random_bytes(32)); $expires = gmdate('c', time() + max(60, min(3600, $seconds)));
        $this->database->pdo()->prepare('INSERT INTO headless_preview_tokens (token_hash, content_id, created_by, expires_at, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$this->hash($token), $contentId, $creatorId, $expires, gmdate('c')]);
        return ['token' => $token, 'expires_at' => $expires];
    }
    public function contentId(string $token): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $s = $this->database->pdo()->prepare('SELECT content_id FROM headless_preview_tokens WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ?');
        $s->execute([$this->hash($token), gmdate('c')]); $id = $s->fetchColumn(); return $id === false ? null : (int) $id;
    }
    public function revoke(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return;
        $this->database->pdo()->prepare('UPDATE headless_preview_tokens SET revoked_at = ? WHERE token_hash = ? AND revoked_at IS NULL')->execute([gmdate('c'), $this->hash($token)]);
    }
    private function hash(string $token): string { return hash_hmac('sha256', $token, $this->secret); }
}
