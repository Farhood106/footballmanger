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
}
