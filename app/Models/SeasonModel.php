<?php
// app/Models/SeasonModel.php

class SeasonModel extends BaseModel {
    protected string $table = 'seasons';

    public function getActive(): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM seasons WHERE status = 'ACTIVE' LIMIT 1"
        );
    }

    public function startNew(int $competitionId, string $name, string $startDate, string $endDate): int {
        // پایان دادن به فصل‌های فعال قبلی
        $this->db->query("UPDATE seasons SET status = 'FINISHED' WHERE status = 'ACTIVE'");

        return $this->create([
            'competition_id' => $competitionId,
            'name'       => $name,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'status'     => 'ACTIVE',
            'current_week' => 0
        ]);
    }

    public function initStandings(int $seasonId, array $clubIds): void {
        foreach ($clubIds as $clubId) {
            $exists = $this->db->fetchOne(
                "SELECT id FROM standings WHERE season_id = ? AND club_id = ?",
                [$seasonId, $clubId]
            );
            if (!$exists) {
                $this->db->insert('standings', [
                    'season_id'      => $seasonId,
                    'club_id'        => $clubId,
                    'played'         => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                    'goals_for'      => 0, 'goals_against' => 0, 'goal_diff' => 0, 'points' => 0
                ]);
            }
        }
    }
}
