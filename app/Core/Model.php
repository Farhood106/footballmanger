<?php
// app/Core/Model.php

abstract class Model {
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        ) ?: null;
    }

    public function findBy(string $column, mixed $value): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE {$column} = ?",
            [$value]
        ) ?: null;
    }

    public function all(string $orderBy = 'id', string $dir = 'ASC'): array {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$dir}"
        );
    }

    public function create(array $data): int {
        return $this->db->insert($this->table, $data);
    }

    public function update(int $id, array $data): void {
        $this->db->update($this->table, $data, "{$this->primaryKey} = :id", ['id' => $id]);
    }

    public function delete(int $id): void {
        $this->db->delete($this->table, "{$this->primaryKey} = ?", [$id]);
    }

    public function count(string $where = '1', array $params = []): int {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM {$this->table} WHERE {$where}",
            $params
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function paginate(int $page, int $perPage = 20, string $where = '1', array $params = []): array {
        $offset = ($page - 1) * $perPage;
        $total = $this->count($where, $params);
        $items = $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE {$where} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current' => $page
        ];
    }
}
