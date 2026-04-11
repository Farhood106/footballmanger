<?php
// app/Services/GovernanceService.php

class GovernanceService {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    public function getEligibleClubs(int $userId): array {
        return $this->db->fetchAll(
            "SELECT * FROM clubs WHERE owner_user_id = ? OR manager_user_id = ? ORDER BY name ASC",
            [$userId, $userId]
        );
    }

    public function createCase(int $raisedByUserId, int $clubId, string $caseType, string $subject, string $description): array {
        $subject = trim($subject);
        $description = trim($description);

        if ($clubId <= 0 || $subject === '' || $description === '') {
            return ['ok' => false, 'error' => 'All case fields are required.'];
        }

        $club = $this->db->fetchOne("SELECT * FROM clubs WHERE id = ?", [$clubId]);
        if (!$club) {
            return ['ok' => false, 'error' => 'Club not found.'];
        }

        $ownerId = (int)($club['owner_user_id'] ?? 0);
        $managerId = (int)($club['manager_user_id'] ?? 0);

        if ($raisedByUserId !== $ownerId && $raisedByUserId !== $managerId) {
            return ['ok' => false, 'error' => 'Only current owner/manager can open a governance case.'];
        }

        $against = $raisedByUserId === $ownerId ? $managerId : $ownerId;
        if ($against <= 0) {
            return ['ok' => false, 'error' => 'Opposite party is not assigned to this club yet.'];
        }

        $contract = $this->db->fetchOne(
            "SELECT id FROM manager_contracts
             WHERE club_id = ? AND owner_user_id = ? AND coach_user_id = ?
             ORDER BY id DESC LIMIT 1",
            [$clubId, $ownerId, $managerId]
        );

        $caseId = $this->db->insert('club_governance_cases', [
            'club_id' => $clubId,
            'contract_id' => $contract['id'] ?? null,
            'owner_user_id' => $ownerId,
            'manager_user_id' => $managerId,
            'raised_by_user_id' => $raisedByUserId,
            'against_user_id' => $against,
            'case_type' => $caseType,
            'subject' => $subject,
            'description' => $description,
            'status' => 'open',
            'opened_at' => date('Y-m-d H:i:s')
        ]);

        return ['ok' => true, 'case_id' => $caseId];
    }

    public function getCasesForUser(int $userId): array {
        return $this->db->fetchAll(
            "SELECT gc.*, c.name AS club_name,
                    ru.username AS raised_by_name,
                    au.username AS against_name
             FROM club_governance_cases gc
             JOIN clubs c ON c.id = gc.club_id
             LEFT JOIN users ru ON ru.id = gc.raised_by_user_id
             LEFT JOIN users au ON au.id = gc.against_user_id
             WHERE gc.owner_user_id = ? OR gc.manager_user_id = ?
             ORDER BY gc.opened_at DESC",
            [$userId, $userId]
        );
    }

    public function getOpenCasesForReview(): array {
        return $this->db->fetchAll(
            "SELECT gc.*, c.name AS club_name, ru.username AS raised_by_name
             FROM club_governance_cases gc
             JOIN clubs c ON c.id = gc.club_id
             LEFT JOIN users ru ON ru.id = gc.raised_by_user_id
             WHERE gc.status IN ('open','under_review')
             ORDER BY gc.opened_at ASC"
        );
    }

    public function getCaseWithDecisions(int $caseId): ?array {
        $case = $this->db->fetchOne(
            "SELECT gc.*, c.name AS club_name,
                    ou.username AS owner_name,
                    mu.username AS manager_name,
                    ru.username AS raised_by_name
             FROM club_governance_cases gc
             JOIN clubs c ON c.id = gc.club_id
             LEFT JOIN users ou ON ou.id = gc.owner_user_id
             LEFT JOIN users mu ON mu.id = gc.manager_user_id
             LEFT JOIN users ru ON ru.id = gc.raised_by_user_id
             WHERE gc.id = ?",
            [$caseId]
        );

        if (!$case) return null;

        $case['decisions'] = $this->db->fetchAll(
            "SELECT d.*, u.username AS decided_by_name
             FROM club_governance_decisions d
             LEFT JOIN users u ON u.id = d.decided_by_user_id
             WHERE d.case_id = ?
             ORDER BY d.decided_at DESC",
            [$caseId]
        );

        return $case;
    }

    public function resolveCase(int $caseId, int $reviewerId, string $decisionType, string $summary, int $penaltyAmount, int $compensationAmount): array {
        $summary = trim($summary);
        if ($summary === '') {
            return ['ok' => false, 'error' => 'Decision summary is required.'];
        }

        $this->db->beginTransaction();
        try {
            $case = $this->db->fetchOne("SELECT * FROM club_governance_cases WHERE id = ? FOR UPDATE", [$caseId]);
            if (!$case) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Case not found.'];
            }

            if (in_array($case['status'], ['resolved', 'rejected'], true)) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Case already closed.'];
            }

            $this->db->insert('club_governance_decisions', [
                'case_id' => $caseId,
                'decision_type' => $decisionType,
                'decision_summary' => $summary,
                'penalty_amount' => max(0, $penaltyAmount),
                'compensation_amount' => max(0, $compensationAmount),
                'decided_by_user_id' => $reviewerId,
                'decided_at' => date('Y-m-d H:i:s')
            ]);

            $finalStatus = self::finalStatusForDecision($decisionType);
            $this->db->execute(
                "UPDATE club_governance_cases
                 SET status = ?, resolved_at = NOW()
                 WHERE id = ?",
                [$finalStatus, $caseId]
            );

            $effects = self::ledgerEffects(max(0, $penaltyAmount), max(0, $compensationAmount));
            foreach ($effects as $effect) {
                $this->db->insert('club_finance_ledger', [
                    'club_id' => (int)$case['club_id'],
                    'entry_type' => $effect['entry_type'],
                    'amount' => $effect['amount'],
                    'description' => $effect['description'] . ' (Governance case #' . $caseId . ')',
                    'reference_type' => 'GOVERNANCE_CASE',
                    'reference_id' => $caseId,
                ]);
                $this->db->execute("UPDATE clubs SET balance = balance + ? WHERE id = ?", [$effect['amount'], (int)$case['club_id']]);
            }

            $this->db->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function finalStatusForDecision(string $decisionType): string {
        return $decisionType === 'CASE_REJECTED' ? 'rejected' : 'resolved';
    }

    public static function ledgerEffects(int $penaltyAmount, int $compensationAmount): array {
        $effects = [];

        if ($penaltyAmount > 0) {
            $effects[] = [
                'entry_type' => 'PENALTY',
                'amount' => -1 * $penaltyAmount,
                'description' => 'Governance penalty'
            ];
        }

        if ($compensationAmount > 0) {
            $effects[] = [
                'entry_type' => 'OTHER',
                'amount' => -1 * $compensationAmount,
                'description' => 'Governance compensation payment'
            ];
        }

        return $effects;
    }
}
