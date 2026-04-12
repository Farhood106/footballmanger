<?php
// app/Services/AdminMatchOperationsService.php

class AdminMatchOperationsService {
    private Database $db;
    private MatchEngine $engine;
    private DailyCycleOrchestrator $orchestrator;

    public function __construct(?Database $db = null, ?MatchEngine $engine = null, ?DailyCycleOrchestrator $orchestrator = null) {
        $this->db = $db ?? Database::getInstance();
        $this->engine = $engine ?? new MatchEngine();
        $this->orchestrator = $orchestrator ?? new DailyCycleOrchestrator();
    }

    public function getMatches(array $filters): array {
        $sql = "SELECT m.id, m.season_id, s.name AS season_name, c.name AS competition_name,
                       m.scheduled_at, hc.name AS home_club_name, ac.name AS away_club_name,
                       m.status, m.home_score, m.away_score, m.played_at
                FROM matches m
                LEFT JOIN seasons s ON s.id = m.season_id
                LEFT JOIN competitions c ON c.id = s.competition_id
                JOIN clubs hc ON hc.id = m.home_club_id
                JOIN clubs ac ON ac.id = m.away_club_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND m.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['season_id'])) {
            $sql .= " AND m.season_id = ?";
            $params[] = (int)$filters['season_id'];
        }
        if (!empty($filters['competition_id'])) {
            $sql .= " AND s.competition_id = ?";
            $params[] = (int)$filters['competition_id'];
        }
        if (!empty($filters['club_id'])) {
            $sql .= " AND (m.home_club_id = ? OR m.away_club_id = ?)";
            $params[] = (int)$filters['club_id'];
            $params[] = (int)$filters['club_id'];
        }

        $sql .= " ORDER BY m.scheduled_at DESC LIMIT 300";
        return $this->db->fetchAll($sql, $params);
    }

    public function repairLiveToScheduled(int $matchId, int $adminId): array {
        $match = $this->db->fetchOne("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) return ['ok' => false, 'error' => 'Match not found.'];

        if (!self::canRepairLiveMatch($match)) {
            return ['ok' => false, 'error' => 'Match is not eligible for safe LIVE->SCHEDULED repair.'];
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE matches SET status = 'SCHEDULED', played_at = NULL WHERE id = ?", [$matchId]);
            $this->db->execute("DELETE FROM match_lineups WHERE match_id = ?", [$matchId]);
            $this->logOperation($adminId, 'REPAIR_LIVE_TO_SCHEDULED', 'match', $matchId, ['previous_status' => $match['status']]);
            $this->db->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function rerunMatch(int $matchId, int $adminId, bool $override = false): array {
        $match = $this->db->fetchOne("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) return ['ok' => false, 'error' => 'Match not found.'];

        if (!self::canRerunMatch($match, $override)) {
            return ['ok' => false, 'error' => 'Unsafe rerun blocked by guard policy.'];
        }

        $this->db->beginTransaction();
        try {
            if ($override && $match['status'] === 'FINISHED') {
                $this->db->execute("DELETE FROM match_events WHERE match_id = ?", [$matchId]);
                $this->db->execute("DELETE FROM player_match_ratings WHERE match_id = ?", [$matchId]);
                $this->db->execute("DELETE FROM match_lineups WHERE match_id = ?", [$matchId]);
                $this->db->execute("UPDATE matches SET status = 'SCHEDULED', home_score = NULL, away_score = NULL, played_at = NULL WHERE id = ?", [$matchId]);
            }
            if ($match['status'] === 'LIVE') {
                $this->db->execute("UPDATE matches SET status = 'SCHEDULED', played_at = NULL WHERE id = ?", [$matchId]);
            }
            $this->logOperation($adminId, 'RERUN_MATCH', 'match', $matchId, ['override' => $override]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $result = $this->engine->simulate($matchId);
            return ['ok' => true, 'result' => $result];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function resetLineupLock(int $matchId, int $adminId): array {
        $match = $this->db->fetchOne("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) return ['ok' => false, 'error' => 'Match not found.'];
        if ($match['status'] === 'FINISHED') {
            return ['ok' => false, 'error' => 'Cannot reset lineup lock for finished match.'];
        }

        $this->db->execute("DELETE FROM match_lineups WHERE match_id = ?", [$matchId]);
        $this->logOperation($adminId, 'RESET_LINEUP_LOCK', 'match', $matchId, []);
        return ['ok' => true];
    }

    public function getCycleStates(string $cycleDate): array {
        return $this->db->fetchAll(
            "SELECT s.*, c.name AS club_name
             FROM club_daily_cycle_states s
             JOIN clubs c ON c.id = s.club_id
             WHERE s.cycle_date = ?
             ORDER BY c.name ASC",
            [$cycleDate]
        );
    }

    public function syncCycleState(int $clubId, string $cycleDate, int $adminId): array {
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM (
                SELECT id FROM matches WHERE DATE(scheduled_at) = ? AND home_club_id = ?
                UNION ALL
                SELECT id FROM matches WHERE DATE(scheduled_at) = ? AND away_club_id = ?
             ) x",
            [$cycleDate, $clubId, $cycleDate, $clubId]
        );

        $matchesToday = (int)($count['c'] ?? 0);
        if ($matchesToday === 0) {
            return ['ok' => false, 'error' => 'No matches for this club/date.'];
        }

        $phase = $this->orchestrator->detectPhase(new DateTimeImmutable());
        $phaseKey = $phase['key'] ?? 'LINEUP_1_SETUP';
        $profile = DailyCycleOrchestrator::profileKey($matchesToday);

        $exists = $this->db->fetchOne(
            "SELECT id FROM club_daily_cycle_states WHERE cycle_date = ? AND club_id = ?",
            [$cycleDate, $clubId]
        );

        if ($exists) {
            $this->db->execute(
                "UPDATE club_daily_cycle_states
                 SET matches_today = ?, profile_key = ?, current_phase_key = ?, updated_at = NOW()
                 WHERE id = ?",
                [$matchesToday, $profile, $phaseKey, (int)$exists['id']]
            );
        } else {
            $this->db->insert('club_daily_cycle_states', [
                'cycle_date' => $cycleDate,
                'club_id' => $clubId,
                'matches_today' => $matchesToday,
                'profile_key' => $profile,
                'current_phase_key' => $phaseKey,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->logOperation($adminId, 'SYNC_CYCLE_STATE', 'club_daily_cycle_state', $clubId, ['cycle_date' => $cycleDate]);
        return ['ok' => true];
    }

    public static function canRepairLiveMatch(array $match): bool {
        return ($match['status'] ?? '') === 'LIVE'
            && empty($match['played_at'])
            && $match['home_score'] === null
            && $match['away_score'] === null;
    }

    public static function canRerunMatch(array $match, bool $override): bool {
        $status = $match['status'] ?? '';

        if ($status === 'SCHEDULED' || $status === 'LIVE') {
            return true;
        }

        if ($status === 'FINISHED') {
            return $override;
        }

        return false;
    }

    private function logOperation(int $adminId, string $action, string $entityType, int $entityId, array $payload): void {
        $this->db->insert('admin_operation_logs', [
            'admin_user_id' => $adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
