<?php
// app/Models/BaseModel.php

abstract class BaseModel {
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    /**
     * ستون‌هایی که mass-assignment روی آن‌ها مجاز است.
     * در هر Model فرزند باید override شود.
     */
    protected array $fillable = [];

    /**
     * ستون‌هایی که نباید در خروجی عمومی نمایش داده شوند.
     */
    protected array $hidden = [];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ------- helpers -------

    /**
     * فقط کلیدهایی که در $fillable هستند را نگه می‌دارد.
     */
    protected function filterFillable(array $data): array {
        if (empty($this->fillable)) return $data; // اگر fillable تعریف نشده، همه را قبول کن
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * نام ستون را اعتبارسنجی می‌کند (فقط حروف، اعداد، underscore).
     */
    protected function isValidColumn(string $col): bool {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col);
    }

    /**
     * عبارت ORDER BY را اعتبارسنجی می‌کند.
     * مثال معتبر: "created_at DESC" یا "name ASC, id DESC"
     */
    protected function isValidOrderBy(string $orderBy): bool {
        return (bool) preg_match(
            '/^([a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?(\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?)*)$/i',
            trim($orderBy)
        );
    }

    /**
     * ویژگی‌های hidden را از نتیجه حذف می‌کند.
     */
    protected function hideAttributes(array $row): array {
        foreach ($this->hidden as $col) {
            unset($row[$col]);
        }
        return $row;
    }

    protected function hideFromCollection(array $rows): array {
        return array_map(fn($row) => $this->hideAttributes($row), $rows);
    }

    // ------- CRUD -------

    public function find(int $id): ?array {
        $row = $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
        return $row ? $this->hideAttributes($row) : null;
    }

    /**
     * @param array  $conditions  ['column' => value, ...]  — کلیدها اعتبارسنجی می‌شوند
     * @param string $orderBy     مثال: "created_at DESC"
     * @param int    $limit       0 = بدون محدودیت
     */
    public function findAll(array $conditions = [], string $orderBy = '', int $limit = 0): array {
        $sql    = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                if (!$this->isValidColumn($col)) {
                    throw new InvalidArgumentException("نام ستون نامعتبر: {$col}");
                }
                $clauses[] = "`{$col}` = ?";
                $params[]  = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if ($orderBy !== '') {
            if (!$this->isValidOrderBy($orderBy)) {
                throw new InvalidArgumentException("عبارت ORDER BY نامعتبر: {$orderBy}");
            }
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        return $this->hideFromCollection($this->db->fetchAll($sql, $params));
    }

    public function create(array $data): int {
        $filtered = $this->filterFillable($data);
        if (empty($filtered)) {
            throw new InvalidArgumentException('هیچ داده‌ای برای insert وجود ندارد.');
        }
        return $this->db->insert($this->table, $filtered);
    }

    public function update(int $id, array $data): bool {
        $filtered = $this->filterFillable($data);
        if (empty($filtered)) return false;

        $fields = [];
        $params = [];

        foreach ($filtered as $col => $val) {
            if (!$this->isValidColumn($col)) {
                throw new InvalidArgumentException("نام ستون نامعتبر: {$col}");
            }
            $fields[] = "`{$col}` = ?";
            $params[]  = $val;
        }

        $params[] = $id;
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $fields)
             . " WHERE `{$this->primaryKey}` = ?";

        return $this->db->execute($sql, $params) > 0;
    }

    public function delete(int $id): bool {
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        ) > 0;
    }

    public function count(array $conditions = []): int {
        $sql    = "SELECT COUNT(*) as cnt FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                if (!$this->isValidColumn($col)) {
                    throw new InvalidArgumentException("نام ستون نامعتبر: {$col}");
                }
                $clauses[] = "`{$col}` = ?";
                $params[]  = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $row = $this->db->fetchOne($sql, $params);
        return (int) ($row['cnt'] ?? 0);
    }
}
