<?php
/**
 * Сессия и защита админки.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_name('billing_admin');
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function admin_user(): ?string {
    return $_SESSION['admin_user'] ?? null;
}

function admin_login(string $user, string $password): bool {
    $users = app_config()['admin']['users'] ?? [];
    $hash = $users[$user] ?? null;
    // password_verify по фиктивному хэшу, чтобы время ответа не выдавало существование логина
    $ok = password_verify($password, $hash ?? '$2y$10$abcdefghijklmnopqrstuuJ0zN7B5lX3cJ8bLq2w8a7r3qP6kYbEa');
    if ($ok && $hash !== null) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = $user;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return true;
    }
    return false;
}

function admin_logout(): void {
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
