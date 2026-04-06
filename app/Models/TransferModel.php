<?php
// app/Models/TransferModel.php

class TransferModel extends BaseModel {
    protected string $table = 'transfers';

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT t.*,
                    p.name AS player_name, p.position, p.overall_rating,
                    fc.name AS from_club_name,
                    tc.name AS to_club_name
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             LEFT JOIN clubs fc ON t.from_club_id = fc.id
             LEFT JOIN clubs tc ON t.to_club_id = tc.id
             WHERE t.status = 'PENDING'
             ORDER BY t.created_at DESC"
        );
    }

    public function getByClub(int $clubId): array {
        return $this->db->fetchAll(
            "SELECT t.*, p.name AS player_name, p.position
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             WHERE (t.from_club_id = ? OR t.to_club_id = ?)
             ORDER BY t.created_at DESC",
            [$clubId, $clubId]
        );
    }

    public function makeBid(int $playerId, int $fromClubId, int $toClubId, float $amount): int {
        return $this->create([
            'player_id'    => $playerId,
            'from_club_id' => $fromClubId,
            'to_club_id'   => $toClubId,
            'fee'          => $amount,
            'status'       => 'PENDING',
            'created_at'   => date('Y-m-d H:i:s')
        ]);
    }

    public function accept(int $transferId): bool {
        $transfer = $this->find($transferId);
        if (!$transfer || $transfer['status'] !== 'PENDING') return false;

        $this->db->beginTransaction();
        try {
            // انتقال بازیکن
            $this->db->update('players',
                ['club_id' => $transfer['to_club_id']],
                'id = :id', ['id' => $transfer['player_id']]
            );

            // کسر از بودجه خریدار
            $this->db->query(
                "UPDATE clubs SET budget = budget - ? WHERE id = ?",
                [$transfer['fee'], $transfer['to_club_id']]
            );

            // افزودن به بودجه فروشنده
            $this->db->query(
                "UPDATE clubs SET budget = budget + ? WHERE id = ?",
                [$transfer['fee'], $transfer['from_club_id']]
            );

            // آپدیت وضعیت
            $this->update($transferId, ['status' => 'COMPLETED', 'completed_at' => date('Y-m-d H:i:s')]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    public function reject(int $transferId): bool {
        return $this->update($transferId, ['status' => 'REJECTED']);
    }
}
