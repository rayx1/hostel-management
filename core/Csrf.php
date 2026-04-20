<?php

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function guard(?string $token): void
    {
        if (!self::validate($token)) {
            Response::json([
                'success' => false,
                'message' => 'Invalid CSRF token. Refresh and retry.',
            ], 419);
        }
    }
}

