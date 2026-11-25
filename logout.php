<?php
require_once __DIR__ . '/app/config.php';

// Logout hanya lewat POST + CSRF supaya tautan/permintaan GET tidak bisa mengakhiri sesi.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(u('login.php'));
}
csrf_check();
session_destroy();
redirect(u('login.php'));
