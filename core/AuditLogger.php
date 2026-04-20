<?php

require_once BASE_PATH . '/config/db.php';

class AuditLogger
{
    public static function log(int $userId, string $module, string $action, string $description): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT INTO audit_logs (user_id, action, module, description, ip_address) VALUES (:user_id, :action, :module, :description, :ip)');
            $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':module' => $module,
                ':description' => $description,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            ]);
        } catch (Throwable $e) {
        }
    }
}

