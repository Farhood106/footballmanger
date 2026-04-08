<?php
// app/Models/OwnershipRequestModel.php

class OwnershipRequestModel extends BaseModel {
    protected string $table = 'club_ownership_requests';

    public function hasPendingRequest(int $userId, int $clubId): bool {
        $row = $this->db->fetchOne(
            "SELECT id FROM club_ownership_requests WHERE user_id = ? AND club_id = ? AND status = 'PENDING' LIMIT 1",
            [$userId, $clubId]
        );

        return $row !== null;
    }

    public function createRequest(int $userId, int $clubId, int $offerAmount, string $message = ''): int {
        return $this->db->insert('club_ownership_requests', [
            'user_id' => $userId,
            'club_id' => $clubId,
            'offer_amount' => $offerAmount,
            'message' => $message,
            'status' => 'PENDING'
        ]);
    }

    public function getUserRequests(int $userId): array {
        return $this->db->fetchAll(
            "SELECT r.*, c.name AS club_name
             FROM club_ownership_requests r
             JOIN clubs c ON r.club_id = c.id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC",
            [$userId]
        );
    }

    public function getPendingForAdmin(): array {
        return $this->db->fetchAll(
            "SELECT r.*, c.name AS club_name, u.username AS requester_name, c.owner_user_id
             FROM club_ownership_requests r
             JOIN clubs c ON r.club_id = c.id
             JOIN users u ON r.user_id = u.id
             WHERE r.status = 'PENDING'
             ORDER BY r.created_at ASC"
        );
    }

    public function getPendingForOwner(int $ownerUserId): array {
        return $this->db->fetchAll(
            "SELECT r.*, c.name AS club_name, u.username AS requester_name, c.owner_user_id
             FROM club_ownership_requests r
             JOIN clubs c ON r.club_id = c.id
             JOIN users u ON r.user_id = u.id
             WHERE r.status = 'PENDING' AND c.owner_user_id = ?
             ORDER BY r.created_at ASC",
            [$ownerUserId]
        );
    }

    public function findWithClub(int $requestId): ?array {
        return $this->db->fetchOne(
            "SELECT r.*, c.owner_user_id, c.name AS club_name
             FROM club_ownership_requests r
             JOIN clubs c ON r.club_id = c.id
             WHERE r.id = ?",
            [$requestId]
        );
    }

    public function approve(int $requestId, int $reviewerId, bool $isAdmin): bool {
        $request = $this->findWithClub($requestId);
        if (!$request || $request['status'] !== 'PENDING') {
            return false;
        }

        $currentOwnerId = (int)($request['owner_user_id'] ?? 0);
        $canReview = $isAdmin || ($currentOwnerId > 0 && $currentOwnerId === $reviewerId);
        if (!$canReview) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE clubs SET owner_user_id = ? WHERE id = ?",
                [(int)$request['user_id'], (int)$request['club_id']]
            );

            $this->db->execute(
                "UPDATE club_ownership_requests
                 SET status = 'APPROVED', reviewed_at = NOW(), reviewed_by = ?
                 WHERE id = ?",
                [$reviewerId, $requestId]
            );

            $this->db->execute(
                "UPDATE club_ownership_requests
                 SET status = 'REJECTED', reviewed_at = NOW(), reviewed_by = ?
                 WHERE club_id = ? AND status = 'PENDING' AND id <> ?",
                [$reviewerId, (int)$request['club_id'], $requestId]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function reject(int $requestId, int $reviewerId, bool $isAdmin): bool {
        $request = $this->findWithClub($requestId);
        if (!$request || $request['status'] !== 'PENDING') {
            return false;
        }

        $currentOwnerId = (int)($request['owner_user_id'] ?? 0);
        $canReview = $isAdmin || ($currentOwnerId > 0 && $currentOwnerId === $reviewerId);
        if (!$canReview) {
            return false;
        }

        return $this->db->execute(
            "UPDATE club_ownership_requests
             SET status = 'REJECTED', reviewed_at = NOW(), reviewed_by = ?
             WHERE id = ? AND status = 'PENDING'",
            [$reviewerId, $requestId]
        ) > 0;
    }
}
