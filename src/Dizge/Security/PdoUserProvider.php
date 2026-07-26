<?php
declare(strict_types=1);
namespace Dizge\Security;
use Dizge\Data\Database;
/** Explicit PDO provider; table/column configuration is trusted deploy-time code. */
final class PdoUserProvider implements UserProvider
{
    public function __construct(private Database $database, private string $table = 'users', private string $identifierColumn = 'email', private string $passwordColumn = 'password_hash', private string $idColumn = 'id', private ?string $activeColumn = null) {}
    public function findByIdentifier(string $identifier): ?UserRecord
    {
        $query = $this->database->table($this->table)->where($this->identifierColumn, '=', $identifier);
        if ($this->activeColumn !== null) $query->where($this->activeColumn, '=', 1);
        $row = $query->first();
        if ($row === null || !isset($row[$this->idColumn], $row[$this->passwordColumn])) return null;
        $attributes = $row; unset($attributes[$this->passwordColumn]);
        return new UserRecord($row[$this->idColumn], (string) $row[$this->passwordColumn], $attributes);
    }
}
