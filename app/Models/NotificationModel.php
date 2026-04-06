<?php
// app/Models/NotificationModel.php

class NotificationModel extends BaseModel {
    protected string $table = 'notifications';

    public function getForUser(int $userId, bool $unreadOnly = false): array {
        $where = 'user_id = ?';
        $params = [$userId];
        if ($unreadOnly) { $where .= ' AND is_read = 0'; }

        return $this->findAll($where, $params, 'created_at DESC', 20);
    }

    public function send(int $userId, string $type, string $message, array $data = []): int {
        return $this->create([
            'user_id'    => $userId,
            'type'       => $type,
            'message'    => $message,
            'data'       => json_encode($data),
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function markRead(int $userId): void {
        $this->db->query(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?",
            [$userId]
        );
    }

    public function getUnreadCount(int $userId): int {
        return $this->count('user_id = ? AND is_read = 0', [$userId]);
    }
}
