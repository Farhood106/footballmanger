<?php
// app/Models/ManagerApplicationModel.php

class ManagerApplicationModel extends BaseModel {
    protected string $table = 'club_manager_applications';

    private const NEGOTIATION_STATUSES = ['open', 'accepted', 'rejected', 'expired', 'superseded'];
    private const TERMINATION_TYPES = ['OWNER_TERMINATION', 'MUTUAL_TERMINATION', 'ADMIN_FORCED_TERMINATION'];

    public function __construct() {
        parent::__construct();
        if ($this->db->shouldRunRuntimeDdlFallback()) {
            $this->ensureTables();
        }
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
                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                rejection_reason VARCHAR(1000),
                reviewed_by_user_id INT,
                reviewed_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pending_coach_club (club_id, coach_user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS manager_contract_negotiations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                club_id INT NOT NULL,
                coach_user_id INT NOT NULL,
                owner_user_id INT NOT NULL,
                status ENUM('open','accepted','rejected','expired','superseded') DEFAULT 'open',
                offered_salary_per_cycle BIGINT NOT NULL,
                offered_contract_length_cycles INT NOT NULL,
                club_objective VARCHAR(255),
                bonus_promotion BIGINT DEFAULT 0,
                bonus_title BIGINT DEFAULT 0,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                responded_at DATETIME,
                INDEX idx_negotiation_application_status (application_id, status),
                INDEX idx_negotiation_coach_status (coach_user_id, status),
                INDEX idx_negotiation_owner_status (owner_user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS manager_contract_terminations (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                contract_id INT NOT NULL,
                club_id INT NOT NULL,
                owner_user_id INT NULL,
                coach_user_id INT NULL,
                terminated_by_user_id INT NOT NULL,
                termination_type ENUM('OWNER_TERMINATION','MUTUAL_TERMINATION','ADMIN_FORCED_TERMINATION') NOT NULL,
                compensation_amount BIGINT DEFAULT 0,
                reason VARCHAR(1000),
                governance_case_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_termination_contract (contract_id, created_at),
                INDEX idx_termination_club (club_id, created_at),
                INDEX idx_termination_actor (terminated_by_user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColumnExists('club_manager_applications', 'rejection_reason', "VARCHAR(1000) NULL AFTER status");
        $this->renameColumnIfExists('club_manager_applications', 'reviewed_by', 'reviewed_by_user_id', 'INT NULL');
        $this->db->execute("UPDATE club_manager_applications SET status = LOWER(status)");

        $this->ensureUtf8ForTable('club_manager_expectations');
        $this->ensureUtf8ForTable('club_manager_applications');
        $this->ensureUtf8ForTable('manager_contract_negotiations');
        $this->ensureUtf8ForTable('manager_contract_terminations');
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
             WHERE club_id = ? AND coach_user_id = ? AND LOWER(status) = 'pending' LIMIT 1",
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
            'status' => 'pending'
        ]);
    }

    public function getByCoach(int $coachUserId): array {
        return $this->db->fetchAll(
            "SELECT a.*, c.name AS club_name
             FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             WHERE a.coach_user_id = ?
             ORDER BY a.created_at DESC",
            [$coachUserId]
        );
    }

    public function getPendingForReviewer(int $userId, bool $isAdmin): array {
        if ($isAdmin) {
            return $this->db->fetchAll(
                "SELECT a.*, c.name AS club_name, u.username AS coach_name
                 FROM club_manager_applications a
                 JOIN clubs c ON a.club_id = c.id
                 JOIN users u ON a.coach_user_id = u.id
                 WHERE LOWER(a.status) = 'pending'
                 ORDER BY a.created_at ASC"
            );
        }

        return $this->db->fetchAll(
            "SELECT a.*, c.name AS club_name, u.username AS coach_name
             FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             JOIN users u ON a.coach_user_id = u.id
             WHERE LOWER(a.status) = 'pending' AND c.owner_user_id = ?
             ORDER BY a.created_at ASC",
            [$userId]
        );
    }

    public function getOffersForReviewer(int $userId, bool $isAdmin): array {
        if ($isAdmin) {
            return $this->db->fetchAll(
                "SELECT n.*, c.name AS club_name, u.username AS coach_name
                 FROM manager_contract_negotiations n
                 JOIN clubs c ON c.id = n.club_id
                 JOIN users u ON u.id = n.coach_user_id
                 WHERE n.status = 'open'
                 ORDER BY n.created_at DESC"
            );
        }

        return $this->db->fetchAll(
            "SELECT n.*, c.name AS club_name, u.username AS coach_name
             FROM manager_contract_negotiations n
             JOIN clubs c ON c.id = n.club_id
             JOIN users u ON u.id = n.coach_user_id
             WHERE n.status = 'open' AND n.owner_user_id = ?
             ORDER BY n.created_at DESC",
            [$userId]
        );
    }

    public function getOffersForCoach(int $coachUserId): array {
        return $this->db->fetchAll(
            "SELECT n.*, c.name AS club_name, o.username AS owner_name
             FROM manager_contract_negotiations n
             JOIN clubs c ON c.id = n.club_id
             LEFT JOIN users o ON o.id = n.owner_user_id
             WHERE n.coach_user_id = ?
             ORDER BY n.created_at DESC",
            [$coachUserId]
        );
    }

    public function getActiveContractsForActor(int $actorUserId, bool $isAdmin): array {
        if ($isAdmin) {
            return $this->db->fetchAll(
                "SELECT mc.*, c.name AS club_name, o.username AS owner_name, u.username AS coach_name
                 FROM manager_contracts mc
                 JOIN clubs c ON c.id = mc.club_id
                 LEFT JOIN users o ON o.id = mc.owner_user_id
                 LEFT JOIN users u ON u.id = mc.coach_user_id
                 WHERE mc.status = 'ACTIVE'
                 ORDER BY mc.id DESC"
            );
        }

        return $this->db->fetchAll(
            "SELECT mc.*, c.name AS club_name, o.username AS owner_name, u.username AS coach_name
             FROM manager_contracts mc
             JOIN clubs c ON c.id = mc.club_id
             LEFT JOIN users o ON o.id = mc.owner_user_id
             LEFT JOIN users u ON u.id = mc.coach_user_id
             WHERE mc.status = 'ACTIVE' AND (mc.owner_user_id = ? OR mc.coach_user_id = ?)
             ORDER BY mc.id DESC",
            [$actorUserId, $actorUserId]
        );
    }

    public function terminateActiveContract(
        int $clubId,
        int $actorUserId,
        bool $isAdmin,
        string $terminationType,
        ?int $requestedCompensation,
        string $reason,
        bool $openGovernanceCase = false
    ): array {
        $terminationType = strtoupper(trim($terminationType));
        if (!in_array($terminationType, self::TERMINATION_TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid termination type.'];
        }

        $this->db->beginTransaction();
        try {
            $contract = $this->db->fetchOne(
                "SELECT mc.*, c.manager_user_id
                 FROM manager_contracts mc
                 JOIN clubs c ON c.id = mc.club_id
                 WHERE mc.club_id = ? AND mc.status = 'ACTIVE'
                 ORDER BY mc.id DESC
                 LIMIT 1 FOR UPDATE",
                [$clubId]
            );
            if (!$contract) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'No active manager contract found.'];
            }

            $ownerId = (int)($contract['owner_user_id'] ?? 0);
            $coachId = (int)($contract['coach_user_id'] ?? 0);
            $canOwner = $isAdmin || $actorUserId === $ownerId;
            $canCoach = $isAdmin || $actorUserId === $coachId;

            if ($terminationType === 'OWNER_TERMINATION' && !$canOwner) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Only owner/admin can perform owner termination.'];
            }
            if ($terminationType === 'MUTUAL_TERMINATION' && !($canOwner || $canCoach)) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Only owner/coach/admin can perform mutual termination.'];
            }
            if ($terminationType === 'ADMIN_FORCED_TERMINATION' && !$isAdmin) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Only admin can force termination.'];
            }

