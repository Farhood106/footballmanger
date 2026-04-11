<?php
// app/Models/TacticModel.php

class TacticModel extends BaseModel {
    protected string $table = 'tactics';

    private const DEFAULT_PHASE = 'MATCH_1';

    public function getActiveByClub(int $clubId): ?array {
        $tactic = $this->db->fetchOne(
            "SELECT * FROM tactics WHERE club_id = ?",
            [$clubId]
        );

        if (!$tactic) {
            return null;
        }

        $rows = $this->db->fetchAll(
            "SELECT tl.*, p.first_name, p.last_name, p.position, p.overall
             FROM tactic_lineups tl
             JOIN players p ON tl.player_id = p.id
             WHERE tl.club_id = ? AND tl.is_active = 1
             ORDER BY tl.phase_key, tl.position_slot",
            [$clubId]
        );

        $lineupsByPhase = [];
        foreach ($rows as $row) {
            $phase = $row['phase_key'];
            $lineupsByPhase[$phase][] = [
                'player_id' => (int)$row['player_id'],
                'position_slot' => $row['position_slot'],
                'position' => $row['position'],
                'overall' => (int)$row['overall'],
                'full_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            ];
        }

        $tactic['lineups'] = $lineupsByPhase;
        $tactic['lineup'] = $lineupsByPhase[self::DEFAULT_PHASE] ?? [];

        return $tactic;
    }

    public function saveTacticalSetup(int $clubId, array $setup): int {
        $existing = $this->db->fetchOne("SELECT id FROM tactics WHERE club_id = ?", [$clubId]);

        if ($existing) {
            $this->update((int)$existing['id'], $setup);
            return (int)$existing['id'];
        }

        return $this->create(array_merge(['club_id' => $clubId], $setup));
    }

    public function saveLineup(int $clubId, string $phaseKey, array $lineup): void {
        $this->db->query(
            "UPDATE tactic_lineups SET is_active = 0 WHERE club_id = ? AND phase_key = ?",
            [$clubId, $phaseKey]
        );

        foreach ($lineup as $slot => $playerId) {
            $this->db->insert('tactic_lineups', [
                'club_id' => $clubId,
                'phase_key' => $phaseKey,
                'player_id' => (int)$playerId,
                'position_slot' => (string)$slot,
                'is_active' => 1,
            ]);
        }
    }

    public function getLineupForPhase(int $clubId, string $phaseKey): array {
        return $this->db->fetchAll(
            "SELECT tl.*, p.first_name, p.last_name, p.position, p.overall
             FROM tactic_lineups tl
             JOIN players p ON p.id = tl.player_id
             WHERE tl.club_id = ? AND tl.phase_key = ? AND tl.is_active = 1
             ORDER BY tl.position_slot",
            [$clubId, $phaseKey]
        );
    }

    public function getValidFormations(): array {
        return [
            '4-4-2' => 'Classic 4-4-2',
            '4-3-3' => 'Attacking 4-3-3',
            '4-2-3-1' => 'Balanced 4-2-3-1',
            '3-5-2' => 'Wing Play 3-5-2',
            '5-3-2' => 'Defensive 5-3-2',
            '4-1-4-1' => 'Counter 4-1-4-1',
        ];
    }
}
