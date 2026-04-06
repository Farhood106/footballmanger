<?php
// app/Core/Auth.php

class Auth {
    private static ?array $user = null;
    private const TOKEN_LENGTH = 32;
    private const SESSION_LIFETIME = 86400; // 24 hours

    public static function attempt(string $email, string $password): bool {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM `users` WHERE `email` = ?", [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::createSession($user);
        return true;
    }

    public static function register(string $email, string $username, string $password): int {
        $db = Database::getInstance();
        return $db->insert('users', [
            'email' => $email,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
        ]);
    }

    private static function createSession(array $user): void {
        // Regenerate session ID to prevent fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $db = Database::getInstance();
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
        $ipAddress = self::getClientIp();
        $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME);

        // Revoke old sessions (optional: keep last N sessions)
        $db->execute(
            "UPDATE `user_sessions` SET `is_revoked` = 1 WHERE `user_id` = ? AND `is_revoked` = 0",
            [$user['id']]
        );

        $db->execute(
            "INSERT INTO `user_sessions` (`user_id`, `token`, `user_agent`, `ip_address`, `expires_at`, `created_at`) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$user['id'], $token, $userAgent, $ipAddress, $expiresAt]
        );

        $_SESSION['auth_token'] = $token;
        $_SESSION['user_id'] = $user['id'];
        self::$user = $user;
    }

    public static function check(): bool {
        return self::user() !== null;
    }

    public static function user(): ?array {
        if (self::$user !== null) {
            return self::$user;
        }

        if (empty($_SESSION['auth_token'])) {
            return null;
        }

        $db = Database::getInstance();
        $session = $db->fetchOne(
            "SELECT u.* FROM `users` u 
             JOIN `user_sessions` s ON u.id = s.user_id 
             WHERE s.token = ? 
               AND s.is_revoked = 0 
               AND s.expires_at > NOW()
             ORDER BY s.created_at DESC 
             LIMIT 1",
            [$_SESSION['auth_token']]
        );

        if ($session) {
            self::$user = $session;
            unset(self::$user['password_hash']); // never expose password hash
            return self::$user;
        }

        // Invalid token, clear session
        self::clearSession();
        return null;
    }

    public static function id(): ?int {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function login(int $userId): void {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM `users` WHERE `id` = ?", [$userId]);

        if ($user) {
            self::createSession($user);
        }
    }

    public static function logout(): void {
        if (!empty($_SESSION['auth_token'])) {
            $db = Database::getInstance();
            $db->execute(
                "UPDATE `user_sessions` SET `is_revoked` = 1 WHERE `token` = ?",
                [$_SESSION['auth_token']]
            );
        }
        
        self::clearSession();
    }

    private static function clearSession(): void {
        unset($_SESSION['auth_token'], $_SESSION['user_id']);
        self::$user = null;
    }

    private static function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // اگر از proxy استفاده می‌شه
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
