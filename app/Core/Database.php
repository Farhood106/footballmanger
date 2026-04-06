<?php
// app/Core/Database.php

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $config = require __DIR__ . '/../../config/config.php';
        $db = $config['db'];

        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

        try {
            $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            error_log('[DB Connection Error] ' . $e->getMessage());
            throw new RuntimeException('خطا در اتصال به پایگاه داده.');
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ------- identifier quoting -------

    private function quoteIdentifier(string $name): string {
        // فقط حروف، اعداد و underscore مجاز است
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("نام identifier نامعتبر است: {$name}");
        }
        return '`' . $name . '`';
    }

    // ------- core query methods -------

    public function query(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('[DB Query Error] ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new RuntimeException('خطا در اجرای کوئری.');
        }
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function execute(string $sql, array $params = []): int {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): int {
        return (int) $this->pdo->lastInsertId();
    }

    // ------- insert with quoted identifiers -------

    public function insert(string $table, array $data): int {
        $quotedTable = $this->quoteIdentifier($table);

        $columns      = array_keys($data);
        $quotedCols   = array_map(fn($c) => $this->quoteIdentifier($c), $columns);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $quotedTable,
            implode(', ', $quotedCols),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return $this->lastInsertId();
    }

    // ------- transactions -------

    public function beginTransaction(): void {
        $this->pdo->beginTransaction();
    }

    public function commit(): void {
        $this->pdo->commit();
    }

    public function rollBack(): void {
        $this->pdo->rollBack();
    }
}
