<?php
// app/Models/TransferModel.php

class TransferModel extends BaseModel {
    protected string $table = 'transfers';

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS player_name, p.position, p.overall,
                    fc.name AS from_club_name, tc.name AS to_club_name
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
        $finance = new FinanceService($this->db);
        $this->db->beginTransaction();
        try {
            $transfer = $this->db->fetchOne(
                "SELECT * FROM transfers WHERE id = ? FOR UPDATE",
                [$transferId]
            );

            if (!$transfer || $transfer['status'] !== 'PENDING') {
                $this->db->rollBack();
                return false;
            }

            $this->db->query(
                "UPDATE transfers SET status = 'COMPLETED', completed_at = ? WHERE id = ? AND status = 'PENDING'",
                [date('Y-m-d H:i:s'), $transferId]
            );

            $this->db->query("UPDATE players SET club_id = ? WHERE id = ?", [$transfer['to_club_id'], $transfer['player_id']]);

            $outPost = $finance->postEntry((int)$transfer['to_club_id'], 'TRANSFER_OUT', -1 * (int)$transfer['fee'], 'Transfer fee paid', null, 'TRANSFER', $transferId, [], false);
            if (empty($outPost['ok'])) {
                $this->db->rollBack();
                return false;
            }

            if (!empty($transfer['from_club_id'])) {
                $inPost = $finance->postEntry((int)$transfer['from_club_id'], 'TRANSFER_IN', (int)$transfer['fee'], 'Transfer fee received', null, 'TRANSFER', $transferId, [], false);
                if (empty($inPost['ok'])) {
                    $this->db->rollBack();
                    return false;
                }
            }

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
