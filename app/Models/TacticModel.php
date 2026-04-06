<?php
// app/Models/TacticModel.php

class TacticModel extends BaseModel {
    protected string $table = 'tactics';

    public function getActiveByClub(int $clubId): ?array {
        $tactic = $this->db->fetchOne(
            "SELECT * FROM tactics WHERE club_id = ? AND is_active = 1",
            [$clubId]
        );

        if (!$tactic) return null;

        $tactic['lineup'] = $this->db->fetchAll(
            "SELECT tl.*, p.name, p.position, p.overall_rating
             FROM tactic_lineups tl
             JOIN players p ON tl.player_id = p.id
             WHERE tl.tactic_id = ?
             ORDER BY tl.position_slot",
            [$tactic['id']]
        );

        return $tactic;
    }

    public function saveLineup(int $tacticId, array $lineup): void {
        // پاک کردن ترکیب قبلی
        $this->db->query(
            "DELETE FROM tactic_lineups WHERE tactic_id = ?",
            [$tacticId]
        );

        // ذخیره ترکیب جدید
        foreach ($lineup as $slot => $playerId) {
            $this->db->insert('tactic_lineups', [
                'tactic_id'     => $tacticId,
                'player_id'     => $playerId,
                'position_slot' => $slot
            ]);
        }
    }

    public function setActive(int $clubId, int $tacticId): void {
        $this->db->query(
            "UPDATE tactics SET is_active = 0 WHERE club_id = ?",
            [$clubId]
        );
        $this->update($tacticId, ['is_active' => 1]);
    }

    public function getValidFormations(): array {
        return [
            '4-4-2'  => 'Classic 4-4-2',
            '4-3-3'  => 'Attacking 4-3-3',
            '4-2-3-1'=> 'Balanced 4-2-3-1',
            '3-5-2'  => 'Wing Play 3-5-2',
            '5-3-2'  => 'Defensive 5-3-2',
            '4-1-4-1'=> 'Counter 4-1-4-1',
        ];
    }
}
