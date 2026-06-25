<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = ['password_hash', 'remember_token', 'two_factor_secret'];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int|string $id): array|false
    {
        $whereClause = $this->softDeletes
            ? "`{$this->primaryKey}` = ? AND `deleted_at` IS NULL"
            : "`{$this->primaryKey}` = ?";

        $row = $this->db->selectOne(
            "SELECT * FROM `{$this->table}` WHERE {$whereClause}",
            [$id]
        );

        return $row ? $this->hideFields($row) : false;
    }

    public function findBy(string $column, mixed $value): array|false
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1",
            [$value]
        );
        return $row ? $this->hideFields($row) : false;
    }

    public function all(string $orderBy = '', string $direction = 'ASC'): array
    {
        $where = $this->softDeletes ? 'WHERE `deleted_at` IS NULL' : '';
        $order = $orderBy ? "ORDER BY `{$orderBy}` {$direction}" : '';
        $rows  = $this->db->select("SELECT * FROM `{$this->table}` {$where} {$order}");
        return array_map(fn($r) => $this->hideFields($r), $rows);
    }

    public function create(array $data): int|string
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }

        return $this->db->insertInto($this->table, $data);
    }

    public function update(int|string $id, array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->db->updateTable($this->table, $data, "`{$this->primaryKey}` = ?", [$id]);
    }

    public function delete(int|string $id): int
    {
        if ($this->softDeletes) {
            return $this->db->updateTable(
                $this->table,
                ['deleted_at' => date('Y-m-d H:i:s')],
                "`{$this->primaryKey}` = ?",
                [$id]
            );
        }
        return $this->db->delete($this->table, "`{$this->primaryKey}` = ?", [$id]);
    }

    public function where(string $column, mixed $value, string $operator = '='): array
    {
        $softDeleteClause = $this->softDeletes ? " AND `deleted_at` IS NULL" : '';
        $rows = $this->db->select(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` {$operator} ? {$softDeleteClause}",
            [$value]
        );
        return array_map(fn($r) => $this->hideFields($r), $rows);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return $this->db->count($this->table, $where, $params);
    }

    public function exists(string $column, mixed $value, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) as cnt FROM `{$this->table}` WHERE `{$column}` = ?";
        $params = [$value];

        if ($excludeId !== null) {
            $sql    .= " AND `{$this->primaryKey}` != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->selectOne($sql, $params);
        return ($result['cnt'] ?? 0) > 0;
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function hideFields(array $row): array
    {
        foreach ($this->hidden as $field) {
            unset($row[$field]);
        }
        return $row;
    }

    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
