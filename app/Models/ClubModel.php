<?php
// app/Models/ClubModel.php

class ClubModel extends BaseModel {
    protected string $table = 'clubs';

    public function getWithDetails(int $clubId): ?array {
        return $this->db->fetchOne(
            "SELECT c.*,
                    u1.username AS owner_name,
                    u2.username AS manager_name,
                    d.name AS division_name,
                    l.name AS league_name
             FROM clubs c
             LEFT JOIN users u1 ON c.owner_id = u1.id
             LEFT JOIN users u2 ON c.manager_id = u2.id
             LEFT JOIN divisions d ON c.division_id = d.id
             LEFT JOIN leagues l ON d.league_id = l.id
             WHERE c.id = ?",
            [$clubId]
        );
    }

    public function getSquad(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT p.*,
                    GROUP_CONCAT(a.code) AS abilities
             FROM players p
             LEFT JOIN player_abilities pa ON p.id = pa.player_id AND pa.is_active = 1
             LEFT JOIN abilities a ON pa.ability_id = a.id
             WHERE p.club_id = ? AND p.is_active = 1
             GROUP BY p.id
             ORDER BY p.position, p.overall_rating DESC",
            [$clubId]
        );
    }

    public function getStartingXI(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT p.*, tl.position_slot
             FROM tactic_lineups tl
             JOIN players p ON tl.player_id = p.id
             JOIN tactics t ON tl.tactic_id = t.id
             WHERE t.club_id = ? AND t.is_active = 1
             ORDER BY tl.position_slot",
            [$clubId]
        );
    }

    public function getFinances(int $clubId): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM club_finances WHERE club_id = ? ORDER BY updated_at DESC LIMIT 1",
            [$clubId]
        );
    }

    public function getStanding(int $clubId, int $seasonId): ?array {
        return $this->db->fetchOne(
            "SELECT s.*, 
                    (s.goals_for - s.goals_against) AS goal_diff
             FROM standings s
             WHERE s.club_id = ? AND s.season_id = ?",
            [$clubId, $seasonId]
        );
    }

    public function getUnowned(): array {
        return $this->findAll('owner_id IS NULL AND is_active = 1');
    }

    public function getUnmanaged(): array {
        return $this->findAll('manager_id IS NULL AND is_active = 1');
    }

    public function assignManager(int $clubId, int $userId): bool {
        return $this->update($clubId, ['manager_id' => $userId]);
    }

    public function removeManager(int $clubId): bool {
        return $this->update($clubId, ['manager_id' => null]);
    }

    public function updateBudget(int $clubId, float $amount): bool {
        return $this->db->query(
            "UPDATE clubs SET budget = budget + ? WHERE id = ?",
            [$amount, $clubId]
        );
    }
}
