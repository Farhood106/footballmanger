<?php

class StructuredSeedImporter
{
    private PDO $db;
    private bool $dryRun;
    private bool $supportsPlayerExternalKey;
    private int $simulatedIdCursor = -1;
    private array $competitionCodeToId = [];
    private array $clubCodeToId = [];

    public function __construct(PDO $db, bool $dryRun = false)
    {
        $this->db = $db;
        $this->dryRun = $dryRun;
        $this->supportsPlayerExternalKey = $this->detectPlayerExternalKeySupport();
    }

    public function importFromDirectory(string $directory): array
    {
        $base = rtrim($directory, DIRECTORY_SEPARATOR);
        $report = [
            'ok' => false,
            'dry_run' => $this->dryRun,
            'source_dir' => $base,
            'supports_player_external_key' => $this->supportsPlayerExternalKey,
            'stages' => [],
            'errors' => [],
            'warnings' => [],
            'totals' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0],
        ];

        $datasets = [
            'competitions' => $this->loadJsonFile($base . '/competitions.json', $report),
            'clubs' => $this->loadJsonFile($base . '/clubs.json', $report),
            'players' => $this->loadJsonFile($base . '/players.json', $report),
        ];

        if (!empty($report['errors'])) {
            return $report;
        }

        $validationErrors = array_merge(
            $this->validateCompetitions($datasets['competitions']),
            $this->validateClubs($datasets['clubs']),
            $this->validatePlayers($datasets['players'])
        );
        if (!empty($validationErrors)) {
            $report['errors'] = array_values(array_merge($report['errors'], $validationErrors));
            $report['totals']['invalid'] = count($validationErrors);
            return $report;
        }

        $inTx = false;
        try {
            $this->hydrateIdentityMaps();
            if (!$this->dryRun) {
                $this->db->beginTransaction();
                $inTx = true;
            }

            $competitionStage = $this->importCompetitions($datasets['competitions']);
            $report['stages']['competitions'] = $competitionStage;
            $this->accumulateTotals($report['totals'], $competitionStage);

            $clubStage = $this->importClubs($datasets['clubs']);
            $report['stages']['clubs'] = $clubStage;
            $this->accumulateTotals($report['totals'], $clubStage);

            $playerStage = $this->importPlayers($datasets['players']);
            $report['stages']['players'] = $playerStage;
            $this->accumulateTotals($report['totals'], $playerStage);
            $report['warnings'] = array_merge($report['warnings'], $playerStage['warnings'] ?? []);

            if ($inTx) {
                $this->db->commit();
            }
            $report['ok'] = true;
        } catch (Throwable $e) {
            if ($inTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $report['errors'][] = 'Import failed: ' . $e->getMessage();
        }

        return $report;
    }

    private function importCompetitions(array $rows): array
    {
        $stage = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0, 'warnings' => []];

        $pending = array_values($rows);
        $pass = 0;
        while (!empty($pending)) {
            $pass++;
            $next = [];
            $resolvedThisPass = 0;

            foreach ($pending as $row) {
                $code = trim((string)$row['external_key']);
                $parentKey = trim((string)($row['parent_external_key'] ?? ''));
                if ($parentKey !== '' && !isset($this->competitionCodeToId[$parentKey])) {
                    $next[] = $row;
                    continue;
                }

                $payload = [
                    'parent_competition_id' => $parentKey !== '' ? (int)$this->competitionCodeToId[$parentKey] : null,
                    'code' => $code,
                    'name' => trim((string)$row['name']),
                    'type' => strtoupper(trim((string)$row['type'])),
                    'country' => trim((string)$row['country']),
                    'level' => (int)$row['level'],
                    'teams_count' => (int)$row['teams_count'],
                    'promotion_slots' => isset($row['promotion_slots']) ? (int)$row['promotion_slots'] : 0,
                    'relegation_slots' => isset($row['relegation_slots']) ? (int)$row['relegation_slots'] : 0,
                ];

                $existingId = (int)($this->competitionCodeToId[$code] ?? 0);
                if ($existingId > 0) {
                    $stage['updated']++;
                    if (!$this->dryRun) {
                        $this->updateById('competitions', $existingId, $payload);
                    }
                    $resolvedThisPass++;
                    continue;
                }

                $stage['inserted']++;
                if ($this->dryRun) {
                    $this->competitionCodeToId[$code] = $this->nextSimulatedId();
                } else {
                    $this->competitionCodeToId[$code] = $this->insert('competitions', $payload);
                }
                $resolvedThisPass++;
            }

            if ($resolvedThisPass === 0) {
                foreach ($next as $row) {
                    $code = trim((string)$row['external_key']);
                    $parentKey = trim((string)($row['parent_external_key'] ?? ''));
                    $stage['invalid']++;
                    $stage['warnings'][] = "Competition {$code} references unknown parent_external_key {$parentKey} (dataset_missing_or_unresolved_stage_map)";
                }
                break;
            }
            $pending = $next;
        }

