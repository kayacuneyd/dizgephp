<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Data\Database;

/** PII store with bounded retention; only Studio administrators may call its read methods. */
final class ContactSubmissionRepository
{
    public function __construct(private Database $database, private int $retentionDays = 30) {}

    /** @param array{name:string,email:string,subject:string,message:string} $input */
    public function record(array $input): void
    {
        $now = gmdate('c'); $expires = gmdate('c', time() + max(1, min(365, $this->retentionDays)) * 86400);
        $this->purgeExpired();
        $this->database->pdo()->prepare('INSERT INTO contact_submissions (name,email,subject,message,received_at,expires_at) VALUES (?,?,?,?,?,?)')->execute([$input['name'], $input['email'], $input['subject'], $input['message'], $now, $expires]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        $this->purgeExpired(); $statement = $this->database->pdo()->prepare('SELECT * FROM contact_submissions ORDER BY id DESC LIMIT ?');
        $statement->bindValue(1, max(1, min(100, $limit)), \PDO::PARAM_INT); $statement->execute(); return $statement->fetchAll();
    }

    public function markAccessed(int $id, int $actorId): bool
    {
        $statement = $this->database->pdo()->prepare('UPDATE contact_submissions SET accessed_at = ?, accessed_by = ? WHERE id = ? AND expires_at > ?');
        $statement->execute([gmdate('c'), $actorId, $id, gmdate('c')]); return $statement->rowCount() === 1;
    }

    public function purgeExpired(): void { $this->database->pdo()->prepare('DELETE FROM contact_submissions WHERE expires_at <= ?')->execute([gmdate('c')]); }
}
