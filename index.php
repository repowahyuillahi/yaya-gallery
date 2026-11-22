<?php
require_once __DIR__ . '/app/config.php';

// Halaman produk: publik, tanpa login. Aplikasi sendiri ada di pages/dashboard.php.
$brand = settings()['business_name'] ?: 'YAYA Gallery';
$repoUrl = 'https://github.com/repowahyuillahi/yaya-gallery';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($brand) ?>: Sistem Manajemen Rental Baju dan Keuangan</title>
<meta name="description" content="Aplikasi rental baju: koleksi, penyewaan, invoice WhatsApp, pengembalian, piutang, utang, dan laporan keuangan dalam satu tempat.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= u("assets/css/style.css") ?>">
</head>
<body class="landing">

<header class="landing-nav">
    <span class="brand-name"><?= e($brand) ?></span>
    <nav aria-label="Navigasi halaman">
        <a class="textlink" href="#fitur">Fitur</a>
        <a class="textlink" href="#alur">Cara kerja</a>
        <a class="textlink" target="_blank" rel="noopener" href="<?= e($repoUrl) ?>">GitHub</a>
        <a class="btn btn-primary" href="<?= u("login.php") ?>">Masuk ke aplikasi</a>
    </nav>
</header>

<main>
    <section class="hero">
        <div class="hero-copy">
            <h1>Kelola rental baju dari koleksi sampai laporan, dalam satu aplikasi.</h1>
            <p>
                <?= e($brand) ?> mencatat penyewaan, invoice, piutang pelanggan, utang usaha,
                dan pengeluaran secara terhubung. Keuangan butik tinggal dibaca, bukan dirapikan
                ulang tiap akhir bulan.
            </p>
            <div class="btn-row">
                <a class="btn btn-primary" href="<?= u("login.php") ?>">Masuk ke aplikasi</a>
                <a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= e($repoUrl) ?>">Lihat kode sumber</a>
            </div>
            <p class="hero-note">Berjalan di PHP 8 dan MySQL, siap dipasang di XAMPP maupun hosting sendiri.</p>
        </div>
        <figure class="hero-shot">
            <img src="<?= u("assets/img/dashboard.png") ?>" alt="Tampilan dashboard aplikasi: ringkasan pendapatan, koleksi, dan penyewaan yang jatuh tempo"
                 width="1280" height="800" fetchpriority="high">
            <figcaption>Dashboard: angka hari ini, yang jatuh tempo, dan penyewaan terbaru.</figcaption>
        </figure>
    </section>

    <section class="section" id="fitur">
        <h2>Apa yang dicatatnya</h2>
        <ul class="feature-list">
            <li>
                <h3>Koleksi baju dengan foto</h3>
                <p>Kode otomatis, kategori, ukuran, harga sewa dan modal, serta status yang ikut berubah sendiri saat baju dibooking, disewa, dicuci, atau diperbaiki.</p>
            </li>
            <li>
                <h3>Transaksi penyewaan</h3>
                <p>Multi-baju per transaksi, deposit, diskon, ongkos, dan DP yang langsung tercatat sebagai pemasukan.</p>
            </li>
            <li>
                <h3>Invoice cetak dan WhatsApp</h3>
                <p>Invoice siap cetak atau simpan PDF, plus kirim rincian tagihan lewat wa.me dalam satu klik.</p>
            </li>
            <li>
                <h3>Pengembalian dan denda</h3>
                <p>Denda keterlambatan dihitung otomatis per hari, kondisi tiap baju dicatat, baju rusak masuk perbaikan dan yang kembali baik masuk antrean laundry.</p>
            </li>
            <li>
                <h3>Piutang dan utang usaha</h3>
                <p>Piutang diambil langsung dari sisa tagihan tiap transaksi; pembayaran utang otomatis masuk pengeluaran, jadi tidak ada angka dobel.</p>
            </li>
            <li>
                <h3>Pemasukan dan pengeluaran</h3>
                <p>DP, pelunasan, denda, dan biaya kerusakan tercatat sendiri; biaya operasional bisa ditempel bukti fotonya.</p>
            </li>
            <li>
                <h3>Laporan siap pakai</h3>
                <p>Laba rugi, pemasukan harian dan bulanan, koleksi paling sering disewa, keuntungan per baju, serta ekspor Excel (CSV) dan cetak PDF.</p>
            </li>
            <li>
                <h3>Admin dan karyawan</h3>
                <p>Karyawan mengurus operasional harian, admin mengunci pengaturan dan penghapusan data. Tampilan nyaman di HP dan komputer.</p>
            </li>
        </ul>
    </section>

    <section class="section" id="alur">
        <h2>Cara kerjanya dalam sehari</h2>
        <ol class="steps">
            <li>
                <h3>Catat koleksi</h3>
                <p>Foto, ukuran, dan harga tiap baju masuk katalog dengan kode otomatis.</p>
            </li>
            <li>
                <h3>Buat penyewaan</h3>
                <p>Pilih pelanggan dan baju, isi DP, kirim invoice ke WhatsApp pelanggan.</p>
            </li>
            <li>
                <h3>Terima kembali</h3>
                <p>Tandai kondisi baju, denda terhitung sendiri, deposit dipakai untuk pelunasan bila perlu.</p>
            </li>
            <li>
                <h3>Baca laporannya</h3>
                <p>Pendapatan, pengeluaran, piutang, dan laba bersih tersaji per hari, bulan, atau baju.</p>
            </li>
        </ol>
        <div class="shots">
            <figure>
                <img src="<?= u("assets/img/koleksi.png") ?>" alt="Katalog koleksi baju berisi kartu foto, harga sewa, dan status"
                     width="1280" height="800" loading="lazy">
                <figcaption>Katalog koleksi: status tiap baju selalu terbaca.</figcaption>
            </figure>
            <figure>
                <img src="<?= u("assets/img/invoice.png") ?>" alt="Halaman invoice dengan rincian baju, total, dan sisa pembayaran"
                     width="1280" height="800" loading="lazy">
                <figcaption>Invoice yang sama juga terkirim ke WhatsApp pelanggan.</figcaption>
            </figure>
        </div>
    </section>

    <section class="section closing">
        <h2>Siap dirapikan bersama</h2>
        <p class="closing-copy">
            Kode sumber terbuka dan datanya milik toko Anda sendiri, di server sendiri.
        </p>
        <div class="btn-row" style="justify-content:center">
            <a class="btn btn-primary" href="<?= u("login.php") ?>">Masuk ke aplikasi</a>
            <a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= e($repoUrl) ?>">GitHub</a>
        </div>
    </section>
</main>

<footer class="landing-foot">
    <span><?= e($brand) ?></span>
    <span>&copy; <?= date('Y') ?></span>
</footer>

<script src="<?= u("assets/js/app.js") ?>" defer></script>
</body>
</html>
