<?php
// app/Services/AIClubManagementService.php

class AIClubManagementService {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    public static function determineControlState(array $club): array {
        $ownerId = (int)($club['owner_user_id'] ?? 0);
        $managerId = (int)($club['manager_user_id'] ?? 0);

        $hasOwner = $ownerId > 0;
        $hasManager = $managerId > 0;

        if ($hasOwner && $hasManager && $ownerId === $managerId) {
            return ['key' => 'OWNER_SELF_MANAGED', 'is_ai_owner' => false, 'is_ai_manager' => false];
        }
        if ($hasOwner && $hasManager) {
            return ['key' => 'HUMAN_OWNER_HUMAN_MANAGER', 'is_ai_owner' => false, 'is_ai_manager' => false];
        }
        if ($hasOwner && !$hasManager) {
            return ['key' => 'HUMAN_OWNER_AI_MANAGER', 'is_ai_owner' => false, 'is_ai_manager' => true];
        }
        if (!$hasOwner && $hasManager) {
            return ['key' => 'AI_OWNER_HUMAN_MANAGER', 'is_ai_owner' => true, 'is_ai_manager' => false];
        }

        return ['key' => 'AI_OWNER_AI_MANAGER', 'is_ai_owner' => true, 'is_ai_manager' => true];
    }

    public function getClubControlState(int $clubId): array {
        $club = $this->db->fetchOne(
            "SELECT id, name, owner_user_id, manager_user_id FROM clubs WHERE id = ?",
            [$clubId]
        ) ?: ['id' => $clubId, 'name' => 'unknown', 'owner_user_id' => null, 'manager_user_id' => null];

        $state = self::determineControlState($club);
        return array_merge($club, $state);
    }

    public function listClubControlStates(): array {
        $rows = $this->db->fetchAll(
            "SELECT c.id, c.name, c.owner_user_id, c.manager_user_id,
                    o.username AS owner_name, m.username AS manager_name
             FROM clubs c
             LEFT JOIN users o ON o.id = c.owner_user_id
             LEFT JOIN users m ON m.id = c.manager_user_id
             ORDER BY c.name ASC"
        );

        return array_map(function (array $row): array {
            return array_merge($row, self::determineControlState($row));
        }, $rows);
    }

    public function applyDailyPreparation(int $clubId, string $cycleDate): array {
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
            return ['ok' => true, 'mode' => 'recovery'];
        }

        $this->db->execute(
            "UPDATE players
             SET fatigue = GREATEST(0, fatigue - 3),
                 morale = LEAST(10, morale + 0.02)
             WHERE club_id = ? AND is_retired = 0",
            [$clubId]
        );

        return ['ok' => true, 'mode' => 'balanced'];
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
            "SELECT tl.player_id, tl.position_slot, p.position AS actual_position
             FROM tactic_lineups tl
             JOIN players p ON p.id = tl.player_id
             WHERE tl.club_id = ? AND tl.phase_key IN (?, 'MATCH_1') AND tl.is_active = 1
             ORDER BY CASE WHEN tl.phase_key = ? THEN 0 ELSE 1 END, tl.position_slot",
            [$clubId, $lineupPhase, $lineupPhase]
        );
    }

    private function buildAiLineup(int $clubId): array {
        $players = $this->db->fetchAll(
            "SELECT id, position, overall
             FROM players
             WHERE club_id = ? AND is_retired = 0 AND is_injured = 0
             ORDER BY overall DESC, id ASC",
            [$clubId]
        );

        $slots = ['GK','LB','CB','CB','RB','CM','CM','CAM','LW','RW','ST'];
        $selected = [];
        $used = [];

        foreach ($slots as $slot) {
            $picked = $this->pickBestForSlot($players, $slot, $used);
            if (!$picked) {
                break;
            }
            $selected[] = ['player_id' => (int)$picked['id'], 'position_slot' => $slot];
            $used[(int)$picked['id']] = true;
        }

        if (count($selected) < 11) {
            foreach ($players as $p) {
                $pid = (int)$p['id'];
                if (isset($used[$pid])) continue;
                $selected[] = ['player_id' => $pid, 'position_slot' => $slots[count($selected)] ?? 'CM'];
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

        foreach ($preferred as $pos) {
            foreach ($players as $p) {
                $pid = (int)$p['id'];
                if (isset($used[$pid])) continue;
                if (($p['position'] ?? '') === $pos) {
                    return $p;
                }
            }
        }

        foreach ($players as $p) {
            $pid = (int)$p['id'];
            if (!isset($used[$pid])) {
                return $p;
            }
        }

        return null;
    }
}
