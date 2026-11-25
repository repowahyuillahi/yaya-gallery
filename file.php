<?php
require_once __DIR__ . '/app/auth.php';

require_login();

// File upload hanya boleh diakses lewat sini (butuh login), bukan URL langsung.
$path = (string)($_GET['f'] ?? '');
$base = realpath(app_dir('uploads'));
$full = realpath(app_dir($path));

if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR) || !is_file($full)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($full);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($full));
header('Cache-Control: private, max-age=3600');
readfile($full);
exit;
