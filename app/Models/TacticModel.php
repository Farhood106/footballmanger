<?php
// app/Models/TacticModel.php

class TacticModel extends BaseModel {
    protected string $table = 'tactics';

    private const DEFAULT_PHASE = 'MATCH_1';
    private const DEFAULT_FORMATION = '4-3-3';
    private const FORMATIONS = [
        '4-4-2' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'LM__1', 'position_slot' => 'LM', 'slot_order' => 1, 'label' => 'Left Midfielder', 'board_x' => 18, 'board_y' => 53],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Midfielder (L)', 'board_x' => 43, 'board_y' => 56],
            ['slot_key' => 'CM__2', 'position_slot' => 'CM', 'slot_order' => 2, 'label' => 'Midfielder (R)', 'board_x' => 57, 'board_y' => 56],
            ['slot_key' => 'RM__1', 'position_slot' => 'RM', 'slot_order' => 1, 'label' => 'Right Midfielder', 'board_x' => 82, 'board_y' => 53],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker (L)', 'board_x' => 43, 'board_y' => 30],
            ['slot_key' => 'ST__2', 'position_slot' => 'ST', 'slot_order' => 2, 'label' => 'Striker (R)', 'board_x' => 57, 'board_y' => 30],
        ],
        '4-3-3' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'Defensive Midfielder', 'board_x' => 50, 'board_y' => 62],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder (L)', 'board_x' => 38, 'board_y' => 50],
            ['slot_key' => 'CM__2', 'position_slot' => 'CM', 'slot_order' => 2, 'label' => 'Central Midfielder (R)', 'board_x' => 62, 'board_y' => 50],
            ['slot_key' => 'LW__1', 'position_slot' => 'LW', 'slot_order' => 1, 'label' => 'Left Wing', 'board_x' => 22, 'board_y' => 32],
            ['slot_key' => 'RW__1', 'position_slot' => 'RW', 'slot_order' => 1, 'label' => 'Right Wing', 'board_x' => 78, 'board_y' => 32],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker', 'board_x' => 50, 'board_y' => 24],
        ],
        '4-2-3-1' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'Defensive Midfielder (L)', 'board_x' => 42, 'board_y' => 61],
            ['slot_key' => 'CDM__2', 'position_slot' => 'CDM', 'slot_order' => 2, 'label' => 'Defensive Midfielder (R)', 'board_x' => 58, 'board_y' => 61],
            ['slot_key' => 'CAM__1', 'position_slot' => 'CAM', 'slot_order' => 1, 'label' => 'Attacking Midfielder', 'board_x' => 50, 'board_y' => 44],
            ['slot_key' => 'LM__1', 'position_slot' => 'LM', 'slot_order' => 1, 'label' => 'Left Midfielder', 'board_x' => 22, 'board_y' => 42],
            ['slot_key' => 'RM__1', 'position_slot' => 'RM', 'slot_order' => 1, 'label' => 'Right Midfielder', 'board_x' => 78, 'board_y' => 42],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker', 'board_x' => 50, 'board_y' => 24],
        ],
        '3-5-2' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 33, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (C)', 'board_x' => 50, 'board_y' => 76],
            ['slot_key' => 'CB__3', 'position_slot' => 'CB', 'slot_order' => 3, 'label' => 'Center Back (R)', 'board_x' => 67, 'board_y' => 76],
            ['slot_key' => 'LWB__1', 'position_slot' => 'LWB', 'slot_order' => 1, 'label' => 'Left Wing Back', 'board_x' => 18, 'board_y' => 57],
            ['slot_key' => 'RWB__1', 'position_slot' => 'RWB', 'slot_order' => 1, 'label' => 'Right Wing Back', 'board_x' => 82, 'board_y' => 57],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'Defensive Midfielder', 'board_x' => 50, 'board_y' => 60],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder', 'board_x' => 50, 'board_y' => 47],
            ['slot_key' => 'CAM__1', 'position_slot' => 'CAM', 'slot_order' => 1, 'label' => 'Attacking Midfielder', 'board_x' => 50, 'board_y' => 36],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker (L)', 'board_x' => 43, 'board_y' => 24],
            ['slot_key' => 'ST__2', 'position_slot' => 'ST', 'slot_order' => 2, 'label' => 'Striker (R)', 'board_x' => 57, 'board_y' => 24],
        ],
        '4-1-4-1' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'DM (CDM)', 'board_x' => 50, 'board_y' => 63],
            ['slot_key' => 'LM__1', 'position_slot' => 'LM', 'slot_order' => 1, 'label' => 'Left Midfielder', 'board_x' => 22, 'board_y' => 47],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder (L)', 'board_x' => 43, 'board_y' => 49],
            ['slot_key' => 'CM__2', 'position_slot' => 'CM', 'slot_order' => 2, 'label' => 'Central Midfielder (R)', 'board_x' => 57, 'board_y' => 49],
            ['slot_key' => 'RM__1', 'position_slot' => 'RM', 'slot_order' => 1, 'label' => 'Right Midfielder', 'board_x' => 78, 'board_y' => 47],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker', 'board_x' => 50, 'board_y' => 24],
        ],
        '4-1-2-1-2' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'DM (CDM)', 'board_x' => 50, 'board_y' => 64],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder (L)', 'board_x' => 40, 'board_y' => 50],
            ['slot_key' => 'CM__2', 'position_slot' => 'CM', 'slot_order' => 2, 'label' => 'Central Midfielder (R)', 'board_x' => 60, 'board_y' => 50],
            ['slot_key' => 'CAM__1', 'position_slot' => 'CAM', 'slot_order' => 1, 'label' => 'AM (CAM)', 'board_x' => 50, 'board_y' => 38],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker (L)', 'board_x' => 43, 'board_y' => 24],
            ['slot_key' => 'ST__2', 'position_slot' => 'ST', 'slot_order' => 2, 'label' => 'Striker (R)', 'board_x' => 57, 'board_y' => 24],
        ],
        '4-5-1' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'LM__1', 'position_slot' => 'LM', 'slot_order' => 1, 'label' => 'Left Midfielder', 'board_x' => 20, 'board_y' => 50],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'DM (CDM)', 'board_x' => 38, 'board_y' => 56],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder', 'board_x' => 50, 'board_y' => 49],
            ['slot_key' => 'CAM__1', 'position_slot' => 'CAM', 'slot_order' => 1, 'label' => 'AM (CAM)', 'board_x' => 62, 'board_y' => 42],
            ['slot_key' => 'RM__1', 'position_slot' => 'RM', 'slot_order' => 1, 'label' => 'Right Midfielder', 'board_x' => 80, 'board_y' => 50],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker', 'board_x' => 50, 'board_y' => 24],
        ],
        '4-4-1-1' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LB__1', 'position_slot' => 'LB', 'slot_order' => 1, 'label' => 'Left Back', 'board_x' => 20, 'board_y' => 74],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 40, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (R)', 'board_x' => 60, 'board_y' => 76],
            ['slot_key' => 'RB__1', 'position_slot' => 'RB', 'slot_order' => 1, 'label' => 'Right Back', 'board_x' => 80, 'board_y' => 74],
            ['slot_key' => 'LM__1', 'position_slot' => 'LM', 'slot_order' => 1, 'label' => 'Left Midfielder', 'board_x' => 20, 'board_y' => 52],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder (L)', 'board_x' => 42, 'board_y' => 56],
            ['slot_key' => 'CM__2', 'position_slot' => 'CM', 'slot_order' => 2, 'label' => 'Central Midfielder (R)', 'board_x' => 58, 'board_y' => 56],
            ['slot_key' => 'RM__1', 'position_slot' => 'RM', 'slot_order' => 1, 'label' => 'Right Midfielder', 'board_x' => 80, 'board_y' => 52],
            ['slot_key' => 'CAM__1', 'position_slot' => 'CAM', 'slot_order' => 1, 'label' => 'Second Striker / AM', 'board_x' => 50, 'board_y' => 37],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker', 'board_x' => 50, 'board_y' => 24],
        ],
        '5-3-2' => [
            ['slot_key' => 'GK__1', 'position_slot' => 'GK', 'slot_order' => 1, 'label' => 'Goalkeeper', 'board_x' => 50, 'board_y' => 90],
            ['slot_key' => 'LWB__1', 'position_slot' => 'LWB', 'slot_order' => 1, 'label' => 'Left Wing Back', 'board_x' => 14, 'board_y' => 69],
            ['slot_key' => 'CB__1', 'position_slot' => 'CB', 'slot_order' => 1, 'label' => 'Center Back (L)', 'board_x' => 33, 'board_y' => 76],
            ['slot_key' => 'CB__2', 'position_slot' => 'CB', 'slot_order' => 2, 'label' => 'Center Back (C)', 'board_x' => 50, 'board_y' => 78],
            ['slot_key' => 'CB__3', 'position_slot' => 'CB', 'slot_order' => 3, 'label' => 'Center Back (R)', 'board_x' => 67, 'board_y' => 76],
            ['slot_key' => 'RWB__1', 'position_slot' => 'RWB', 'slot_order' => 1, 'label' => 'Right Wing Back', 'board_x' => 86, 'board_y' => 69],
            ['slot_key' => 'CM__1', 'position_slot' => 'CM', 'slot_order' => 1, 'label' => 'Central Midfielder (L)', 'board_x' => 38, 'board_y' => 52],
            ['slot_key' => 'CDM__1', 'position_slot' => 'CDM', 'slot_order' => 1, 'label' => 'DM (CDM)', 'board_x' => 50, 'board_y' => 58],
            ['slot_key' => 'CM__2', 'position_slot' => 'CM', 'slot_order' => 2, 'label' => 'Central Midfielder (R)', 'board_x' => 62, 'board_y' => 52],
            ['slot_key' => 'ST__1', 'position_slot' => 'ST', 'slot_order' => 1, 'label' => 'Striker (L)', 'board_x' => 43, 'board_y' => 24],
            ['slot_key' => 'ST__2', 'position_slot' => 'ST', 'slot_order' => 2, 'label' => 'Striker (R)', 'board_x' => 57, 'board_y' => 24],
        ],
    ];

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
             ORDER BY tl.phase_key, tl.position_slot, tl.slot_order, tl.id",
            [$clubId]
        );

        $lineupsByPhase = [];
        foreach ($rows as $row) {
            $phase = $row['phase_key'];
            $slotOrder = (int)($row['slot_order'] ?? 1);
            $slotKey = ((string)$row['position_slot']) . '__' . $slotOrder;
            $lineupsByPhase[$phase][] = [
                'player_id' => (int)$row['player_id'],
                'position_slot' => $row['position_slot'],
                'slot_order' => $slotOrder,
                'slot_key' => $slotKey,
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

    public function saveSetupAndLineup(int $clubId, array $setup, string $phaseKey, array $lineupRows): void {
        $this->db->beginTransaction();
        try {
            $this->saveTacticalSetup($clubId, $setup);
            $this->saveLineup($clubId, $phaseKey, $lineupRows);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function saveLineup(int $clubId, string $phaseKey, array $lineup): void {
        $this->db->query(
            "UPDATE tactic_lineups SET is_active = 0 WHERE club_id = ? AND phase_key = ?",
            [$clubId, $phaseKey]
        );

        foreach ($lineup as $row) {
            $slot = (string)($row['position_slot'] ?? '');
            $slotOrder = max(1, (int)($row['slot_order'] ?? 1));
            $playerId = (int)($row['player_id'] ?? 0);
            if ($slot === '' || $playerId <= 0) {
                continue;
            }
            $this->db->insert('tactic_lineups', [
                'club_id' => $clubId,
                'phase_key' => $phaseKey,
                'player_id' => $playerId,
                'position_slot' => $slot,
                'slot_order' => $slotOrder,
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
             ORDER BY tl.position_slot, tl.slot_order, tl.id",
            [$clubId, $phaseKey]
        );
    }

    public function getValidFormations(): array {
        return [
            '4-4-2' => 'Classic 4-4-2',
            '4-3-3' => 'Attacking 4-3-3',
            '4-2-3-1' => 'Balanced 4-2-3-1',
            '3-5-2' => 'Wing Play 3-5-2',
            '4-1-4-1' => 'Holding 4-1-4-1',
            '4-1-2-1-2' => 'Narrow Diamond 4-1-2-1-2',
            '4-5-1' => 'Compact 4-5-1',
            '4-4-1-1' => 'Support Striker 4-4-1-1',
            '5-3-2' => 'Wingback 5-3-2',
        ];
    }

    public function getFormationSlots(string $formation): array {
        return self::FORMATIONS[$formation] ?? self::FORMATIONS[self::DEFAULT_FORMATION];
    }

    public function getDefaultFormation(): string {
        return self::DEFAULT_FORMATION;
    }

    public function buildLineupSelectionData(array $squad, array $formationSlots, array $selectedMap): array {
        $output = [];
        foreach ($formationSlots as $slot) {
            $slotKey = (string)$slot['slot_key'];
            $positionSlot = (string)$slot['position_slot'];
            $selectedPlayerId = (int)($selectedMap[$slotKey] ?? 0);
            $candidates = [];
            foreach ($squad as $player) {
                $playerId = (int)($player['id'] ?? 0);
                if ($playerId <= 0) {
                    continue;
                }
                $rating = $this->calculatePositionRating($player, $positionSlot);
                $candidates[] = [
                    'id' => $playerId,
                    'full_name' => trim((string)(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''))),
                    'overall' => (int)($player['overall'] ?? 0),
                    'position' => (string)($player['position'] ?? ''),
                    'position_rating' => $rating,
                    'out_of_position' => strtoupper((string)($player['position'] ?? '')) !== strtoupper($positionSlot),
                ];
            }

            usort($candidates, fn($a, $b) => ($b['position_rating'] <=> $a['position_rating']) ?: ($b['overall'] <=> $a['overall']) ?: ($a['id'] <=> $b['id']));

            $output[] = [
                'slot_key' => $slotKey,
                'slot_label' => (string)$slot['label'],
                'position_slot' => $positionSlot,
                'slot_order' => (int)$slot['slot_order'],
                'board_x' => (int)($slot['board_x'] ?? 50),
                'board_y' => (int)($slot['board_y'] ?? 50),
                'selected_player_id' => $selectedPlayerId,
                'candidates' => $candidates,
                'recommended' => array_slice($candidates, 0, 3),
                'selected_candidate' => $this->findSelectedCandidate($candidates, $selectedPlayerId),
            ];
        }

        return $output;
    }

    public function buildResponsibilityRankings(array $squad, array $lineupBoard): array {
        $lineupPlayers = $this->extractLineupPlayers($squad, $lineupBoard);

        $roles = ['captain', 'penalty_taker', 'freekick_taker', 'corner_taker'];
        $result = [];
        foreach ($roles as $role) {
            $ranked = [];
            foreach ($lineupPlayers as $player) {
                $pid = (int)($player['id'] ?? 0);
                if ($pid <= 0) continue;
                $score = $this->calculateResponsibilityScore($player, $role, true);
                $name = trim((string)(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')));
                $ranked[] = [
                    'id' => $pid,
                    'name' => $name !== '' ? $name : ('Player #' . $pid),
                    'position' => (string)($player['position'] ?? ''),
                    'overall' => (int)($player['overall'] ?? 0),
                    'score' => $score,
                ];
            }
            usort($ranked, fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['overall'] <=> $a['overall']) ?: ($a['id'] <=> $b['id']));
            $result[$role] = $ranked;
        }

        return $result;
    }

    public function normalizeResponsibilitiesForLineup(array $tactic, array $lineupBoard): array {
        $allowed = $this->extractLineupPlayerIds($lineupBoard);
        foreach (['captain', 'penalty_taker', 'freekick_taker', 'corner_taker'] as $field) {
            $pid = (int)($tactic[$field] ?? 0);
            if ($pid > 0 && !isset($allowed[$pid])) {
                $tactic[$field] = null;
            }
        }
        return $tactic;
    }

    public function extractLineupPlayerIds(array $lineupBoard): array {
        $lineupPlayerIds = [];
        foreach ($lineupBoard as $slot) {
            $pid = (int)($slot['selected_player_id'] ?? 0);
            if ($pid > 0) {
                $lineupPlayerIds[$pid] = true;
            }
        }
        return $lineupPlayerIds;
    }

    private function extractLineupPlayers(array $squad, array $lineupBoard): array {
        $byId = [];
        foreach ($squad as $player) {
            $pid = (int)($player['id'] ?? 0);
            if ($pid > 0) {
                $byId[$pid] = $player;
            }
        }

        $lineup = [];
        foreach ($this->extractLineupPlayerIds($lineupBoard) as $pid => $_) {
            if (isset($byId[$pid])) {
                $lineup[] = $byId[$pid];
            }
        }

        return $lineup;
    }

    public function calculatePositionRating(array $player, string $slot): int {
        $slot = strtoupper($slot);
        $weights = $this->positionWeights($slot);
        $overall = (int)($player['overall'] ?? 0);

        $base =
            ((int)($player['pace'] ?? 0) * $weights['pace']) +
            ((int)($player['shooting'] ?? 0) * $weights['shooting']) +
            ((int)($player['passing'] ?? 0) * $weights['passing']) +
            ((int)($player['dribbling'] ?? 0) * $weights['dribbling']) +
            ((int)($player['defending'] ?? 0) * $weights['defending']) +
            ((int)($player['physical'] ?? 0) * $weights['physical']) +
            ($overall * $weights['overall']);

        $preferred = strtoupper((string)($player['position'] ?? ''));
        if ($preferred === $slot) {
            $base += 4.0;
        } elseif ($this->isAdjacentRole($preferred, $slot)) {
            $base -= 2.0;
        } else {
            $base -= 8.0;
        }

        return max(1, min(99, (int)round($base)));
    }

    private function isAdjacentRole(string $preferred, string $slot): bool {
        $families = [
            'GK' => ['GK'],
            'LB' => ['LB', 'LWB', 'CB'],
            'RB' => ['RB', 'RWB', 'CB'],
            'CB' => ['CB', 'LB', 'RB', 'CDM'],
            'LWB' => ['LWB', 'LB', 'LW'],
            'RWB' => ['RWB', 'RB', 'RW'],
            'CDM' => ['CDM', 'CM', 'CB'],
            'CM' => ['CM', 'CDM', 'CAM'],
            'CAM' => ['CAM', 'CM', 'CF'],
            'LM' => ['LM', 'LW', 'CM', 'LWB'],
            'RM' => ['RM', 'RW', 'CM', 'RWB'],
            'LW' => ['LW', 'RW', 'CAM', 'LWB'],
            'RW' => ['RW', 'LW', 'CAM', 'RWB'],
            'ST' => ['ST', 'CF', 'CAM'],
            'CF' => ['CF', 'ST', 'CAM'],
        ];

        return in_array($slot, $families[$preferred] ?? [], true);
    }

    private function positionWeights(string $slot): array {
        $map = [
            'GK' => ['pace' => 0.02, 'shooting' => 0.00, 'passing' => 0.18, 'dribbling' => 0.00, 'defending' => 0.55, 'physical' => 0.15, 'overall' => 0.10],
            'LB' => ['pace' => 0.20, 'shooting' => 0.03, 'passing' => 0.17, 'dribbling' => 0.10, 'defending' => 0.35, 'physical' => 0.10, 'overall' => 0.05],
            'RB' => ['pace' => 0.20, 'shooting' => 0.03, 'passing' => 0.17, 'dribbling' => 0.10, 'defending' => 0.35, 'physical' => 0.10, 'overall' => 0.05],
            'CB' => ['pace' => 0.08, 'shooting' => 0.00, 'passing' => 0.10, 'dribbling' => 0.03, 'defending' => 0.54, 'physical' => 0.20, 'overall' => 0.05],
            'LWB' => ['pace' => 0.22, 'shooting' => 0.05, 'passing' => 0.18, 'dribbling' => 0.15, 'defending' => 0.27, 'physical' => 0.08, 'overall' => 0.05],
            'RWB' => ['pace' => 0.22, 'shooting' => 0.05, 'passing' => 0.18, 'dribbling' => 0.15, 'defending' => 0.27, 'physical' => 0.08, 'overall' => 0.05],
            'CDM' => ['pace' => 0.10, 'shooting' => 0.05, 'passing' => 0.25, 'dribbling' => 0.08, 'defending' => 0.37, 'physical' => 0.10, 'overall' => 0.05],
            'CM' => ['pace' => 0.10, 'shooting' => 0.10, 'passing' => 0.30, 'dribbling' => 0.15, 'defending' => 0.20, 'physical' => 0.10, 'overall' => 0.05],
            'CAM' => ['pace' => 0.10, 'shooting' => 0.18, 'passing' => 0.28, 'dribbling' => 0.24, 'defending' => 0.05, 'physical' => 0.10, 'overall' => 0.05],
            'LM' => ['pace' => 0.20, 'shooting' => 0.12, 'passing' => 0.22, 'dribbling' => 0.18, 'defending' => 0.12, 'physical' => 0.11, 'overall' => 0.05],
            'RM' => ['pace' => 0.20, 'shooting' => 0.12, 'passing' => 0.22, 'dribbling' => 0.18, 'defending' => 0.12, 'physical' => 0.11, 'overall' => 0.05],
            'LW' => ['pace' => 0.22, 'shooting' => 0.20, 'passing' => 0.15, 'dribbling' => 0.25, 'defending' => 0.03, 'physical' => 0.10, 'overall' => 0.05],
            'RW' => ['pace' => 0.22, 'shooting' => 0.20, 'passing' => 0.15, 'dribbling' => 0.25, 'defending' => 0.03, 'physical' => 0.10, 'overall' => 0.05],
            'ST' => ['pace' => 0.18, 'shooting' => 0.34, 'passing' => 0.12, 'dribbling' => 0.16, 'defending' => 0.00, 'physical' => 0.15, 'overall' => 0.05],
            'CF' => ['pace' => 0.16, 'shooting' => 0.30, 'passing' => 0.20, 'dribbling' => 0.18, 'defending' => 0.00, 'physical' => 0.11, 'overall' => 0.05],
        ];

        return $map[$slot] ?? $map['CM'];
    }

    private function findSelectedCandidate(array $candidates, int $selectedPlayerId): ?array {
        if ($selectedPlayerId <= 0) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ((int)$candidate['id'] === $selectedPlayerId) {
                return $candidate;
            }
        }

        return null;
    }

    private function calculateResponsibilityScore(array $player, string $role, bool $inLineup): float {
        $overall = (int)($player['overall'] ?? 0);
        $shooting = (int)($player['shooting'] ?? 0);
        $passing = (int)($player['passing'] ?? 0);
        $dribbling = (int)($player['dribbling'] ?? 0);
        $pace = (int)($player['pace'] ?? 0);
        $physical = (int)($player['physical'] ?? 0);
        $morale = (float)($player['morale'] ?? 6.5);
        $roleCode = strtoupper((string)($player['squad_role'] ?? 'ROTATION'));
        $position = strtoupper((string)($player['position'] ?? ''));

        $lineupBonus = $inLineup ? 5.0 : 0.0;
        $stabilityBonus = match ($roleCode) {
            'KEY_PLAYER' => 4.0,
            'REGULAR_STARTER' => 2.0,
            'ROTATION' => 1.0,
            default => 0.0,
        };

        return match ($role) {
            'captain' => ($overall * 0.45) + ($morale * 6.0) + ($physical * 0.18) + $lineupBonus + $stabilityBonus,
            'penalty_taker' => ($shooting * 0.60) + ($overall * 0.20) + ($morale * 2.5) + ($physical * 0.10) + $lineupBonus,
            'freekick_taker' => ($passing * 0.45) + ($shooting * 0.25) + ($dribbling * 0.15) + ($overall * 0.15) + $lineupBonus,
            'corner_taker' => ($passing * 0.45) + ($dribbling * 0.25) + ($pace * 0.10) + ($overall * 0.20)
                + (in_array($position, ['LM','RM','LW','RW','CAM'], true) ? 3.0 : 0.0)
                + $lineupBonus,
            default => (float)$overall,
        };
    }
}
