<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Di hosting, isi lewat file app/config.local.php (lihat DEPLOY.md); tidak ikut repo.
$DB_HOST = '127.0.0.1';
$DB_NAME = 'yaya_gallery';
$DB_USER = 'root';
$DB_PASS = '';
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

try {
    $pdo = new PDO(
        'mysql:host=' . $DB_HOST . ';dbname=' . $DB_NAME . ';charset=utf8mb4',
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>YAYA Gallery</title></head>'
        . '<body style="font-family:system-ui,sans-serif;background:#FBF7F2;color:#3B3230;max-width:560px;margin:96px auto;line-height:1.6">'
        . '<h1 style="font-size:22px">Database belum siap</h1>'
        . '<p>Aplikasi tidak bisa terhubung ke MySQL. Periksa kredensial di <code>app/config.local.php</code>, lalu import skema dari <code>database/database.sql</code> lewat phpMyAdmin.</p></body></html>';
    exit;
}

// Jalur dasar URL aplikasi: posisi folder proyek relatif terhadap document root,
// dihitung dari lokasi file ini sehingga tautan benar dari folder mana pun.
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appRoot = str_replace('\\', '/', dirname(__DIR__));
define('APP_BASE', ($docRoot !== '' && str_starts_with($appRoot . '/', $docRoot . '/')) ? substr($appRoot, strlen($docRoot)) : '');

function u(string $path): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

function app_dir(string $path = ''): string
{
    return dirname(__DIR__) . ($path !== '' ? '/' . $path : '');
}

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function qall(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function qone(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function qval(string $sql, array $params = [])
{
    $row = q($sql, $params)->fetch(PDO::FETCH_NUM);
    return $row === false ? null : $row[0];
}

function settings(): array
{
    static $s = null;
    if ($s === null) {
        $s = qone('SELECT * FROM settings WHERE id = 1') ?: [];
    }
    return $s;
}

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function angka($n): string
{
    return number_format((float)$n, 0, ',', '.');
}

function bulan_indo(int $m): string
{
    static $b = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $b[$m];
}

function tgl(?string $d): string
{
    if (!$d) {
        return '-';
    }
    $t = strtotime($d);
    return date('j', $t) . ' ' . bulan_indo((int)date('n', $t)) . ' ' . date('Y', $t);
}

// Pengguna mengetik nominal dengan atau tanpa titik/koma, keduanya diterima.
function parse_amount(mixed $v): float
{
    $v = preg_replace('/[^0-9]/', '', (string)$v);
    return $v === '' ? 0.0 : (float)$v;
}

function post(string $k, string $default = ''): string
{
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $default;
}

function post_amount(string $k): float
{
    return parse_amount($_POST[$k] ?? '0');
}

function post_date(string $k): ?string
{
    $v = post($k);
    if ($v === '') {
        return null;
    }
    $t = strtotime($v);
    return $t ? date('Y-m-d', $t) : null;
}

function post_int(string $k): int
{
    return (int)($_POST[$k] ?? 0);
}

function post_enum(string $k, array $allowed, string $default): string
{
    $v = post($k);
    return in_array($v, $allowed, true) ? $v : $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(400);
        exit('Sesi tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// Jalur yang disimpan mulai dari "uploads/" sehingga bisa langsung dipakai u()
// untuk tampilan dan app_dir() untuk akses file.
function upload_image(string $field, string $subdir, ?string $old = null): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Upload gagal (kode ' . $f['error'] . '), coba file lain.');
        return null;
    }
    if ($f['size'] > 3 * 1024 * 1024) {
        flash_set('error', 'Ukuran file maksimal 3 MB.');
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($exts[$mime])) {
        flash_set('error', 'Format file harus JPG, PNG, atau WebP.');
        return null;
    }
    $dir = app_dir('uploads/' . $subdir);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $name = 'uploads/' . $subdir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
    if (!move_uploaded_file($f['tmp_name'], app_dir($name))) {
        flash_set('error', 'Gagal menyimpan file ke folder uploads.');
        return null;
    }
    if ($old && is_file(app_dir($old))) {
        unlink(app_dir($old));
    }
    return $name;
}

function next_code(string $table, string $prefix): string
{
    $next = ((int)qval("SELECT MAX(id) FROM `$table`")) + 1;
    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function rental_refresh_totals(int $rental_id): void
{
    $items = (float)qval('SELECT COALESCE(SUM(price),0) FROM rental_items WHERE rental_id = ?', [$rental_id]);
    $r = qone('SELECT discount, extra_fee, late_fee, damage_fee FROM rentals WHERE id = ?', [$rental_id]);
    $subtotal = $items - (float)$r['discount'] + (float)$r['extra_fee'];
    $total = $subtotal + (float)$r['late_fee'] + (float)$r['damage_fee'];
    q('UPDATE rentals SET subtotal = ?, total = ? WHERE id = ?', [$subtotal, $total, $rental_id]);
}

function rental_paid(int $rental_id): float
{
    return (float)qval('SELECT COALESCE(SUM(amount),0) FROM incomes WHERE rental_id = ?', [$rental_id]);
}

function debt_paid(int $debt_id): float
{
    return (float)qval('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE utang_id = ?', [$debt_id]);
}

// Booking berubah disewa saat tanggal ambil tiba; status baju mengikuti transaksinya.
function refresh_booking_statuses(): void
{
    q("UPDATE rentals SET status = 'disewa' WHERE status = 'booking' AND pickup_date IS NOT NULL AND pickup_date <= CURDATE()");
    q("UPDATE dresses d JOIN rental_items ri ON ri.dress_id = d.id JOIN rentals r ON r.id = ri.rental_id
       SET d.status = 'disewa' WHERE r.status = 'disewa' AND d.status = 'booking'");
}

function rental_overdue(array $r): bool
{
    return $r['status'] === 'disewa' && !empty($r['return_date']) && strtotime($r['return_date']) < strtotime(date('Y-m-d'));
}

function late_days(array $r): int
{
    if ($r['status'] !== 'disewa' || empty($r['return_date'])) {
        return 0;
    }
    $diff = strtotime(date('Y-m-d')) - strtotime($r['return_date']);
    return max(0, (int)floor($diff / 86400));
}

function wa_link(?string $phone, string $text): string
{
    $num = preg_replace('/\D/', '', (string)$phone);
    if ($num !== '' && str_starts_with($num, '0')) {
        $num = '62' . substr($num, 1);
    }
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}
