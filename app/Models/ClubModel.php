<?php
// app/Models/ClubModel.php

class ClubModel extends BaseModel {
    protected string $table = 'clubs';

    public function getWithDetails(int $clubId): ?array {
        return $this->db->fetchOne(
            "SELECT c.*,
                    owner.username AS owner_name,
                    manager.username AS manager_name
             FROM clubs c
             LEFT JOIN users owner ON c.owner_user_id = owner.id
             LEFT JOIN users manager ON c.manager_user_id = manager.id
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
             WHERE p.club_id = ? AND p.is_retired = 0
             GROUP BY p.id
             ORDER BY p.position, p.overall DESC",
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
            "SELECT * FROM finances WHERE club_id = ? ORDER BY date DESC LIMIT 1",
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
        return $this->db->fetchAll("SELECT * FROM clubs WHERE owner_user_id IS NULL ORDER BY reputation DESC, id ASC");
    }

    public function getUnmanaged(): array {
        return $this->db->fetchAll("SELECT * FROM clubs WHERE manager_user_id IS NULL ORDER BY reputation DESC, id ASC");
    }

    public function assignManager(int $clubId, int $userId): bool {
        return $this->db->execute(
            "UPDATE clubs SET manager_user_id = ?, user_id = ? WHERE id = ? AND manager_user_id IS NULL",
            [$userId, $userId, $clubId]
        ) > 0;
    }

    public function removeManager(int $clubId): bool {
        return $this->db->execute(
            "UPDATE clubs SET manager_user_id = NULL, user_id = NULL WHERE id = ?",
            [$clubId]
        ) > 0;
    }

    public function assignOwner(int $clubId, int $userId): bool {
        return $this->db->execute(
            "UPDATE clubs SET owner_user_id = ? WHERE id = ? AND owner_user_id IS NULL",
            [$userId, $clubId]
        ) > 0;
    }

    public function updateBudget(int $clubId, float $amount): bool {
        return $this->db->execute(
            "UPDATE clubs SET balance = balance + ? WHERE id = ?",
            [$amount, $clubId]
        ) > 0;
    }
}
