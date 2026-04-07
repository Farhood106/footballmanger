<?php
// app/Models/UserModel.php

class UserModel extends BaseModel {
    protected string $table = 'users';

    public function findByEmail(string $email): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ?", [$email]
        );
    }

    public function findByUsername(string $username): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ?", [$username]
        );
    }

    public function register(array $data): int {
        return $this->create([
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => $data['role'] ?? 'MANAGER',
            'game_role'     => $data['game_role'] ?? 'COACH',
            'created_at'    => date('Y-m-d H:i:s')
        ]);
    }

    public function verifyPassword(string $plain, ?string $hash): bool {
    if ($hash === null || $hash === '') {
        return false;
        }
        return password_verify($plain, $hash);
    }


    public function getClub(int $userId): ?array {
        return $this->db->fetchOne(
            "SELECT c.* FROM clubs c 
             WHERE c.manager_user_id = ? OR c.owner_user_id = ? OR c.user_id = ?
             LIMIT 1",
            [$userId, $userId, $userId]
        );
    }

    public function updateLastLogin(int $userId): void {
        $this->update($userId, ['updated_at' => date('Y-m-d H:i:s')]);
    }
}
