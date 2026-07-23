<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Data\Database;

/** Database-backed bearer tokens. Only SHA-256 hashes are persisted. */
final class ApiTokenStore
{
    public function __construct(private Database $database) {}

    /** @param list<string> $abilities @return array{token:string,id:int} */
    public function issue(string $subjectId, string $name, array $abilities = ['*'], ?\DateTimeImmutable $expiresAt = null): array
    {
        if ($subjectId === '' || $name === '' || $abilities === []) throw new \InvalidArgumentException('Token subject, name and abilities are required.');
        foreach ($abilities as $ability) if (!is_string($ability) || !preg_match('/^[a-z*][a-z0-9.*_-]{0,80}$/', $ability)) throw new \InvalidArgumentException('Token ability is invalid.');
        $secret = 'dizge_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = gmdate('c');
        $this->database->pdo()->prepare('INSERT INTO dizge_api_tokens (subject_id, name, token_hash, abilities_json, expires_at, created_at) VALUES (:subject_id, :name, :token_hash, :abilities_json, :expires_at, :created_at)')->execute([
            ':subject_id' => $subjectId, ':name' => $name, ':token_hash' => hash('sha256', $secret), ':abilities_json' => json_encode(array_values($abilities), JSON_THROW_ON_ERROR), ':expires_at' => $expiresAt?->format('c'), ':created_at' => $now,
        ]);
        return ['token' => $secret, 'id' => (int) $this->database->pdo()->lastInsertId()];
    }

    public function authenticate(string $secret): ?ApiToken
    {
        if (!preg_match('/^dizge_[A-Za-z0-9_-]{20,}$/', $secret)) return null;
        $statement = $this->database->pdo()->prepare('SELECT * FROM dizge_api_tokens WHERE token_hash = :hash LIMIT 1');
        $statement->execute([':hash' => hash('sha256', $secret)]); $row = $statement->fetch();
        if (!is_array($row) || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time())) return null;
        $abilities = json_decode((string) $row['abilities_json'], true);
        if (!is_array($abilities)) return null;
        $this->database->pdo()->prepare('UPDATE dizge_api_tokens SET last_used_at = :used WHERE id = :id')->execute([':used' => gmdate('c'), ':id' => $row['id']]);
        return new ApiToken((string) $row['subject_id'], array_values(array_filter($abilities, 'is_string')));
    }

    public function revoke(int $id): void { $this->database->pdo()->prepare('DELETE FROM dizge_api_tokens WHERE id = :id')->execute([':id' => $id]); }
}
