<?php
require_once __DIR__ . '/config.php';

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['user_id'])) {
            $u = qone('SELECT id, name, username, role FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
            if (!$u) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $u;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash_set('error', 'Menu ini hanya untuk admin.');
        redirect('index.php');
    }
}

function can_delete(): bool
{
    return is_admin();
}
