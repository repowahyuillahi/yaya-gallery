<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = qone('SELECT * FROM users WHERE username = ? AND is_active = 1', [post('username')]);
    if ($u && password_verify(post('password'), $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        redirect('index.php');
    }
    $error = 'Username atau password salah.';
}
$s = settings();
$brand = $s['business_name'] ?: 'YAYA Gallery';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · <?= e($brand) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
<main class="login-card">
    <h1 class="login-brand"><?= e($brand) ?></h1>
    <p class="login-sub">Rental Management System</p>
    <?php if ($error): ?>
        <p class="login-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="login-field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="login-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Masuk</button>
    </form>
</main>
</body>
</html>
