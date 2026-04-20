<?php

require_once BASE_PATH . '/core/Response.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function current_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $parts = explode('?', $uri);

    return $parts[0];
}

function old(string $key, string $default = ''): string
{
    return $_POST[$key] ?? $default;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;

        return null;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $msg;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

