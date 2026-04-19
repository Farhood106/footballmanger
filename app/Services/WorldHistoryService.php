<?php
// app/Services/WorldHistoryService.php

class WorldHistoryService {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        if ($this->db->shouldRunRuntimeDdlFallback()) {
            $this->ensureHistoryTables();
        }
    }

    public function recordPlayerOfMatch(int $matchId, int $seasonId, int $competitionId, array $potm): void {
        $this->upsertPlayerAward(
            $seasonId,
            $competitionId,
            'PLAYER_OF_MATCH',
            (int)$potm['player_id'],
            (int)$potm['club_id'],
            $matchId,
            null,
            ['rating' => $potm['rating'] ?? null]
        );
    }

    public function upsertWeeklyAwardFromMatch(int $matchId, int $seasonId, int $competitionId, int $week, array $candidate): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM player_awards
             WHERE season_id = ? AND competition_id = ? AND award_type = 'PLAYER_OF_WEEK' AND week_number = ?
             LIMIT 1",
            [$seasonId, $competitionId, $week]
        );
        if ($existing && (float)($existing['score_value'] ?? 0) >= (float)($candidate['score'] ?? 0)) {
            return;
        }
        $this->upsertPlayerAward(
            $seasonId,
            $competitionId,
            'PLAYER_OF_WEEK',
            (int)$candidate['player_id'],
            (int)$candidate['club_id'],
            $matchId,
            $week,
            ['rating' => $candidate['score'] ?? null],
            (float)($candidate['score'] ?? 0)
        );
    }

    public function applySeasonAwards(int $seasonId, int $competitionId): array {
        $scopeClubIds = $this->db->fetchAll("SELECT club_id FROM club_seasons WHERE season_id = ?", [$seasonId]);
        $clubIds = array_map(fn($r) => (int)$r['club_id'], $scopeClubIds);
        if (empty($clubIds)) return ['ok' => true, 'awards' => 0];

        $in = implode(',', array_fill(0, count($clubIds), '?'));
        $params = array_merge([$seasonId], $clubIds);

        $topScorer = $this->db->fetchOne(
            "SELECT pss.player_id, pss.club_id, pss.goals AS score
             FROM player_season_stats pss
             WHERE pss.season_id = ? AND pss.club_id IN ($in)
             ORDER BY pss.goals DESC, pss.assists DESC, pss.appearances DESC, pss.player_id ASC
             LIMIT 1",
            $params
        );
        $topAssist = $this->db->fetchOne(
            "SELECT pss.player_id, pss.club_id, pss.assists AS score
             FROM player_season_stats pss
             WHERE pss.season_id = ? AND pss.club_id IN ($in)
             ORDER BY pss.assists DESC, pss.goals DESC, pss.appearances DESC, pss.player_id ASC
             LIMIT 1",
            $params
        );
        $bestPlayer = $this->db->fetchOne(
            "SELECT pss.player_id, pss.club_id,
                    ((pss.avg_rating * 10) + (pss.goals * 2) + pss.assists) AS score
             FROM player_season_stats pss
             WHERE pss.season_id = ? AND pss.club_id IN ($in) AND pss.appearances >= 5
             ORDER BY score DESC, pss.avg_rating DESC, pss.player_id ASC
             LIMIT 1",
            $params
        );
        $bestYoung = $this->db->fetchOne(
            "SELECT pss.player_id, pss.club_id,
                    ((pss.avg_rating * 10) + (pss.goals * 2) + pss.assists) AS score
             FROM player_season_stats pss
             JOIN players p ON p.id = pss.player_id
             WHERE pss.season_id = ? AND pss.club_id IN ($in)
               AND TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) <= 23
               AND pss.appearances >= 3
             ORDER BY score DESC, pss.avg_rating DESC, pss.player_id ASC
             LIMIT 1",
            $params
        );

        $count = 0;
        if ($topScorer) { $this->upsertPlayerAward($seasonId, $competitionId, 'TOP_SCORER', (int)$topScorer['player_id'], (int)$topScorer['club_id'], null, null, [], (float)$topScorer['score']); $count++; }
        if ($topAssist) { $this->upsertPlayerAward($seasonId, $competitionId, 'TOP_ASSIST', (int)$topAssist['player_id'], (int)$topAssist['club_id'], null, null, [], (float)$topAssist['score']); $count++; }
        if ($bestPlayer) { $this->upsertPlayerAward($seasonId, $competitionId, 'BEST_PLAYER', (int)$bestPlayer['player_id'], (int)$bestPlayer['club_id'], null, null, [], (float)$bestPlayer['score']); $count++; }
        if ($bestYoung) { $this->upsertPlayerAward($seasonId, $competitionId, 'BEST_YOUNG_PLAYER', (int)$bestYoung['player_id'], (int)$bestYoung['club_id'], null, null, [], (float)$bestYoung['score']); $count++; }

        $this->refreshClubRecordsAndLegends($clubIds);
        return ['ok' => true, 'awards' => $count];
    }

    public function addClubHonor(int $clubId, int $seasonId, int $competitionId, string $honorType, string $details = ''): void {
        $exists = $this->db->fetchOne(
            "SELECT id FROM club_honors
             WHERE club_id = ? AND season_id = ? AND competition_id = ? AND honor_type = ?",
            [$clubId, $seasonId, $competitionId, $honorType]
        );
        if ($exists) return;
        $this->db->insert('club_honors', [
            'club_id' => $clubId,
            'season_id' => $seasonId,
            'competition_id' => $competitionId,
            'honor_type' => $honorType,
            'details' => trim($details) ?: null,
        ]);
    }

    public function getRecentRecognitionsForClub(int $clubId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT pa.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, c.name AS competition_name
             FROM player_awards pa
             JOIN players p ON p.id = pa.player_id
             JOIN competitions c ON c.id = pa.competition_id
             WHERE pa.club_id = ?
               AND pa.award_type IN ('PLAYER_OF_MATCH', 'PLAYER_OF_WEEK')
             ORDER BY pa.created_at DESC, pa.id DESC
             LIMIT " . max(1, (int)$limit),
            [$clubId]
        );
    }

    public function getSeasonAwardsForClub(int $clubId, int $limit = 15): array {
        return $this->db->fetchAll(
            "SELECT pa.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, c.name AS competition_name, s.name AS season_name
             FROM player_awards pa
             JOIN players p ON p.id = pa.player_id
             JOIN competitions c ON c.id = pa.competition_id
             JOIN seasons s ON s.id = pa.season_id
             WHERE pa.club_id = ?
               AND pa.award_type IN ('TOP_SCORER','TOP_ASSIST','BEST_PLAYER','BEST_YOUNG_PLAYER')
             ORDER BY s.end_date DESC, pa.id DESC
             LIMIT " . max(1, (int)$limit),
            [$clubId]
        );
    }

    public function getClubHonors(int $clubId, int $limit = 20): array {
        return $this->db->fetchAll(
            "SELECT ch.*, c.name AS competition_name, s.name AS season_name
             FROM club_honors ch
             JOIN competitions c ON c.id = ch.competition_id
             JOIN seasons s ON s.id = ch.season_id
             WHERE ch.club_id = ?
             ORDER BY s.end_date DESC, ch.id DESC
             LIMIT " . max(1, (int)$limit),
            [$clubId]
        );
    }

    public function getClubRecords(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT cr.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name
             FROM club_records cr
             LEFT JOIN players p ON p.id = cr.player_id
             WHERE cr.club_id = ?
             ORDER BY cr.record_key ASC",
            [$clubId]
        );
    }

    public function getClubLegends(int $clubId, int $limit = 20): array {
        return $this->db->fetchAll(
            "SELECT cl.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name
             FROM club_legends cl
             JOIN players p ON p.id = cl.player_id
             WHERE cl.club_id = ?
             ORDER BY cl.legend_score DESC, cl.id ASC
             LIMIT " . max(1, (int)$limit),
            [$clubId]
        );
    }

    public function refreshClubRecordsAndLegends(array $clubIds): void {
        foreach ($clubIds as $clubId) {
            $clubId = (int)$clubId;
            if ($clubId <= 0) continue;

            $topScorer = $this->db->fetchOne(
                "SELECT player_id, SUM(goals) AS v
                 FROM player_career_history
                 WHERE club_id = ?
                 GROUP BY player_id
                 ORDER BY v DESC, player_id ASC
                 LIMIT 1",
                [$clubId]
            );
            $topApps = $this->db->fetchOne(
                "SELECT player_id, SUM(appearances) AS v
                 FROM player_career_history
                 WHERE club_id = ?
                 GROUP BY player_id
                 ORDER BY v DESC, player_id ASC
                 LIMIT 1",
                [$clubId]
            );
            $bestSeasonScorer = $this->db->fetchOne(
                "SELECT player_id, MAX(goals) AS v
                 FROM player_career_history
                 WHERE club_id = ?
                 GROUP BY player_id
                 ORDER BY v DESC, player_id ASC
                 LIMIT 1",
                [$clubId]
            );

            $this->upsertClubRecord($clubId, 'TOP_SCORER', (int)($topScorer['player_id'] ?? 0), (int)($topScorer['v'] ?? 0));
            $this->upsertClubRecord($clubId, 'MOST_APPEARANCES', (int)($topApps['player_id'] ?? 0), (int)($topApps['v'] ?? 0));
            $this->upsertClubRecord($clubId, 'BEST_SEASON_SCORER', (int)($bestSeasonScorer['player_id'] ?? 0), (int)($bestSeasonScorer['v'] ?? 0));

            $legendRows = $this->db->fetchAll(
                "SELECT player_id,
                        SUM(appearances) AS apps,
                        SUM(goals) AS goals,
                        SUM(assists) AS assists,
                        AVG(avg_rating) AS rating
                 FROM player_career_history
                 WHERE club_id = ?
                 GROUP BY player_id",
                [$clubId]
            );
            foreach ($legendRows as $row) {
                $score = ((int)$row['apps'] * 1.2) + ((int)$row['goals'] * 2.5) + ((int)$row['assists'] * 1.5) + ((float)$row['rating'] * 8);
                if ($score < 120) continue;
                $this->upsertLegend($clubId, (int)$row['player_id'], (int)round($score));
            }
        }
    }

    private function upsertPlayerAward(
        int $seasonId,
        int $competitionId,
        string $awardType,
        int $playerId,
        int $clubId,
        ?int $matchId = null,
        ?int $weekNumber = null,
        array $meta = [],
        float $scoreValue = 0
    ): void {
        $exists = $this->db->fetchOne(
            "SELECT id FROM player_awards
             WHERE season_id = ? AND competition_id = ? AND award_type = ?
               AND COALESCE(match_id,0) = COALESCE(?,0)
               AND COALESCE(week_number,0) = COALESCE(?,0)",
            [$seasonId, $competitionId, $awardType, $matchId, $weekNumber]
        );
        if ($exists) {
            $this->db->execute(
                "UPDATE player_awards
                 SET player_id = ?, club_id = ?, score_value = ?, meta_json = ?, updated_at = NOW()
                 WHERE id = ?",
                [$playerId, $clubId, $scoreValue, empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE), (int)$exists['id']]
            );
            return;
        }

        $this->db->insert('player_awards', [
            'season_id' => $seasonId,
            'competition_id' => $competitionId,
            'award_type' => $awardType,
            'player_id' => $playerId,
            'club_id' => $clubId,
            'match_id' => $matchId,
            'week_number' => $weekNumber,
            'score_value' => $scoreValue,
            'meta_json' => empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function upsertClubRecord(int $clubId, string $recordKey, int $playerId, int $recordValue): void {
        $exists = $this->db->fetchOne(
            "SELECT id FROM club_records WHERE club_id = ? AND record_key = ?",
            [$clubId, $recordKey]
        );
        if ($exists) {
            $this->db->execute(
                "UPDATE club_records SET player_id = ?, record_value = ?, updated_at = NOW() WHERE id = ?",
                [$playerId, $recordValue, (int)$exists['id']]
            );
            return;
        }
        $this->db->insert('club_records', [
            'club_id' => $clubId,
            'record_key' => $recordKey,
            'player_id' => $playerId,
            'record_value' => $recordValue,
        ]);
    }

    private function upsertLegend(int $clubId, int $playerId, int $score): void {
        $exists = $this->db->fetchOne("SELECT id FROM club_legends WHERE club_id = ? AND player_id = ?", [$clubId, $playerId]);
        if ($exists) {
            $this->db->execute("UPDATE club_legends SET legend_score = ?, updated_at = NOW() WHERE id = ?", [$score, (int)$exists['id']]);
            return;
        }
        $this->db->insert('club_legends', [
            'club_id' => $clubId,
            'player_id' => $playerId,
            'legend_score' => $score,
            'status' => 'LEGEND',
        ]);
    }

    private function ensureHistoryTables(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS player_awards (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                season_id INT NOT NULL,
                competition_id INT NOT NULL,
                award_type ENUM('PLAYER_OF_MATCH','PLAYER_OF_WEEK','TOP_SCORER','TOP_ASSIST','BEST_PLAYER','BEST_YOUNG_PLAYER') NOT NULL,
                player_id INT NOT NULL,
                club_id INT NOT NULL,
                match_id INT NULL,
                week_number INT NULL,
                score_value DECIMAL(10,2) DEFAULT 0,
                meta_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_award_scope (season_id, competition_id, award_type, match_id, week_number),
                INDEX idx_award_player (player_id, season_id),
                FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
                FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_honors (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                season_id INT NOT NULL,
                competition_id INT NOT NULL,
                honor_type ENUM('LEAGUE_TITLE','CUP_WIN','PROMOTION','RELEGATION','CHAMPIONS_QUALIFIED') NOT NULL,
                details VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_honor (club_id, season_id, competition_id, honor_type),
                INDEX idx_honor_club (club_id, created_at),
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
                FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_records (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                record_key ENUM('TOP_SCORER','MOST_APPEARANCES','BEST_SEASON_SCORER') NOT NULL,
                player_id INT NOT NULL,
                record_value INT NOT NULL DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_club_record (club_id, record_key),
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_legends (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                player_id INT NOT NULL,
                legend_score INT NOT NULL DEFAULT 0,
                status ENUM('ICON','LEGEND') DEFAULT 'LEGEND',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_club_legend_player (club_id, player_id),
                INDEX idx_legend_club_score (club_id, legend_score),
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
