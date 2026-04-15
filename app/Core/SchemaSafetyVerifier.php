<?php
// app/Core/SchemaSafetyVerifier.php

class SchemaSafetyVerifier {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    public function verifyOrFail(): void {
        if ($this->isVerificationDisabled()) {
            return;
        }

        $missing = $this->detectMissingRequirements();
        $decision = self::buildDecision($missing, $this->db->shouldRunRuntimeDdlFallback());

        if ($decision['level'] === 'error') {
            throw new RuntimeException((string)$decision['message']);
        }

        if ($decision['level'] === 'warning') {
            error_log('[Schema Verification WARNING] ' . (string)$decision['message']);
        }
    }

    public static function buildDecision(array $missing, bool $fallbackEnabled): array {
        if (empty($missing)) {
            return ['ok' => true, 'level' => 'ok', 'message' => 'Schema verification passed.'];
        }

        $lines = array_map(
            static fn(array $m): string => sprintf('%s %s%s', (string)$m['type'], (string)$m['name'], !empty($m['detail']) ? (' (' . (string)$m['detail'] . ')') : ''),
            $missing
        );

        $message = "Schema verification found missing requirements:\n - " . implode("\n - ", $lines);

        if ($fallbackEnabled) {
            return [
                'ok' => true,
                'level' => 'warning',
                'message' => $message . "\nRuntime DDL fallback is ON (RUNTIME_DDL_FALLBACK=1), continuing with compatibility mode."
            ];
        }

        return [
            'ok' => false,
            'level' => 'error',
            'message' => $message . "\nStartup aborted: required migrations are missing and runtime DDL fallback is OFF."
        ];
    }

    public function detectMissingRequirements(): array {
        $missing = [];

        foreach (self::requiredTables() as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = ['type' => 'table', 'name' => $table, 'detail' => 'missing'];
            }
        }

        foreach (self::requiredColumns() as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    $missing[] = ['type' => 'column', 'name' => $table . '.' . $column, 'detail' => 'missing'];
                }
            }
        }

        foreach (self::requiredEnumTokens() as $table => $columns) {
            foreach ($columns as $column => $tokens) {
                foreach ($tokens as $token) {
                    if (!$this->enumContains($table, $column, $token)) {
                        $missing[] = ['type' => 'enum', 'name' => $table . '.' . $column, 'detail' => 'missing token ' . $token];
                    }
                }
            }
        }

        return $missing;
    }

    private static function requiredTables(): array {
        return [
            'club_manager_expectations',
            'club_manager_applications',
            'manager_contract_negotiations',
            'club_sponsors',
            'club_owner_funding_events',
            'club_facilities',
            'player_career_history',
            'club_control_runtime_states',
            'season_rollover_logs',
            'competition_qualification_slots',
            'player_awards',
            'club_honors',
            'club_records',
            'club_legends',
            'admin_operation_logs',
        ];
    }

    private static function requiredColumns(): array {
        return [
            'players' => ['fitness', 'morale_score', 'is_transfer_listed', 'asking_price', 'transfer_listed_at', 'squad_role', 'last_played_at', 'last_minutes_played'],
            'transfers' => ['season_id', 'counter_fee', 'negotiation_round', 'countered_at', 'responded_at'],
            'club_finance_ledger' => ['meta_json', 'entry_type'],
            'club_manager_applications' => ['reviewed_by_user_id', 'rejection_reason', 'status'],
            'player_season_stats' => ['starts'],
        ];
    }

    private static function requiredEnumTokens(): array {
        return [
            'club_finance_ledger' => [
                'entry_type' => ['COACH_SALARY', 'SPONSOR_INCOME', 'FACILITY_MAINTENANCE', 'MANUAL_ADMIN_ADJUSTMENT']
            ]
        ];
    }

    private function tableExists(string $table): bool {
        $row = $this->db->fetchOne(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        );
        return $row !== null;
    }

    private function columnExists(string $table, string $column): bool {
        $row = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        return $row !== null;
    }

    private function enumContains(string $table, string $column, string $token): bool {
        $row = $this->db->fetchOne(
            "SELECT COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        if (!$row) {
            return false;
        }

        return strpos((string)$row['COLUMN_TYPE'], "'{$token}'") !== false;
    }

    private function isVerificationDisabled(): bool {
        $raw = getenv('SCHEMA_VERIFY_DISABLE');
        if ($raw === false) {
            return false;
        }
        $value = strtolower(trim((string)$raw));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