            $compensation = $this->resolveCompensationAmount($terminationType, (int)($contract['salary'] ?? 0), $requestedCompensation);
            $reason = trim($reason);
            if ($reason === '') {
                $reason = $terminationType === 'MUTUAL_TERMINATION' ? 'Mutual termination agreed.' : 'Contract terminated by club.';
            }

            $this->db->execute(
                "UPDATE manager_contracts
                 SET status = 'TERMINATED',
                     end_date = CURDATE(),
                     termination_reason = ?,
                     updated_at = NOW()
                 WHERE id = ? AND status = 'ACTIVE'",
                [$reason, (int)$contract['id']]
            );

            $this->db->execute(
                "UPDATE clubs
                 SET manager_user_id = NULL, user_id = NULL
                 WHERE id = ? AND manager_user_id = ?",
                [$clubId, $coachId]
            );

            $governanceCaseId = null;
            if ($openGovernanceCase && $ownerId > 0 && $coachId > 0) {
                $governanceCaseId = (int)$this->db->insert('club_governance_cases', [
                    'club_id' => $clubId,
                    'contract_id' => (int)$contract['id'],
                    'owner_user_id' => $ownerId,
                    'manager_user_id' => $coachId,
                    'raised_by_user_id' => $actorUserId,
                    'against_user_id' => $actorUserId === $ownerId ? $coachId : $ownerId,
                    'case_type' => $terminationType === 'MUTUAL_TERMINATION' ? 'MUTUAL_TERMINATION_DISPUTE' : 'UNFAIR_DISMISSAL',
                    'subject' => 'Manager contract termination review',
                    'description' => $reason,
                    'status' => 'open',
                    'opened_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($compensation > 0) {
                $finance = new FinanceService($this->db);
                $post = $finance->postEntry(
                    $clubId,
                    'MANAGER_TERMINATION_COMPENSATION',
                    -1 * $compensation,
                    'Manager contract termination compensation',
                    null,
                    'MANAGER_CONTRACT_TERMINATION',
                    (int)$contract['id'],
                    [
                        'termination_type' => $terminationType,
                        'coach_user_id' => $coachId,
                        'owner_user_id' => $ownerId,
                    ],
                    false
                );
                if (empty($post['ok'])) {
                    $this->db->rollBack();
                    return ['ok' => false, 'error' => $post['error'] ?? 'Compensation posting failed.'];
                }
            }

            $this->db->insert('manager_contract_terminations', [
                'contract_id' => (int)$contract['id'],
                'club_id' => $clubId,
                'owner_user_id' => $ownerId > 0 ? $ownerId : null,
                'coach_user_id' => $coachId > 0 ? $coachId : null,
                'terminated_by_user_id' => $actorUserId,
                'termination_type' => $terminationType,
                'compensation_amount' => $compensation,
                'reason' => $reason,
                'governance_case_id' => $governanceCaseId ?: null,
            ]);

            $this->db->execute(
                "UPDATE manager_contract_negotiations
                 SET status = 'superseded', responded_at = NOW()
                 WHERE club_id = ? AND status = 'open'",
                [$clubId]
            );

            $this->db->commit();
            return ['ok' => true, 'compensation' => $compensation, 'governance_case_id' => $governanceCaseId];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendOffer(
        int $applicationId,
        int $actorId,
        bool $isAdmin,
        int $salaryPerCycle,
        int $lengthCycles,
        string $clubObjective,
        int $bonusPromotion,
        int $bonusTitle
    ): array {
        if ($salaryPerCycle < 0) {
            return ['ok' => false, 'error' => 'Salary must be non-negative.'];
        }
        if ($lengthCycles <= 0) {
            return ['ok' => false, 'error' => 'Contract length must be a positive number of cycles.'];
        }

        $app = $this->db->fetchOne(
            "SELECT a.*, c.owner_user_id, c.manager_user_id FROM club_manager_applications a
             JOIN clubs c ON c.id = a.club_id
             WHERE a.id = ?",
            [$applicationId]
        );

        if (!$app || strtolower((string)$app['status']) !== 'pending') {
            return ['ok' => false, 'error' => 'Application is not open for negotiation.'];
        }

        $ownerId = (int)($app['owner_user_id'] ?? 0);
        if ($ownerId <= 0) {
            return ['ok' => false, 'error' => 'Club owner is not set.'];
        }

        $canReview = $isAdmin || $ownerId === $actorId;
        if (!$canReview) {
            return ['ok' => false, 'error' => 'Only owner/admin can send an offer.'];
        }

        $duplicateOpen = $this->db->fetchOne(
            "SELECT id FROM manager_contract_negotiations WHERE application_id = ? AND status = 'open' LIMIT 1",
            [$applicationId]
        );

        if ($duplicateOpen) {
            return ['ok' => false, 'error' => 'An active negotiation already exists for this application.'];
        }

        $this->db->insert('manager_contract_negotiations', [
            'application_id' => $applicationId,
            'club_id' => (int)$app['club_id'],
            'coach_user_id' => (int)$app['coach_user_id'],
            'owner_user_id' => $ownerId,
            'status' => 'open',
            'offered_salary_per_cycle' => $salaryPerCycle,
            'offered_contract_length_cycles' => $lengthCycles,
            'club_objective' => trim($clubObjective),
            'bonus_promotion' => max(0, $bonusPromotion),
            'bonus_title' => max(0, $bonusTitle),
            'created_by_user_id' => $actorId,
        ]);

        return ['ok' => true];
    }

    public function respondToOffer(
        int $negotiationId,
        int $actorId,
        bool $isAdmin,
        string $action,
        int $salaryPerCycle = 0,
        int $lengthCycles = 0,
        string $clubObjective = '',
        int $bonusPromotion = 0,
        int $bonusTitle = 0
    ): array {
        $offer = $this->db->fetchOne("SELECT * FROM manager_contract_negotiations WHERE id = ?", [$negotiationId]);
        if (!$offer) return ['ok' => false, 'error' => 'Offer not found.'];
        if (($offer['status'] ?? '') !== 'open') {
            return ['ok' => false, 'error' => 'This negotiation is already closed.'];
        }

        $isCoach = (int)$offer['coach_user_id'] === $actorId;
        if (!$isCoach && !$isAdmin) {
            return ['ok' => false, 'error' => 'Only targeted coach/admin can respond to this offer.'];
        }

        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['accept', 'reject', 'counter'], true)) {
            return ['ok' => false, 'error' => 'Invalid response action.'];
        }

        if ($normalized === 'accept') {
            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "UPDATE manager_contract_negotiations SET status = 'accepted', responded_at = NOW() WHERE id = ? AND status = 'open'",
                    [$negotiationId]
                );

                $activation = $this->activateContractFromNegotiation((int)$offer['id'], (int)$actorId, (bool)$isAdmin);
                if (!$activation['ok']) {
                    $this->db->rollBack();
                    return $activation;
                }

                $this->db->execute(
                    "UPDATE manager_contract_negotiations
                     SET status = 'superseded', responded_at = NOW()
                     WHERE application_id = ? AND status = 'open' AND id <> ?",
                    [(int)$offer['application_id'], $negotiationId]
                );

                $this->db->commit();
                return ['ok' => true];
            } catch (Throwable $e) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        if ($normalized === 'reject') {
            $this->db->execute(
                "UPDATE manager_contract_negotiations SET status = 'rejected', responded_at = NOW() WHERE id = ? AND status = 'open'",
                [$negotiationId]
            );
            return ['ok' => true];
        }

