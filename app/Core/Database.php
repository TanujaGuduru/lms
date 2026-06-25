<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private array $queryLog = [];
    private bool $logQueries;

    private function __construct()
    {
        $config = require BASE_PATH . '/config/database.php';
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}",
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            Logger::critical('Database connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Database connection failed. Please check configuration.');
        }

        $this->logQueries = (APP_ENV === 'development');
    }

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($this->logQueries) {
                $this->queryLog[] = [
                    'sql'      => $sql,
                    'params'   => $params,
                    'time_ms'  => round((microtime(true) - $start) * 1000, 2),
                ];
            }

            return $stmt;
        } catch (PDOException $e) {
            Logger::error('Query failed', ['sql' => $sql, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function selectOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function insert(string $sql, array $params = []): int|string
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function update(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function insertInto(string $table, array $data): int|string
    {
        $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($columns) VALUES ($placeholders)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function updateTable(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $stmt = $this->query(
            "UPDATE `$table` SET $set WHERE $where",
            array_merge(array_values($data), $whereParams)
        );
        return $stmt->rowCount();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->select($sql, $params);
    }

    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->selectOne($sql, $params);
    }

    public function execute(string $sql, array $params = []): PDOStatement
    {
        return $this->query($sql, $params);
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public function count(string $table, string $where = '1', array $params = []): int
    {
        $result = $this->selectOne("SELECT COUNT(*) as cnt FROM `$table` WHERE $where", $params);
        return (int)($result['cnt'] ?? 0);
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void          { $this->pdo->commit(); }
    public function rollBack(): void        { $this->pdo->rollBack(); }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }

    public function getQueryLog(): array { return $this->queryLog; }

    public function paginate(string $sql, array $params, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as sub";
        $total = (int)($this->selectOne($countSql, $params)['total'] ?? 0);
        $data = $this->select("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / $perPage),
            'from'         => $offset + 1,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton.'); }
}
