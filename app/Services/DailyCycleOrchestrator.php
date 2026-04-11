<?php
// app/Services/DailyCycleOrchestrator.php

class DailyCycleOrchestrator {
    private Database $db;
    private MatchEngine $engine;
    private array $config;

    public function __construct(?MatchEngine $engine = null, ?array $config = null) {
        $this->db = Database::getInstance();
        $this->engine = $engine ?? new MatchEngine();
        $allConfig = $config ?? require __DIR__ . '/../../config/config.php';
        $this->config = $allConfig['game']['daily_cycle']['phases'];
    }

    public function run(?\DateTimeImmutable $now = null): array {
        $at = $now ?? new \DateTimeImmutable('now');
        $scheduled = $this->db->fetchAll(
            "SELECT m.* FROM matches m
             WHERE m.status = 'SCHEDULED' AND DATE(m.scheduled_at) = DATE(?)
             ORDER BY m.scheduled_at ASC",
            [$at->format('Y-m-d H:i:s')]
        );

        $hasSecondMatch = count($scheduled) > 1;
        $phase = $this->resolvePhase($at, $hasSecondMatch);

        $result = [
            'executed_at' => $at->format('Y-m-d H:i:s'),
            'phase' => $phase['key'] ?? 'OFF_HOURS',
            'simulated' => [],
            'failed' => [],
            'locks' => [],
        ];

        if (!$phase || empty($phase['is_locked'])) {
            return $result;
        }

        foreach ($scheduled as $match) {
            if (!$this->isMatchWindow($phase['key'], $match['scheduled_at'])) {
                continue;
            }

            try {
                $this->lockLineupsForMatch((int)$match['id']);
                $result['locks'][] = (int)$match['id'];
                $result['simulated'][] = $this->engine->simulate((int)$match['id']);
            } catch (\Throwable $e) {
                $result['failed'][] = ['match_id' => (int)$match['id'], 'error' => $e->getMessage()];
            }
        }

        $this->db->insert('daily_cycle_snapshots', [
            'cycle_date' => $at->format('Y-m-d'),
            'phase_key' => $result['phase'],
            'executed_at' => $at->format('Y-m-d H:i:s'),
            'matches_simulated' => count($result['simulated']),
            'payload' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ]);

        return $result;
    }

    private function resolvePhase(\DateTimeImmutable $now, bool $hasSecondMatch): ?array {
        $set = $hasSecondMatch ? $this->config['two_matches'] : $this->config['one_match'];
        $time = $now->format('H:i');

        foreach ($set as $phase) {
            if ($time >= $phase['start'] && $time <= $phase['end']) {
                return $phase;
            }
        }

        return null;
    }

    private function isMatchWindow(string $phaseKey, string $scheduledAt): bool {
        $hour = (int)date('H', strtotime($scheduledAt));
        if ($phaseKey === 'MATCH_1_LIVE') {
            return $hour < 15;
        }
        if ($phaseKey === 'MATCH_2_LIVE') {
            return $hour >= 15;
        }

        return false;
    }

    private function lockLineupsForMatch(int $matchId): void {
        $match = $this->db->fetchOne("SELECT home_club_id, away_club_id, scheduled_at FROM matches WHERE id = ?", [$matchId]);
        if (!$match) {
            return;
        }

        $phaseKey = ((int)date('H', strtotime($match['scheduled_at'])) < 15) ? 'MATCH_1' : 'MATCH_2';
        foreach ([(int)$match['home_club_id'], (int)$match['away_club_id']] as $clubId) {
            $rows = $this->db->fetchAll(
                "SELECT player_id, position_slot FROM tactic_lineups
                 WHERE club_id = ? AND phase_key = ? AND is_active = 1",
                [$clubId, $phaseKey]
            );

            foreach ($rows as $row) {
                $this->db->insert('match_lineups', [
                    'match_id' => $matchId,
                    'club_id' => $clubId,
                    'player_id' => (int)$row['player_id'],
                    'position' => $row['position_slot'],
                    'is_starter' => 1,
                    'shirt_number' => 0,
                ]);
            }
        }
    }
}
