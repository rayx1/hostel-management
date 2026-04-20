<?php

require_once BASE_PATH . '/config/db.php';

class Validator
{
    public static function indianMobile(string $value): bool
    {
        return (bool) preg_match('/^[6-9][0-9]{9}$/', $value);
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function duplicateInSystem(string $column, string $value, ?int $studentId = null, ?int $userId = null): bool
    {
        $db = Database::getInstance();

        $studentSql = "SELECT COUNT(*) FROM students WHERE {$column} = :value";
        $studentParams = [':value' => $value];
        if ($studentId !== null) {
            $studentSql .= ' AND id != :student_id';
            $studentParams[':student_id'] = $studentId;
        }

        $userSql = "SELECT COUNT(*) FROM users WHERE {$column} = :value";
        $userParams = [':value' => $value];
        if ($userId !== null) {
            $userSql .= ' AND id != :user_id';
            $userParams[':user_id'] = $userId;
        }

        $studentStmt = $db->prepare($studentSql);
        $studentStmt->execute($studentParams);

        $userStmt = $db->prepare($userSql);
        $userStmt->execute($userParams);

        return ((int) $studentStmt->fetchColumn() + (int) $userStmt->fetchColumn()) > 0;
    }
}

