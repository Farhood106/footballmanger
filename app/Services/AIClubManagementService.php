<?php
// app/Services/AIClubManagementService.php

class AIClubManagementService {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        if ($this->db->shouldRunRuntimeDdlFallback()) {
            $this->ensureControlRuntimeTable();
        }
    }

    public static function determineControlState(array $club): array {
        $ownerId = (int)($club['owner_user_id'] ?? 0);
        $managerId = (int)($club['manager_user_id'] ?? 0);

        $hasOwner = $ownerId > 0;
        $hasManager = $managerId > 0;

        if ($hasOwner && $hasManager && $ownerId === $managerId) {
            return ['key' => 'OWNER_SELF_MANAGED', 'is_ai_owner' => false, 'is_ai_manager' => false, 'is_caretaker' => false, 'owner_vacant' => false, 'manager_vacant' => false];
        }
        if ($hasOwner && $hasManager) {
            return ['key' => 'HUMAN_OWNER_HUMAN_MANAGER', 'is_ai_owner' => false, 'is_ai_manager' => false, 'is_caretaker' => false, 'owner_vacant' => false, 'manager_vacant' => false];
        }
        if ($hasOwner && !$hasManager) {
            return ['key' => 'HUMAN_OWNER_CARETAKER', 'is_ai_owner' => false, 'is_ai_manager' => true, 'is_caretaker' => true, 'owner_vacant' => false, 'manager_vacant' => true];
        }
        if (!$hasOwner && $hasManager) {
            return ['key' => 'AI_OWNER_HUMAN_MANAGER', 'is_ai_owner' => true, 'is_ai_manager' => false, 'is_caretaker' => false, 'owner_vacant' => true, 'manager_vacant' => false];
        }

        return ['key' => 'AI_OWNER_CARETAKER', 'is_ai_owner' => true, 'is_ai_manager' => true, 'is_caretaker' => true, 'owner_vacant' => true, 'manager_vacant' => true];
    }

    public function getClubControlState(int $clubId): array {
        $this->syncClubVacancyState($clubId);
        $club = $this->db->fetchOne(
            "SELECT c.id, c.name, c.owner_user_id, c.manager_user_id, r.owner_vacancy_since, r.manager_vacancy_since
             FROM clubs c
             LEFT JOIN club_control_runtime_states r ON r.club_id = c.id
             WHERE c.id = ?",
            [$clubId]
        ) ?: ['id' => $clubId, 'name' => 'unknown', 'owner_user_id' => null, 'manager_user_id' => null];

        $state = self::determineControlState($club);
        return array_merge($club, $state);
    }

    public function listClubControlStates(): array {
        $this->syncVacancyStatesForAllClubs();
        $rows = $this->db->fetchAll(
            "SELECT c.id, c.name, c.owner_user_id, c.manager_user_id,
                    o.username AS owner_name, m.username AS manager_name,
                    r.owner_vacancy_since, r.manager_vacancy_since
             FROM clubs c
             LEFT JOIN users o ON o.id = c.owner_user_id
             LEFT JOIN users m ON m.id = c.manager_user_id
             LEFT JOIN club_control_runtime_states r ON r.club_id = c.id
             ORDER BY c.name ASC"
        );

        return array_map(function (array $row): array {
            return array_merge($row, self::determineControlState($row));
        }, $rows);
    }

    public function syncVacancyStatesForAllClubs(): array {
        $rows = $this->db->fetchAll("SELECT id FROM clubs ORDER BY id ASC");
        $synced = 0;
        foreach ($rows as $row) {
            if ($this->syncClubVacancyState((int)$row['id'])) {
                $synced++;
            }
        }
        return ['ok' => true, 'synced' => $synced];
    }

    public function applyDailyPreparation(int $clubId, string $cycleDate): array {
        $this->syncClubVacancyState($clubId);
        $state = $this->getClubControlState($clubId);
        if (!$state['is_ai_manager']) {
            return ['ok' => true, 'mode' => 'human_managed_skip'];
        }

        $playedToday = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM matches
             WHERE DATE(scheduled_at) = ? AND (home_club_id = ? OR away_club_id = ?)",
            [$cycleDate, $clubId, $clubId]
        )['c'] ?? 0) > 0;

        if ($playedToday) {
            $this->db->execute(
                "UPDATE players
                 SET fatigue = GREATEST(0, fatigue - 8),
                     morale = LEAST(10, morale + 0.05)
                 WHERE club_id = ? AND is_retired = 0",
                [$clubId]
            );
            $market = $this->runDailyTransferMarket($clubId);
            return ['ok' => true, 'mode' => 'recovery', 'market' => $market];
        }

        $this->db->execute(
            "UPDATE players
             SET fatigue = GREATEST(0, fatigue - 3),
                 morale = LEAST(10, morale + 0.02)
             WHERE club_id = ? AND is_retired = 0",
            [$clubId]
        );

        $market = $this->runDailyTransferMarket($clubId);
        return ['ok' => true, 'mode' => 'balanced', 'market' => $market];
    }

    public function runDailyTransferMarket(int $clubId): array {
        $transferModel = new TransferModel();
        $listed = 0;
        $responded = 0;
        $bidPlaced = 0;

        $candidates = $this->db->fetchAll(
            "SELECT id, club_id, is_transfer_listed, market_value, wage, morale_score, squad_role, last_minutes_played, potential, overall
             FROM players
             WHERE club_id = ? AND is_retired = 0 AND is_injured = 0
             ORDER BY overall ASC, id ASC
             LIMIT 8",
            [$clubId]
        );
        foreach ($candidates as $p) {
            if ((int)($p['is_transfer_listed'] ?? 0) === 1) {
                continue;
            }
            $role = strtoupper((string)($p['squad_role'] ?? 'ROTATION'));
            $moraleScore = (int)($p['morale_score'] ?? 70);
            $lastMinutes = (int)($p['last_minutes_played'] ?? 0);
            $wage = (int)($p['wage'] ?? 0);
            $canList = in_array($role, ['BENCH', 'PROSPECT', 'ROTATION'], true) && ($moraleScore < 58 || $lastMinutes <= 10 || $wage > 26000);
            if ($canList) {
                $asking = max(1, (int)round(((int)($p['market_value'] ?? 1)) * 1.03));
                $transferModel->setTransferListed((int)$p['id'], $clubId, true, $asking);
                $listed++;
                if ($listed >= 2) {
                    break;
                }
            }
        }

        $incoming = $transferModel->getIncomingOffers($clubId);
        foreach ($incoming as $offer) {
            if (($offer['status'] ?? '') !== 'PENDING') {
                continue;
            }
            $decision = $transferModel->determineSellerDecision($offer, $offer);
            if (($decision['action'] ?? '') === 'accept') {
                $transferModel->accept((int)$offer['id']);
            } elseif (($decision['action'] ?? '') === 'counter') {
                $transferModel->counter((int)$offer['id'], $clubId, (int)($decision['counter_fee'] ?? 0));
            } else {
                $transferModel->reject((int)$offer['id']);
            }
            $responded++;
        }

        $club = $this->db->fetchOne("SELECT id, balance FROM clubs WHERE id = ?", [$clubId]);
        if ((int)($club['balance'] ?? 0) > 1500000) {
            $target = $this->db->fetchOne(
                "SELECT p.*
                 FROM players p
                 JOIN clubs c ON c.id = p.club_id
                 WHERE p.is_transfer_listed = 1
                   AND p.club_id <> ?
                   AND p.is_retired = 0
                 ORDER BY p.morale_score ASC, p.last_minutes_played ASC, p.market_value ASC
                 LIMIT 1",
                [$clubId]
            );
            if ($target) {
                $pricing = $transferModel->buildPricingContext($target);
                $bid = (int)$pricing['min_accept'];
                if ($bid > 0 && $bid < (int)$club['balance']) {
                    $transferModel->makeBid((int)$target['id'], (int)$target['club_id'], $clubId, $bid);
                    $bidPlaced = 1;
                }
            }
        }

        return ['listed' => $listed, 'responded' => $responded, 'bid_placed' => $bidPlaced];
    }

    public function ensureLineupForMatchPhase(int $clubId, string $lineupPhase): array {
        $state = $this->getClubControlState($clubId);
        if (!$state['is_ai_manager']) {
            return ['ok' => false, 'error' => 'Human-managed club lineup must not be overridden.'];
        }

        $rows = $this->fetchCandidateLineupRows($clubId, $lineupPhase);
        $validation = DailyCycleOrchestrator::validateLineupRows($rows);
        if ($validation['ok']) {
            return ['ok' => true, 'generated' => false, 'lineup' => $rows];
        }

        $selected = $this->buildAiLineup($clubId);
        if (count($selected) < 11) {
            return ['ok' => false, 'error' => 'AI manager could not build 11-player lineup.'];
        }

        $this->db->execute(
            "UPDATE tactic_lineups SET is_active = 0 WHERE club_id = ? AND phase_key = ?",
            [$clubId, $lineupPhase]
        );

        foreach ($selected as $row) {
            $this->db->insert('tactic_lineups', [
                'club_id' => $clubId,
                'phase_key' => $lineupPhase,
                'player_id' => (int)$row['player_id'],
                'position_slot' => $row['position_slot'],
                'slot_order' => (int)($row['slot_order'] ?? 1),
                'is_active' => 1,
            ]);
        }

        $rows = $this->fetchCandidateLineupRows($clubId, $lineupPhase);
        $validation = DailyCycleOrchestrator::validateLineupRows($rows);
        if (!$validation['ok']) {
            return ['ok' => false, 'error' => 'AI lineup failed validation: ' . $validation['reason']];
        }

        return ['ok' => true, 'generated' => true, 'lineup' => $rows];
    }

    private function fetchCandidateLineupRows(int $clubId, string $lineupPhase): array {
        return $this->db->fetchAll(
            "SELECT tl.player_id, tl.position_slot, tl.slot_order, p.position AS actual_position
             FROM tactic_lineups tl
             JOIN players p ON p.id = tl.player_id
             WHERE tl.club_id = ? AND tl.phase_key IN (?, 'MATCH_1') AND tl.is_active = 1
             ORDER BY CASE WHEN tl.phase_key = ? THEN 0 ELSE 1 END, tl.position_slot, tl.slot_order, tl.id",
            [$clubId, $lineupPhase, $lineupPhase]
        );
    }

    private function buildAiLineup(int $clubId): array {
        $players = $this->db->fetchAll(
            "SELECT id, position, overall, fitness, fatigue, last_minutes_played, last_played_at
             FROM players
             WHERE club_id = ? AND is_retired = 0 AND is_injured = 0
             ORDER BY overall DESC, fitness DESC, id ASC",
            [$clubId]
        );

        $slots = [
            ['position_slot' => 'GK', 'slot_order' => 1],
            ['position_slot' => 'LB', 'slot_order' => 1],
            ['position_slot' => 'CB', 'slot_order' => 1],
            ['position_slot' => 'CB', 'slot_order' => 2],
            ['position_slot' => 'RB', 'slot_order' => 1],
            ['position_slot' => 'CM', 'slot_order' => 1],
            ['position_slot' => 'CM', 'slot_order' => 2],
            ['position_slot' => 'CAM', 'slot_order' => 1],
            ['position_slot' => 'LW', 'slot_order' => 1],
            ['position_slot' => 'RW', 'slot_order' => 1],
            ['position_slot' => 'ST', 'slot_order' => 1],
        ];
        $selected = [];
        $used = [];

        foreach ($slots as $slot) {
            $picked = $this->pickBestForSlot($players, (string)$slot['position_slot'], $used);
            if (!$picked) {
                break;
            }
            $selected[] = [
                'player_id' => (int)$picked['id'],
                'position_slot' => (string)$slot['position_slot'],
                'slot_order' => (int)$slot['slot_order'],
            ];
            $used[(int)$picked['id']] = true;
        }

        if (count($selected) < 11) {
            foreach ($players as $p) {
                $pid = (int)$p['id'];
                if (isset($used[$pid])) continue;
                $fallback = $slots[count($selected)] ?? ['position_slot' => 'CM', 'slot_order' => 1];
                $selected[] = [
                    'player_id' => $pid,
                    'position_slot' => (string)$fallback['position_slot'],
                    'slot_order' => (int)$fallback['slot_order'],
                ];
                $used[$pid] = true;
                if (count($selected) >= 11) break;
            }
        }

        return $selected;
    }

    private function pickBestForSlot(array $players, string $slot, array $used): ?array {
        $preferred = match ($slot) {
            'GK' => ['GK'],
            'LB' => ['LB', 'LWB', 'CB'],
            'RB' => ['RB', 'RWB', 'CB'],
            'CB' => ['CB', 'LB', 'RB', 'CDM'],
            'CM' => ['CM', 'CDM', 'CAM'],
            'CAM' => ['CAM', 'CM', 'CF'],
            'LW' => ['LW', 'RW', 'ST'],
            'RW' => ['RW', 'LW', 'ST'],
            'ST' => ['ST', 'CF', 'RW', 'LW'],
            default => ['CM', 'CDM', 'CAM'],
        };

        $best = null;
        $bestScore = -INF;
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            if (isset($used[$pid])) continue;

            $position = (string)($p['position'] ?? '');
            $posRank = array_search($position, $preferred, true);
            $posBonus = $posRank === false ? -8.0 : (3.0 - min(3.0, (float)$posRank));

            $fitness = (int)($p['fitness'] ?? 100);
            $fatigue = (int)($p['fatigue'] ?? max(0, 100 - $fitness));
            $heavyMinutesPenalty = ((int)($p['last_minutes_played'] ?? 0) >= 85) ? 3.5 : 0.0;
            $recentPlayedPenalty = 0.0;
            if (!empty($p['last_played_at'])) {
                $daysSince = (int)floor((time() - strtotime((string)$p['last_played_at'])) / 86400);
                if ($daysSince <= 2) {
                    $recentPlayedPenalty = 1.5;
                }
            }

            $score = ((float)($p['overall'] ?? 50) * 1.0)
                + ($fitness * 0.15)
                - ($fatigue * 0.12)
                + $posBonus
                - $heavyMinutesPenalty
                - $recentPlayedPenalty;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $p;
            }
        }

        return $best;
    }

    private function syncClubVacancyState(int $clubId): bool {
        $club = $this->db->fetchOne(
            "SELECT id, owner_user_id, manager_user_id FROM clubs WHERE id = ?",
            [$clubId]
        );
        if (!$club) return false;

        $state = self::determineControlState($club);
        $existing = $this->db->fetchOne(
            "SELECT owner_vacancy_since, manager_vacancy_since FROM club_control_runtime_states WHERE club_id = ?",
            [$clubId]
        );
        $today = date('Y-m-d');
        $ownerSince = !empty($state['owner_vacant']) ? (($existing['owner_vacancy_since'] ?? null) ?: $today) : null;
        $managerSince = !empty($state['manager_vacant']) ? (($existing['manager_vacancy_since'] ?? null) ?: $today) : null;

        $this->db->execute(
            "INSERT INTO club_control_runtime_states
                (club_id, state_key, ai_owner_active, caretaker_active, owner_vacant, manager_vacant, owner_vacancy_since, manager_vacancy_since, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                state_key = VALUES(state_key),
                ai_owner_active = VALUES(ai_owner_active),
                caretaker_active = VALUES(caretaker_active),
                owner_vacant = VALUES(owner_vacant),
                manager_vacant = VALUES(manager_vacant),
                owner_vacancy_since = VALUES(owner_vacancy_since),
                manager_vacancy_since = VALUES(manager_vacancy_since),
                updated_at = NOW()",
            [
                $clubId,
                (string)$state['key'],
                !empty($state['is_ai_owner']) ? 1 : 0,
                !empty($state['is_caretaker']) ? 1 : 0,
                !empty($state['owner_vacant']) ? 1 : 0,
                !empty($state['manager_vacant']) ? 1 : 0,
                $ownerSince,
                $managerSince
            ]
        );
        return true;
    }

    private function ensureControlRuntimeTable(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_control_runtime_states (
                club_id INT PRIMARY KEY,
                state_key VARCHAR(64) NOT NULL,
                ai_owner_active BOOLEAN DEFAULT 0,
                caretaker_active BOOLEAN DEFAULT 0,
                owner_vacant BOOLEAN DEFAULT 0,
                manager_vacant BOOLEAN DEFAULT 0,
                owner_vacancy_since DATE NULL,
                manager_vacancy_since DATE NULL,
                updated_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                INDEX idx_runtime_vacancy (owner_vacant, manager_vacant, caretaker_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
