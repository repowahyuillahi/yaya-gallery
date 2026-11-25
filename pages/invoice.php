<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
$id = (int)($_GET['id'] ?? 0);
$rental = qone(
    'SELECT r.*, c.name AS customer, c.whatsapp, c.address
     FROM rentals r JOIN customers c ON c.id = r.customer_id
     WHERE r.id = ?',
    [$id]
);
if (!$rental) {
    flash_set('error', 'Transaksi tidak ditemukan.');
    redirect('sewa.php');
}
$items = qall(
    'SELECT ri.*, d.code, d.name, d.size
     FROM rental_items ri JOIN dresses d ON d.id = ri.dress_id
     WHERE ri.rental_id = ? ORDER BY ri.id ASC',
    [$id]
);
$payments = qall('SELECT * FROM incomes WHERE rental_id = ? ORDER BY date ASC, id ASC', [$id]);
$paid = array_sum(array_column($payments, 'amount'));
$sisa = (float)$rental['total'] - $paid;

$s = settings();
$brand = $s['business_name'] ?: 'YAYA Gallery';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($rental['code']) ?> · <?= e($brand) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= u("assets/css/style.css") ?>">
</head>
<body class="invoice-body">
<div class="invoice-actions no-print" style="margin-top:20px">
    <button type="button" class="btn btn-primary" id="printBtn"><?= icon('print') ?> Cetak / simpan PDF</button>
    <?php if ($rental['whatsapp']): ?>
        <a class="btn btn-ghost" href="sewa_detail.php?id=<?= (int)$id ?>">Kembali ke detail</a>
    <?php endif; ?>
</div>
<main class="invoice-page">
    <header class="invoice-head">
        <div>
            <h1 class="invoice-brand"><?= e($brand) ?></h1>
            <p style="margin:2px 0 0;font-size:13px;color:var(--muted)">
                <?= e($s['address'] ?: '') ?><?= $s['address'] && $s['phone'] ? ' · ' : '' ?><?= e($s['phone'] ?: '') ?>
            </p>
        </div>
        <div class="invoice-meta">
            <strong style="color:var(--ink)">Invoice <?= e($rental['code']) ?></strong><br>
            Dibuat <?= tgl($rental['rent_date']) ?><br>
            Jatuh tempo <?= tgl($rental['due_date']) ?>
        </div>
    </header>

    <h2 class="invoice-title">Untuk pelanggan</h2>
    <div class="invoice-grid">
        <div>
            <strong><?= e($rental['customer']) ?></strong><br>
            <?= e($rental['whatsapp'] ?: '-') ?><br>
            <?= e($rental['address'] ?: '') ?>
        </div>
        <div>
            Pengambilan: <?= tgl($rental['pickup_date']) ?><br>
            Pengembalian: <?= tgl($rental['return_date']) ?><br>
            Status: <?= e(str_replace('_', ' ', $rental['status'])) ?>
        </div>
    </div>

    <div class="table-wrap">
        <table class="items-table">
            <thead><tr><th>Baju</th><th class="num">Harga</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <span class="cell-main"><?= e($it['name']) ?></span>
                        <span class="cell-sub"><?= e($it['code']) ?> · <?= e($it['size'] ?: '-') ?></span>
                    </td>
                    <td class="num"><?= rupiah($it['price']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr><td>Subtotal</td><td class="num"><?= rupiah($rental['subtotal']) ?></td></tr>
            <?php if ($rental['discount'] > 0): ?><tr><td>Diskon</td><td class="num">- <?= rupiah($rental['discount']) ?></td></tr><?php endif; ?>
            <?php if ($rental['extra_fee'] > 0): ?><tr><td>Ongkos tambahan</td><td class="num"><?= rupiah($rental['extra_fee']) ?></td></tr><?php endif; ?>
            <?php if ($rental['late_fee'] > 0): ?><tr><td>Denda keterlambatan</td><td class="num"><?= rupiah($rental['late_fee']) ?></td></tr><?php endif; ?>
            <?php if ($rental['damage_fee'] > 0): ?><tr><td>Biaya kerusakan</td><td class="num"><?= rupiah($rental['damage_fee']) ?></td></tr><?php endif; ?>
            <tr><td>Total</td><td class="num"><?= rupiah($rental['total']) ?></td></tr>
            <tr><td>Sudah dibayar</td><td class="num"><?= rupiah($paid) ?></td></tr>
            <tr><td>Sisa</td><td class="num"><?= rupiah(max(0, $sisa)) ?></td></tr>
            </tfoot>
        </table>
    </div>

    <?php if ($rental['deposit'] > 0): ?>
        <p class="hint" style="margin-top:12px">Deposit <?= rupiah($rental['deposit']) ?> <?= $rental['deposit_used'] ? 'sudah dipakai untuk pembayaran.' : 'dikembalikan penuh saat seluruh baju kembali dalam kondisi baik.' ?></p>
    <?php endif; ?>

    <p class="invoice-foot"><?= e($s['invoice_notes'] ?: 'Terima kasih telah menyewa di ' . $brand . '.') ?></p>
</main>
<script src="<?= u("assets/js/app.js") ?>" defer></script>
</body>
</html>
