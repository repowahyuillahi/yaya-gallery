<?php
require_once __DIR__ . '/auth.php';

// Set ikon digambar sendiri agar relevan dengan tiap menu, bukan satu pustaka bawaan.
function icon(string $name): string
{
    $paths = [
        'dashboard'   => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'koleksi'     => '<path d="M13 6.7a2 2 0 1 0-2-2"/><path d="M13 6.7v.9L4.6 14a1.25 1.25 0 0 0 .75 2.25h13.3A1.25 1.25 0 0 0 19.4 14Z"/>',
        'sewa'        => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3v4M16 3v4M3.5 10.5h17"/><path d="m9.8 15.4 1.7 1.7 3.4-3.4"/>',
        'pengembalian'=> '<path d="M9.5 14.5 4.5 9.5l5-5"/><path d="M4.5 9.5h9.5a5.75 5.75 0 0 1 0 11.5H11"/>',
        'pelanggan'   => '<circle cx="12" cy="8" r="3.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
        'piutang'     => '<path d="M6.5 3.5h7.5l4.5 4.5v12a.5.5 0 0 1-.5.5H6.5a.5.5 0 0 1-.5-.5V4a.5.5 0 0 1 .5-.5Z"/><path d="M14 3.5V8h4.5"/><path d="M9.75 14.75h4.5M12 12.5v4.5"/>',
        'utang'       => '<path d="M6.5 3.5h7.5l4.5 4.5v12a.5.5 0 0 1-.5.5H6.5a.5.5 0 0 1-.5-.5V4a.5.5 0 0 1 .5-.5Z"/><path d="M14 3.5V8h4.5"/><path d="M9.75 14.75h4.5"/>',
        'pemasukan'   => '<path d="M12 3.5v8.5M8.5 8.5 12 12l3.5-3.5"/><path d="M4 13.5v5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5v-5"/>',
        'pengeluaran' => '<path d="M12 12.5V4M8.5 7.5 12 4l3.5 3.5"/><path d="M4 13.5v5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5v-5"/>',
        'laporan'     => '<path d="M5 20v-8M12 20V6M19 20V9.5"/>',
        'pengaturan'  => '<path d="M4 7h8.5M17.5 7H20M4 17h2.5M11.5 17H20"/><circle cx="15" cy="7" r="2.2"/><circle cx="9" cy="17" r="2.2"/>',
        'pengguna'    => '<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0"/><circle cx="16.8" cy="9.5" r="2.4"/><path d="M14.8 19.5h5.7a4.6 4.6 0 0 0-4-4.6"/>',
        'logout'      => '<path d="M9.5 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20h4M15 8l4 4-4 4M19 12H8.5"/>',
        'cuci'        => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/><circle cx="12" cy="12" r="5"/><path d="M12 9.5v2.5l1.8 1.8"/>',
        'wa'          => '<path d="M12 3.5a8.5 8.5 0 0 0-7.3 12.8L3.5 20.5l4.3-1.1A8.5 8.5 0 1 0 12 3.5Z"/><path d="M9.2 8.7c.6 2.9 2.4 4.6 5.3 5.3l.9-1.6-1.9-.9-.8.7a5.3 5.3 0 0 1-1.7-1.7l.7-.8-.9-1.9Z"/>',
        'print'       => '<path d="M7 8V3.5h10V8"/><rect x="4" y="8" width="16" height="8" rx="1.5"/><path d="M7 13.5h10v7H7z"/>',
    ];
    $label = [
        'dashboard' => 'Dashboard', 'koleksi' => 'Koleksi', 'sewa' => 'Penyewaan', 'pengembalian' => 'Pengembalian',
        'pelanggan' => 'Pelanggan', 'piutang' => 'Piutang', 'utang' => 'Utang', 'pemasukan' => 'Pemasukan',
        'pengeluaran' => 'Pengeluaran', 'laporan' => 'Laporan', 'pengaturan' => 'Pengaturan', 'pengguna' => 'Pengguna',
        'logout' => 'Keluar', 'cuci' => 'Laundry', 'wa' => 'WhatsApp', 'print' => 'Cetak',
    ];
    return '<svg viewBox="0 0 24 24" class="ic" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function badge(string $status): string
{
    $map = [
        'tersedia'        => ['Tersedia', 'st-ok'],
        'booking'         => ['Booking', 'st-book'],
        'disewa'          => ['Disewa', 'st-out'],
        'terlambat'       => ['Terlambat', 'st-late'],
        'dicuci'          => ['Dicuci', 'st-wash'],
        'perbaikan'       => ['Perbaikan', 'st-fix'],
        'tidak_tersedia'  => ['Tidak tersedia', 'st-off'],
        'baik'            => ['Baik', 'st-ok'],
        'perlu_perbaikan' => ['Perlu perbaikan', 'st-fix'],
        'rusak'           => ['Rusak', 'st-late'],
        'hilang'          => ['Hilang', 'st-late'],
        'dikembalikan'    => ['Dikembalikan', 'st-ok'],
        'batal'           => ['Batal', 'st-off'],
        'dp'              => ['DP', 'st-book'],
        'pelunasan'       => ['Pelunasan', 'st-ok'],
        'denda'           => ['Denda', 'st-late'],
        'kerusakan'       => ['Kerusakan', 'st-late'],
        'lainnya'         => ['Lainnya', 'st-off'],
        'cash'            => ['Tunai', 'st-off'],
        'transfer'        => ['Transfer', 'st-wash'],
        'qris'            => ['QRIS', 'st-wash'],
        'belum_lunas'     => ['Belum lunas', 'st-late'],
        'sebagian'        => ['Sebagian', 'st-book'],
        'lunas'           => ['Lunas', 'st-ok'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'st-off'];
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

function empty_state(string $msg, ?string $ctaLabel = null, ?string $ctaHref = null): string
{
    $cta = '';
    if ($ctaLabel && $ctaHref) {
        $cta = '<p><a class="btn btn-primary" href="' . e($ctaHref) . '">' . e($ctaLabel) . '</a></p>';
    }
    return '<div class="empty"><p class="empty-msg">' . e($msg) . '</p>' . $cta . '</div>';
}

function nav_link(string $href, string $file, string $iconName, string $label, string $active): string
{
    $isActive = $active === $file;
    return '<a href="' . e($href) . '"' . ($isActive ? ' aria-current="page"' : '') . '>'
        . icon($iconName) . '<span>' . e($label) . '</span>'
        . '</a>';
}

function page_start(string $title, string $active): void
{
    $s = settings();
    $user = current_user();
    $brand = $s['business_name'] ?: 'YAYA Gallery';
    $groups = [
        'Operasional' => [
            ['index.php', 'index.php', 'dashboard', 'Dashboard'],
            ['koleksi.php', 'koleksi.php', 'koleksi', 'Koleksi Baju'],
            ['sewa.php', 'sewa.php', 'sewa', 'Penyewaan'],
            ['pengembalian.php', 'pengembalian.php', 'pengembalian', 'Pengembalian'],
            ['pelanggan.php', 'pelanggan.php', 'pelanggan', 'Pelanggan'],
        ],
        'Keuangan' => [
            ['piutang.php', 'piutang.php', 'piutang', 'Piutang'],
            ['utang.php', 'utang.php', 'utang', 'Utang Usaha'],
            ['pemasukan.php', 'pemasukan.php', 'pemasukan', 'Pemasukan'],
            ['pengeluaran.php', 'pengeluaran.php', 'pengeluaran', 'Pengeluaran'],
            ['laporan.php', 'laporan.php', 'laporan', 'Laporan'],
        ],
    ];
    if ($user && $user['role'] === 'admin') {
        $groups['Sistem'] = [
            ['pengaturan.php', 'pengaturan.php', 'pengaturan', 'Pengaturan'],
            ['users.php', 'users.php', 'pengguna', 'Pengguna'],
        ];
    }
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($brand) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Langsung ke isi</a>
<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Menu utama">
        <p class="brand">
            <?php if (!empty($s['logo'])): ?>
                <img src="<?= e($s['logo']) ?>" alt="" width="34" height="34">
            <?php endif; ?>
            <span class="brand-name"><?= e($brand) ?></span>
        </p>
        <p class="brand-sub">Rental Management System</p>
        <nav>
            <?php foreach ($groups as $groupName => $items): ?>
                <p class="nav-group"><?= e($groupName) ?></p>
                <?php foreach ($items as [$href, $file, $ic, $label]): ?>
                    <?= nav_link($href, $file, $ic, $label, $active) ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <a class="nav-logout" href="logout.php"><?= icon('logout') ?><span>Keluar</span></a>
    </aside>
    <div class="overlay" id="overlay" hidden></div>
    <div class="maincol">
        <header class="topbar">
            <button class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="sidebar">Menu</button>
            <h1><?= e($title) ?></h1>
            <div class="userchip">
                <span class="user-name"><?= e($user['name'] ?? '') ?></span>
                <span class="user-role"><?= e($user['role'] ?? '') ?></span>
            </div>
        </header>
        <main class="main" id="main">
<?php
}

function page_end(): void
{
    $s = settings();
    ?>
        </main>
        <footer class="appfoot"><span><?= e($s['business_name'] ?: 'YAYA Gallery') ?></span><span>&copy; <?= date('Y') ?></span></footer>
    </div>
</div>
</body>
</html>
<?php
}

function render_flash(): void
{
    foreach (flash_take() as $f) {
        $cls = $f['type'] === 'error' ? 'flash flash-error' : 'flash flash-ok';
        echo '<div class="' . $cls . '" role="status"><p>' . e($f['msg']) . '</p>'
            . '<button type="button" class="flash-x" aria-label="Tutup notifikasi">&times;</button></div>';
    }
}