        return $stage;
    }

    private function importClubs(array $rows): array
    {
        $stage = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0, 'warnings' => []];

        foreach ($rows as $row) {
            $externalKey = trim((string)$row['external_key']);
            $competitionCode = trim((string)($row['competition_external_key'] ?? ''));
            if ($competitionCode !== '') {
                if (!isset($this->competitionCodeToId[$competitionCode])) {
                    $stage['invalid']++;
                    $stage['warnings'][] = "Club {$externalKey} references unknown competition_external_key {$competitionCode} (dataset_missing_or_stage_resolution_failed)";
                    continue;
                }
            }

            $payload = [
                'name' => trim((string)$row['name']),
                'short_name' => $externalKey,
                'country' => trim((string)$row['country']),
                'city' => trim((string)$row['city']),
                'founded' => (int)$row['founded'],
                'reputation' => (int)$row['reputation'],
                'balance' => (int)$row['balance'],
                'stadium_name' => trim((string)$row['stadium_name']),
                'stadium_capacity' => (int)$row['stadium_capacity'],
            ];

            $existingId = (int)($this->clubCodeToId[$externalKey] ?? 0);
            if (!$existingId) {
                $byName = $this->fetchOne("SELECT id FROM clubs WHERE name = ? LIMIT 1", [$payload['name']]);
                $existingId = (int)($byName['id'] ?? 0);
            }

            if ($existingId > 0) {
                $stage['updated']++;
                if (!$this->dryRun) {
                    $this->updateById('clubs', $existingId, $payload);
                }
                $this->clubCodeToId[$externalKey] = $existingId;
                continue;
            }

            $stage['inserted']++;
            if ($this->dryRun) {
                $this->clubCodeToId[$externalKey] = $this->nextSimulatedId();
            } else {
                $this->clubCodeToId[$externalKey] = $this->insert('clubs', $payload);
            }
        }

        return $stage;
    }

    private function importPlayers(array $rows): array
    {
        $stage = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0, 'warnings' => []];

        foreach ($rows as $row) {
            $externalKey = trim((string)$row['external_key']);
            $clubCode = trim((string)$row['club_external_key']);
            $clubId = $this->clubCodeToId[$clubCode] ?? null;
            if (!$clubId) {
                $stage['invalid']++;
                $stage['warnings'][] = "Player {$externalKey} references unknown club_external_key {$clubCode} (dataset_missing_or_stage_resolution_failed)";
                continue;
            }

            $payload = [
                'club_id' => (int)$clubId,
                'first_name' => trim((string)$row['first_name']),
                'last_name' => trim((string)$row['last_name']),
                'nationality' => trim((string)$row['nationality']),
                'birth_date' => trim((string)$row['birth_date']),
                'position' => strtoupper(trim((string)$row['position'])),
                'preferred_foot' => strtoupper(trim((string)($row['preferred_foot'] ?? 'RIGHT'))),
                'pace' => (int)$row['pace'],
                'shooting' => (int)$row['shooting'],
                'passing' => (int)$row['passing'],
                'dribbling' => (int)$row['dribbling'],
                'defending' => (int)$row['defending'],
                'physical' => (int)$row['physical'],
                'overall' => (int)$row['overall'],
                'potential' => (int)$row['potential'],
                'fitness' => isset($row['fitness']) ? (int)$row['fitness'] : 95,
                'morale_score' => isset($row['morale_score']) ? (int)$row['morale_score'] : 70,
                'squad_role' => strtoupper(trim((string)($row['squad_role'] ?? 'ROTATION'))),
                'wage' => (int)$row['wage'],
                'contract_end' => trim((string)$row['contract_end']),
                'market_value' => (int)$row['market_value'],
                'is_transfer_listed' => isset($row['is_transfer_listed']) ? (int)$row['is_transfer_listed'] : 0,
                'asking_price' => isset($row['asking_price']) ? (int)$row['asking_price'] : null,
                'last_minutes_played' => isset($row['last_minutes_played']) ? (int)$row['last_minutes_played'] : 0,
                'last_played_at' => !empty($row['last_played_at']) ? trim((string)$row['last_played_at']) : null,
            ];
            if ($this->supportsPlayerExternalKey) {
                $payload['external_key'] = $externalKey;
            }

            $existingId = 0;
            if ($this->supportsPlayerExternalKey) {
                $byExternal = $this->fetchOne("SELECT id FROM players WHERE external_key = ? LIMIT 1", [$externalKey]);
                $existingId = (int)($byExternal['id'] ?? 0);
            } else {
                $natural = $this->fetchOne(
                    "SELECT id FROM players
                     WHERE club_id = ? AND first_name = ? AND last_name = ? AND birth_date = ?
                     LIMIT 1",
                    [$clubId, $payload['first_name'], $payload['last_name'], $payload['birth_date']]
                );
                $existingId = (int)($natural['id'] ?? 0);
                $stage['warnings'][] = 'players.external_key column not found; using natural-key fallback for upsert detection.';
            }

            if ($existingId > 0) {
                $stage['updated']++;
                if (!$this->dryRun) {
                    $this->updateById('players', $existingId, $payload);
                }
                continue;
            }

            $stage['inserted']++;
            if (!$this->dryRun) {
                $this->insert('players', $payload);
            }
        }

        return $stage;
    }

    private function validateCompetitions(array $rows): array
    {
        $errors = [];
        $seen = [];
        foreach ($rows as $idx => $row) {
            $prefix = 'competitions[' . $idx . ']';
            $errors = array_merge($errors, $this->requireFields($row, $prefix, ['external_key', 'name', 'type', 'country', 'level', 'teams_count']));

            $key = trim((string)($row['external_key'] ?? ''));
            if ($key !== '') {
                if (isset($seen[$key])) {
                    $errors[] = "Duplicate competitions external_key: {$key}";
                }
                $seen[$key] = true;
            }
        }
        return $errors;
    }

    private function validateClubs(array $rows): array
    {
        $errors = [];
        $seen = [];
        foreach ($rows as $idx => $row) {
            $prefix = 'clubs[' . $idx . ']';
            $errors = array_merge($errors, $this->requireFields($row, $prefix, ['external_key', 'name', 'country', 'city', 'founded', 'reputation', 'balance', 'stadium_name', 'stadium_capacity']));

            $key = trim((string)($row['external_key'] ?? ''));
            if ($key !== '') {
                if (isset($seen[$key])) {
                    $errors[] = "Duplicate clubs external_key: {$key}";
                }
                $seen[$key] = true;
            }
        }
        return $errors;
    }

    private function validatePlayers(array $rows): array
    {
        $errors = [];
        $seen = [];
        foreach ($rows as $idx => $row) {
            $prefix = 'players[' . $idx . ']';
            $errors = array_merge($errors, $this->requireFields($row, $prefix, [
                'external_key', 'club_external_key', 'first_name', 'last_name', 'nationality', 'birth_date',
                'position', 'overall', 'potential', 'pace', 'shooting', 'passing', 'dribbling', 'defending',
                'physical', 'wage', 'contract_end', 'market_value'
            ]));

            $key = trim((string)($row['external_key'] ?? ''));
            if ($key !== '') {
                if (isset($seen[$key])) {
                    $errors[] = "Duplicate players external_key: {$key}";
                }
                $seen[$key] = true;
            }

            $listed = (int)($row['is_transfer_listed'] ?? 0);
            if ($listed === 1 && empty($row['asking_price'])) {
                $errors[] = "Player {$key} is transfer-listed but asking_price is missing";
            }
        }
        return $errors;
    }

    private function requireFields(array $row, string $prefix, array $required): array
    {
        $errors = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                $errors[] = "Missing required field {$prefix}.{$field}";
            }
        }
        return $errors;
    }

    private function loadJsonFile(string $path, array &$report): array
    {
        if (!is_file($path)) {
            $report['errors'][] = 'Missing seed file: ' . $path;
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $report['errors'][] = 'Unable to read seed file: ' . $path;
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $report['errors'][] = 'Malformed JSON in: ' . $path;
            return [];
        }

        return $decoded;
    }

    private function detectPlayerExternalKeySupport(): bool
    {
        $row = $this->fetchOne(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'external_key'"
        );
        return $row !== null;
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function insert(string $table, array $payload): int
    {
        $cols = array_keys($payload);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($payload));
        return (int)$this->db->lastInsertId();
    }

    private function updateById(string $table, int $id, array $payload): void
    {
        $sets = [];
        foreach (array_keys($payload) as $col) {
            $sets[] = $col . ' = ?';
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $values = array_values($payload);
        $values[] = $id;
        $stmt->execute($values);
    }

    private function accumulateTotals(array &$totals, array $stage): void
    {
        $totals['inserted'] += (int)($stage['inserted'] ?? 0);
        $totals['updated'] += (int)($stage['updated'] ?? 0);
        $totals['skipped'] += (int)($stage['skipped'] ?? 0);
        $totals['invalid'] += (int)($stage['invalid'] ?? 0);
    }

    private function hydrateIdentityMaps(): void
    {
        $this->competitionCodeToId = [];
        foreach ($this->db->query("SELECT id, code FROM competitions WHERE code IS NOT NULL") as $existing) {
            $code = trim((string)($existing['code'] ?? ''));
            if ($code !== '') {
                $this->competitionCodeToId[$code] = (int)$existing['id'];
            }
        }

        $this->clubCodeToId = [];
        foreach ($this->db->query("SELECT id, short_name FROM clubs WHERE short_name IS NOT NULL") as $existing) {
            $short = trim((string)($existing['short_name'] ?? ''));
            if ($short !== '') {
                $this->clubCodeToId[$short] = (int)$existing['id'];
            }
        }
    }

    private function nextSimulatedId(): int
    {
        return $this->simulatedIdCursor--;
    }
}
