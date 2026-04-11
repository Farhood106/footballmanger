<?php
// app/Services/DailyCycleOrchestrator.php

class DailyCycleOrchestrator {
    private Database $db;
    private MatchEngine $engine;
    private array $phases;

    public function __construct(?MatchEngine $engine = null, ?array $config = null, ?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->engine = $engine ?? new MatchEngine();
        $allConfig = $config ?? require __DIR__ . '/../../config/config.php';
        $this->phases = $allConfig['game']['daily_cycle']['phases'];
    }

    public function run(?\DateTimeImmutable $now = null): array {
        $at = $now ?? new \DateTimeImmutable('now');
        $currentPhase = $this->detectPhase($at);

        $scheduled = $this->db->fetchAll(
            "SELECT m.* FROM matches m
             WHERE m.status = 'SCHEDULED'
               AND m.scheduled_at <= ?
               AND DATE(m.scheduled_at) >= DATE_SUB(DATE(?), INTERVAL 1 DAY)
             ORDER BY m.scheduled_at ASC",
            [$at->format('Y-m-d H:i:s'), $at->format('Y-m-d H:i:s')]
        );

        $result = [
            'executed_at' => $at->format('Y-m-d H:i:s'),
            'phase' => $currentPhase['key'] ?? 'OFF_HOURS',
            'simulated' => [],
            'failed' => [],
            'skipped' => [],
            'locks' => [],
        ];

        foreach ($scheduled as $match) {
            $expectedPhase = self::phaseForMatchTime((string)$match['scheduled_at']);
            $matchId = (int)$match['id'];

            if (($currentPhase['key'] ?? null) !== $expectedPhase) {
                $result['skipped'][] = [
                    'match_id' => $matchId,
                    'reason' => 'phase_mismatch',
                    'expected_phase' => $expectedPhase,
                ];
                continue;
            }

            try {
                $this->lockLineupsForMatch($matchId);
                $result['locks'][] = $matchId;
                $result['simulated'][] = $this->engine->simulate($matchId);
            } catch (\Throwable $e) {
                $result['failed'][] = ['match_id' => $matchId, 'error' => $e->getMessage()];
            }
        }

        $this->snapshot($at, $result);

        return $result;
    }

    public function detectPhase(\DateTimeImmutable $now): ?array {
        $time = $now->format('H:i');

        foreach ($this->phases['two_matches'] as $phase) {
            if (self::isTimeInRange($time, $phase['start'], $phase['end'])) {
                return $phase;
            }
        }

        return null;
    }

    public static function phaseForMatchTime(string $scheduledAt): string {
        $hour = (int)date('H', strtotime($scheduledAt));
        return $hour < 15 ? 'MATCH_1_LIVE' : 'MATCH_2_LIVE';
    }

    public static function isTimeInRange(string $time, string $start, string $end): bool {
        return $time >= $start && $time <= $end;
    }

    private function lockLineupsForMatch(int $matchId): void {
        $match = $this->db->fetchOne(
            "SELECT id, home_club_id, away_club_id, scheduled_at FROM matches WHERE id = ?",
            [$matchId]
        );
        if (!$match) {
            return;
        }

        $lineupPhase = self::phaseForMatchTime((string)$match['scheduled_at']) === 'MATCH_1_LIVE' ? 'MATCH_1' : 'MATCH_2';

        foreach ([(int)$match['home_club_id'], (int)$match['away_club_id']] as $clubId) {
            $rows = $this->db->fetchAll(
                "SELECT player_id, position_slot FROM tactic_lineups
                 WHERE club_id = ? AND phase_key IN (?, 'MATCH_1') AND is_active = 1
                 ORDER BY CASE WHEN phase_key = ? THEN 0 ELSE 1 END, position_slot",
                [$clubId, $lineupPhase, $lineupPhase]
            );

            $seen = [];
            foreach ($rows as $row) {
                $playerId = (int)$row['player_id'];
                if (isset($seen[$playerId])) {
                    continue;
                }
                $seen[$playerId] = true;

                $exists = $this->db->fetchOne(
                    "SELECT id FROM match_lineups WHERE match_id = ? AND player_id = ?",
                    [$matchId, $playerId]
                );

                if ($exists) {
                    continue;
                }

                $this->db->insert('match_lineups', [
                    'match_id' => $matchId,
                    'club_id' => $clubId,
                    'player_id' => $playerId,
                    'position' => $row['position_slot'],
                    'is_starter' => 1,
                    'shirt_number' => 0,
                ]);
            }
        }
    }

    private function snapshot(\DateTimeImmutable $at, array $result): void {
        $this->db->insert('daily_cycle_snapshots', [
            'cycle_date' => $at->format('Y-m-d'),
            'phase_key' => $result['phase'],
            'executed_at' => $at->format('Y-m-d H:i:s'),
            'matches_simulated' => count($result['simulated']),
            'payload' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