        if ($salaryPerCycle < 0) {
            return ['ok' => false, 'error' => 'Salary must be non-negative.'];
        }
        if ($lengthCycles <= 0) {
            return ['ok' => false, 'error' => 'Contract length must be a positive number of cycles.'];
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE manager_contract_negotiations SET status = 'superseded', responded_at = NOW() WHERE id = ? AND status = 'open'",
                [$negotiationId]
            );

            $this->db->insert('manager_contract_negotiations', [
                'application_id' => (int)$offer['application_id'],
                'club_id' => (int)$offer['club_id'],
                'coach_user_id' => (int)$offer['coach_user_id'],
                'owner_user_id' => (int)$offer['owner_user_id'],
                'status' => 'open',
                'offered_salary_per_cycle' => $salaryPerCycle,
                'offered_contract_length_cycles' => $lengthCycles,
                'club_objective' => trim($clubObjective),
                'bonus_promotion' => max(0, $bonusPromotion),
                'bonus_title' => max(0, $bonusTitle),
                'created_by_user_id' => $actorId,
            ]);

            $this->db->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function approve(int $applicationId, int $reviewerId, bool $isAdmin): bool {
        $app = $this->db->fetchOne(
            "SELECT a.*, c.owner_user_id FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             WHERE a.id = ?",
            [$applicationId]
        );

        if (!$app || strtolower((string)$app['status']) !== 'pending') return false;

        $canReview = $isAdmin || ((int)$app['owner_user_id'] === $reviewerId);
        if (!$canReview) return false;

        $this->db->beginTransaction();
        try {
            $clubId = (int)$app['club_id'];
            $coachId = (int)$app['coach_user_id'];
            $ownerId = (int)($app['owner_user_id'] ?? 0);

            $this->db->execute(
                "UPDATE clubs SET manager_user_id = ?, user_id = ? WHERE id = ?",
                [$coachId, $coachId, $clubId]
            );

            $this->db->execute(
                "UPDATE manager_contracts SET status = 'TERMINATED', termination_reason = 'Replaced by new appointment', updated_at = NOW()
                 WHERE club_id = ? AND status = 'ACTIVE'",
                [$clubId]
            );

            if ($ownerId > 0) {
                $this->db->insert('manager_contracts', [
                    'club_id' => $clubId,
                    'owner_user_id' => $ownerId,
                    'coach_user_id' => $coachId,
                    'status' => 'ACTIVE',
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d', strtotime('+2 years')),
                    'salary' => 0,
                    'terms_json' => json_encode(['source' => 'application_approval', 'application_id' => $applicationId]),
                ]);
            }

            $this->db->execute(
                "UPDATE club_manager_applications
                 SET status = 'approved', rejection_reason = NULL, reviewed_by_user_id = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$reviewerId, $applicationId]
            );

            $this->db->execute(
                "UPDATE manager_contract_negotiations
                 SET status = 'superseded', responded_at = NOW()
                 WHERE application_id = ? AND status = 'open'",
                [$applicationId]
            );

            $this->db->execute(
                "UPDATE club_manager_applications
                 SET status = 'rejected', rejection_reason = 'Another candidate was selected for this role.',
                     reviewed_by_user_id = ?, reviewed_at = NOW()
                 WHERE club_id = ? AND LOWER(status) = 'pending' AND id <> ?",
                [$reviewerId, $clubId, $applicationId]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function reject(int $applicationId, int $reviewerId, bool $isAdmin, string $reason): bool {
        $app = $this->db->fetchOne(
            "SELECT a.*, c.owner_user_id FROM club_manager_applications a
             JOIN clubs c ON a.club_id = c.id
             WHERE a.id = ?",
            [$applicationId]
        );

        if (!$app || strtolower((string)$app['status']) !== 'pending') return false;

        $canReview = $isAdmin || ((int)$app['owner_user_id'] === $reviewerId);
        if (!$canReview) return false;

        $this->db->execute(
            "UPDATE manager_contract_negotiations
             SET status = 'superseded', responded_at = NOW()
             WHERE application_id = ? AND status = 'open'",
            [$applicationId]
        );

        return $this->db->execute(
            "UPDATE club_manager_applications
             SET status = 'rejected', rejection_reason = ?, reviewed_by_user_id = ?, reviewed_at = NOW()
             WHERE id = ?",
            [$reason, $reviewerId, $applicationId]
        ) > 0;
    }

    private function activateContractFromNegotiation(int $negotiationId, int $actorId, bool $isAdmin): array {
        $offer = $this->db->fetchOne(
            "SELECT n.*, a.status AS application_status
             FROM manager_contract_negotiations n
             JOIN club_manager_applications a ON a.id = n.application_id
             WHERE n.id = ?",
            [$negotiationId]
        );
        if (!$offer) return ['ok' => false, 'error' => 'Offer not found for activation.'];
        if (strtolower((string)$offer['application_status']) !== 'pending') {
            return ['ok' => false, 'error' => 'Application is not pending anymore.'];
        }

        $clubId = (int)$offer['club_id'];
        $coachId = (int)$offer['coach_user_id'];
        $ownerId = (int)$offer['owner_user_id'];

        $otherClub = $this->db->fetchOne(
            "SELECT id FROM clubs WHERE manager_user_id = ? AND id <> ? LIMIT 1",
            [$coachId, $clubId]
        );
        if ($otherClub) {
            return ['ok' => false, 'error' => 'Coach is already assigned as manager of another club.'];
        }

        $this->db->execute(
            "UPDATE manager_contracts
             SET status = 'TERMINATED', termination_reason = 'Replaced by negotiated appointment', updated_at = NOW()
             WHERE club_id = ? AND status = 'ACTIVE'",
            [$clubId]
        );

        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+' . max(1, (int)$offer['offered_contract_length_cycles']) . ' months'));

        $this->db->insert('manager_contracts', [
            'club_id' => $clubId,
            'owner_user_id' => $ownerId,
            'coach_user_id' => $coachId,
            'status' => 'ACTIVE',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'salary' => (int)$offer['offered_salary_per_cycle'],
            'terms_json' => json_encode([
                'source' => 'negotiation_accept',
                'application_id' => (int)$offer['application_id'],
                'negotiation_id' => $negotiationId,
                'contract_length_cycles' => (int)$offer['offered_contract_length_cycles'],
                'club_objective' => (string)($offer['club_objective'] ?? ''),
                'bonus_promotion' => (int)($offer['bonus_promotion'] ?? 0),
                'bonus_title' => (int)($offer['bonus_title'] ?? 0),
                'accepted_by_user_id' => $actorId,
                'accepted_by_admin' => $isAdmin,
            ]),
        ]);

        $this->db->execute(
            "UPDATE clubs SET manager_user_id = ?, user_id = ? WHERE id = ?",
            [$coachId, $coachId, $clubId]
        );

        $this->db->execute(
            "UPDATE club_manager_applications
             SET status = 'approved', rejection_reason = NULL, reviewed_by_user_id = ?, reviewed_at = NOW()
             WHERE id = ?",
            [$ownerId, (int)$offer['application_id']]
        );

        $this->db->execute(
            "UPDATE club_manager_applications
             SET status = 'rejected', rejection_reason = 'Another candidate was selected for this role.',
                 reviewed_by_user_id = ?, reviewed_at = NOW()
             WHERE club_id = ? AND LOWER(status) = 'pending' AND id <> ?",
            [$ownerId, $clubId, (int)$offer['application_id']]
        );

        return ['ok' => true];
    }

    private function resolveCompensationAmount(string $terminationType, int $salaryPerCycle, ?int $requestedCompensation): int {
        $requestedCompensation = $requestedCompensation ?? 0;
        if ($terminationType === 'MUTUAL_TERMINATION') {
            return max(0, $requestedCompensation);
        }
        if ($terminationType === 'ADMIN_FORCED_TERMINATION') {
            return max(0, $requestedCompensation);
        }
        $baseline = (int)round(max(0, $salaryPerCycle) * 0.5);
        return max(0, $requestedCompensation, $baseline);
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void {
        $exists = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        if (!$exists) {
            $this->db->execute("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private function renameColumnIfExists(string $table, string $from, string $to, string $type): void {
        $old = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $from]
        );
        $new = $this->db->fetchOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $to]
        );

        if ($old && !$new) {
            $this->db->execute("ALTER TABLE `{$table}` CHANGE COLUMN `{$from}` `{$to}` {$type}");
        }
    }

    private function ensureUtf8ForTable(string $table): void {
        try {
            $this->db->execute(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            // continue in restricted environments
        }
    }
}
