<?php

require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/core/AuditLogger.php';

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'hostel_id' => $user['hostel_id'] ? (int) $user['hostel_id'] : null,
            'department' => $user['department'],
        ];
        $_SESSION['last_activity'] = time();

        $db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute([':id' => $user['id']]);
        AuditLogger::log((int) $user['id'], 'auth', 'login', 'User logged in');

        return true;
    }

    public static function check(): bool
    {
        self::enforceInactivity();

        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function hasRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/auth/login.php');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!self::hasRole($roles)) {
            http_response_code(403);
            exit('Access denied');
        }
    }

    public static function enforceInactivity(): void
    {
        if (!isset($_SESSION['user'])) {
            return;
        }

        if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
            self::logout();
            redirect('/auth/login.php?expired=1');
        }

        $_SESSION['last_activity'] = time();
    }

    public static function logout(): void
    {
        $uid = self::id();
        if ($uid) {
            AuditLogger::log($uid, 'auth', 'logout', 'User logged out');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

