<?php

declare(strict_types=1);

namespace Dizge\Data;

use PDOStatement;

/**
 * Small, explicit query builder. Values are parameters; table and column identifiers are
 * allowlisted syntax, never arbitrary SQL fragments. Complex SQL belongs in a reviewed repository.
 */
final class Query
{
    /** @var list<string> */
    private array $columns = ['*'];
    /** @var list<array{sql: string, value: mixed}> */
    private array $conditions = [];
    /** @var list<string> */
    private array $order = [];
    private ?int $limitValue = null;

    public function __construct(private Database $database, private string $table)
    {
        self::identifier($table);
    }

    /** @param list<string> $columns */
    public function select(array $columns): self
    {
        if ($columns === []) throw new \InvalidArgumentException('select() requires at least one column.');
        $this->columns = array_map(fn (string $column): string => $column === '*' ? '*' : self::identifier($column), $columns);
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $allowedOperators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE'];
        if (!in_array(strtoupper($operator), $allowedOperators, true)) {
            throw new \InvalidArgumentException('Unsupported where operator. Use a documented operator or a repository query.');
        }
        if ($value === null) throw new \InvalidArgumentException('Use whereNull() or whereNotNull() for null values.');
        $this->conditions[] = ['sql' => self::identifier($column) . ' ' . strtoupper($operator) . ' ?', 'value' => $value];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->conditions[] = ['sql' => self::identifier($column) . ' IS NULL', 'value' => null];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->conditions[] = ['sql' => self::identifier($column) . ' IS NOT NULL', 'value' => null];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) throw new \InvalidArgumentException('Order direction must be ASC or DESC.');
        $this->order[] = self::identifier($column) . ' ' . $direction;
        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 1 || $limit > 10000) throw new \InvalidArgumentException('Limit must be between 1 and 10000.');
        $this->limitValue = $limit;
        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        $statement = $this->statement('SELECT ' . implode(', ', $this->columns) . ' FROM ' . self::identifier($this->table) . $this->whereSql() . $this->orderSql() . $this->limitSql());
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $this->limitValue = 1;
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /** @param array<string, scalar|null> $values */
    public function insert(array $values): string
    {
        if ($values === []) throw new \InvalidArgumentException('insert() requires values.');
        $columns = array_keys($values);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', self::identifier($this->table), implode(', ', array_map(self::identifier(...), $columns)), implode(', ', array_fill(0, count($columns), '?')));
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute(array_values($values));
        return $this->database->pdo()->lastInsertId();
    }

    /** @param array<string, scalar|null> $values */
    public function update(array $values): int
    {
        $this->requireCondition('update');
        if ($values === []) throw new \InvalidArgumentException('update() requires values.');
        $columns = array_keys($values);
        $set = implode(', ', array_map(fn (string $column): string => self::identifier($column) . ' = ?', $columns));
        $statement = $this->statement('UPDATE ' . self::identifier($this->table) . ' SET ' . $set . $this->whereSql(), array_values($values));
        return $statement->rowCount();
    }

    public function delete(): int
    {
        $this->requireCondition('delete');
        return $this->statement('DELETE FROM ' . self::identifier($this->table) . $this->whereSql())->rowCount();
    }

    /** @param list<mixed> $leadingParams */
    private function statement(string $sql, array $leadingParams = []): PDOStatement
    {
        $statement = $this->database->pdo()->prepare($sql);
        $params = [...$leadingParams, ...array_map(fn (array $condition): mixed => $condition['value'], array_filter($this->conditions, fn (array $condition): bool => !str_ends_with($condition['sql'], 'NULL')))];
        $statement->execute($params);
        return $statement;
    }

    private function whereSql(): string
    {
        return $this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', array_map(fn (array $condition): string => $condition['sql'], $this->conditions));
    }

    private function orderSql(): string { return $this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order); }
    private function limitSql(): string { return $this->limitValue === null ? '' : ' LIMIT ' . $this->limitValue; }

    private function requireCondition(string $operation): void
    {
        if ($this->conditions === []) throw new \LogicException(sprintf('%s() requires where(), whereNull() or whereNotNull().', $operation));
    }

    private static function identifier(string $identifier): string
    {
        foreach (explode('.', $identifier) as $part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                throw new \InvalidArgumentException(sprintf('Invalid SQL identifier "%s".', $identifier));
            }
        }
        return $identifier;
    }
}
