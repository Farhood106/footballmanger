<?php
// app/Models/NotificationModel.php

class NotificationModel extends BaseModel {
    protected string $table = 'notifications';

    public function getForUser(int $userId, bool $unreadOnly = false): array {
        $sql = "SELECT *, body AS message FROM notifications WHERE user_id = ?";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 20";
        return $this->db->fetchAll($sql, $params);
    }

    public function send(int $userId, string $type, string $message, array $data = []): int {
        return $this->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => mb_substr($message, 0, 120),
            'body' => $message,
            'data' => json_encode($data),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function markRead(int $userId): void {
        $this->db->query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$userId]);
    }

    public function getUnreadCount(int $userId): int {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
        return (int)($row['c'] ?? 0);
    }
}
