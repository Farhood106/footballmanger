<?php
// app/Models/ManagerApplicationModel.php

class ManagerApplicationModel extends BaseModel {
    protected string $table = 'club_manager_applications';

    public function __construct() {
        parent::__construct();
        $this->ensureTables();
    }

    public function ensureTables(): void {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_manager_expectations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                owner_user_id INT,
                title VARCHAR(255) NOT NULL,
                expectations TEXT,
                duties TEXT,
                commitments TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_club_expectation (club_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS club_manager_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                coach_user_id INT NOT NULL,
                proposed_expectations TEXT,
                proposed_duties TEXT,
                proposed_commitments TEXT,
                cover_letter TEXT,
                status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') DEFAULT 'PENDING',
                reviewed_by INT,
                reviewed_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pending_coach_club (club_id, coach_user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureUtf8ForTable('club_manager_expectations');
        $this->ensureUtf8ForTable('club_manager_applications');
    }

    public function upsertExpectation(int $clubId, int $ownerUserId, string $title, string $expectations, string $duties, string $commitments): void {
        $exists = $this->db->fetchOne("SELECT id FROM club_manager_expectations WHERE club_id = ?", [$clubId]);

        if ($exists) {
            $this->db->execute(
                "UPDATE club_manager_expectations
                 SET owner_user_id = ?, title = ?, expectations = ?, duties = ?, commitments = ?, updated_at = NOW()
                 WHERE club_id = ?",
                [$ownerUserId, $title, $expectations, $duties, $commitments, $clubId]
            );
            return;
        }

        $this->db->insert('club_manager_expectations', [
            'club_id' => $clubId,
            'owner_user_id' => $ownerUserId,
            'title' => $title,
            'expectations' => $expectations,
            'duties' => $duties,
            'commitments' => $commitments,
        ]);
    }

    public function getExpectationByClub(int $clubId): ?array {
        return $this->db->fetchOne("SELECT * FROM club_manager_expectations WHERE club_id = ?", [$clubId]);
    }

    public function hasPendingApplication(int $clubId, int $coachUserId): bool {
        $row = $this->db->fetchOne(
            "SELECT id FROM club_manager_applications
             WHERE club_id = ? AND coach_user_id = ? AND status = 'PENDING' LIMIT 1",
            [$clubId, $coachUserId]
        );

        return $row !== null;
    }

    public function submitApplication(
        int $clubId,
        int $coachUserId,
        string $proposedExpectations,
        string $proposedDuties,
        string $proposedCommitments,
        string $coverLetter
    ): int {
        return $this->db->insert('club_manager_applications', [
            'club_id' => $clubId,
            'coach_user_id' => $coachUserId,
            'proposed_expectations' => $proposedExpectations,
            'proposed_duties' => $proposedDuties,
            'proposed_commitments' => $proposedCommitments,
            'cover_letter' => $coverLetter,
            'status' => 'PENDING'
        ]);
    }

    public function getPendingForReviewer(int $userId, bool $isAdmin): array {
        if ($isAdmin) {
            return $this->db->fetchAll(
                "SELECT a.*, c.name AS club_name, u.username AS coach_name
                 FROM club_manager_applications a
                 JOIN clubs c ON a.club_id = c.id
                 JOIN users u ON a.coach_user_id = u.id
                 WHERE a.status = 'PENDING'
                 ORDER BY a.created_at ASC"
            );
        }

        return $this->db->fetchAll(
            "SELECT a.*, c.name AS club_name, u.username AS coach_name
             FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             JOIN users u ON a.coach_user_id = u.id
             WHERE a.status = 'PENDING' AND c.owner_user_id = ?
             ORDER BY a.created_at ASC",
            [$userId]
        );
    }

    public function approve(int $applicationId, int $reviewerId, bool $isAdmin): bool {
        $app = $this->db->fetchOne(
            "SELECT a.*, c.owner_user_id FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             WHERE a.id = ?",
            [$applicationId]
        );

        if (!$app || $app['status'] !== 'PENDING') return false;

        $canReview = $isAdmin || ((int)$app['owner_user_id'] === $reviewerId);
        if (!$canReview) return false;

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE clubs SET manager_user_id = ?, user_id = ? WHERE id = ?",
                [(int)$app['coach_user_id'], (int)$app['coach_user_id'], (int)$app['club_id']]
            );

            $this->db->execute(
                "UPDATE club_manager_applications
                 SET status = 'APPROVED', reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$reviewerId, $applicationId]
            );

            $this->db->execute(
                "UPDATE club_manager_applications
                 SET status = 'REJECTED', reviewed_by = ?, reviewed_at = NOW()
                 WHERE club_id = ? AND status = 'PENDING' AND id <> ?",
                [$reviewerId, (int)$app['club_id'], $applicationId]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function reject(int $applicationId, int $reviewerId, bool $isAdmin): bool {
        $app = $this->db->fetchOne(
            "SELECT a.*, c.owner_user_id FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             WHERE a.id = ?",
            [$applicationId]
        );

        if (!$app || $app['status'] !== 'PENDING') return false;

        $canReview = $isAdmin || ((int)$app['owner_user_id'] === $reviewerId);
        if (!$canReview) return false;

        return $this->db->execute(
            "UPDATE club_manager_applications
             SET status = 'REJECTED', reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?",
            [$reviewerId, $applicationId]
        ) > 0;
    }

    private function ensureUtf8ForTable(string $table): void {
        try {
            $this->db->execute(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            // اگر دسترسی ALTER وجود نداشت، از جدول موجود استفاده می‌کنیم
        }
    }
}
