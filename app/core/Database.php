<?php

declare(strict_types=1);

namespace Govyx\Core;

use PDO;
use PDOException;

final class Database
{
    private PDO $pdo;

    public function __construct(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
        ];
        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $options);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Run a query, returning the statement. Parameterized only - no string interpolation into SQL. */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows. */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Fetch single row or null. */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch single scalar value. */
    public function scalar(string $sql, array $params = []): mixed
    {
        $val = $this->query($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn($c) => $this->quoteIdentifier($c), $cols)),
            implode(', ', $placeholders)
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): bool
    {
        $sets = array_map(fn($c) => $this->quoteIdentifier($c) . ' = :' . $c, array_keys($data));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->quoteIdentifier($table), implode(', ', $sets), $where);
        return $this->query($sql, array_merge($data, $whereParams))->rowCount() >= 0;
    }

    public function count(string $sql, array $params = []): int
    {
        return (int) $this->scalar($sql, $params);
    }

    public function delete(string $table, string $where, array $whereParams = []): bool
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $this->quoteIdentifier($table), $where);
        $this->query($sql, $whereParams);
        return true;
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function quoteIdentifier(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }
}