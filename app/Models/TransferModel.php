<?php
// app/Models/TransferModel.php

class TransferModel extends BaseModel {
    protected string $table = 'transfers';

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT t.*,
                    CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall,
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
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall
             FROM transfers t
             JOIN players p ON t.player_id = p.id
             WHERE (t.from_club_id = ? OR t.to_club_id = ?)
             ORDER BY t.created_at DESC",
            [$clubId, $clubId]
        );
    }

    public function makeBid(int $playerId, int $fromClubId, int $toClubId, float $amount): int {
        return $this->create([
            'player_id' => $playerId,
            'from_club_id' => $fromClubId,
            'to_club_id' => $toClubId,
            'type' => 'PERMANENT',
            'fee' => $amount,
            'status' => 'PENDING',
            'initiated_by' => Auth::id() ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function accept(int $transferId): bool {
        $transfer = $this->find($transferId);
        if (!$transfer || $transfer['status'] !== 'PENDING') return false;

        $this->db->beginTransaction();
        try {
            $this->db->query(
                "UPDATE players SET club_id = ? WHERE id = ?",
                [$transfer['to_club_id'], $transfer['player_id']]
            );

            $this->db->query(
                "UPDATE clubs SET balance = balance - ? WHERE id = ?",
                [$transfer['fee'], $transfer['to_club_id']]
            );

            if (!empty($transfer['from_club_id'])) {
                $this->db->query(
                    "UPDATE clubs SET balance = balance + ? WHERE id = ?",
                    [$transfer['fee'], $transfer['from_club_id']]
                );
            }

            $this->db->insert('club_finance_ledger', [
                'club_id' => (int)$transfer['to_club_id'],
                'entry_type' => 'TRANSFER_OUT',
                'amount' => -1 * (int)$transfer['fee'],
                'description' => 'Transfer fee paid',
                'reference_type' => 'TRANSFER',
                'reference_id' => $transferId,
            ]);

            if (!empty($transfer['from_club_id'])) {
                $this->db->insert('club_finance_ledger', [
                    'club_id' => (int)$transfer['from_club_id'],
                    'entry_type' => 'TRANSFER_IN',
                    'amount' => (int)$transfer['fee'],
                    'description' => 'Transfer fee received',
                    'reference_type' => 'TRANSFER',
                    'reference_id' => $transferId,
                ]);
            }

            $this->update($transferId, ['status' => 'COMPLETED', 'completed_at' => date('Y-m-d H:i:s')]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function reject(int $transferId): bool {
        return $this->update($transferId, ['status' => 'REJECTED']);
    }
}
