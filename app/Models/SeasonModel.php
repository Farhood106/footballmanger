<?php
// app/Models/SeasonModel.php

class SeasonModel extends BaseModel {
    protected string $table = 'seasons';

    public function getActive(): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM seasons WHERE is_active = 1 LIMIT 1"
        );
    }

    public function startNew(string $name, string $startDate, string $endDate): int {
        // غیرفعال کردن فصل قبلی
        $this->db->query("UPDATE seasons SET is_active = 0");

        return $this->create([
            'name'       => $name,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'is_active'  => 1
        ]);
    }

    public function initStandings(int $seasonId, int $competitionId, array $clubIds): void {
        foreach ($clubIds as $clubId) {
            $exists = $this->db->fetchOne(
                "SELECT id FROM standings WHERE season_id = ? AND club_id = ? AND competition_id = ?",
                [$seasonId, $clubId, $competitionId]
            );
            if (!$exists) {
                $this->db->insert('standings', [
                    'season_id'      => $seasonId,
                    'competition_id' => $competitionId,
                    'club_id'        => $clubId,
                    'played'         => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                    'goals_for'      => 0, 'goals_against' => 0, 'points' => 0
                ]);
            }
        }
    }
}
