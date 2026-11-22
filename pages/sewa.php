<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
refresh_booking_statuses();

$cari = trim((string)($_GET['cari'] ?? ''));
$fStatus = (string)($_GET['status'] ?? '');
$where = [];
$params = [];
if ($cari !== '') {
    $where[] = '(r.code LIKE :cari OR c.name LIKE :cari)';
    $params['cari'] = "%$cari%";
}
if (in_array($fStatus, ['booking', 'disewa', 'dikembalikan', 'batal'], true)) {
    $where[] = 'r.status = :st';
    $params['st'] = $fStatus;
}

$rentals = qall(
    "SELECT r.*, c.name AS customer, c.whatsapp, COALESCE(p.paid,0) AS paid
     FROM rentals r
     JOIN customers c ON c.id = r.customer_id
     LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p ON p.rental_id = r.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY r.rent_date DESC, r.id DESC",
    $params
);

page_start('Penyewaan', 'sewa.php');
render_flash();
?>
<div class="pagebar">
    <form method="get" class="btn-row growform">
        <input type="text" name="cari" aria-label="Cari transaksi berdasarkan kode atau nama pelanggan" value="<?= e($cari) ?>" class="searchfield">
        <select name="status" aria-label="Filter status transaksi" style="max-width:180px">
            <option value="">Semua status</option>
            <?php foreach (['booking', 'disewa', 'dikembalikan', 'batal'] as $st): ?>
                <option value="<?= $st ?>" <?= $fStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-ghost">Cari</button>
    </form>
    <span class="spacer"></span>
    <a class="btn btn-primary" href="sewa_form.php">Buat penyewaan</a>
</div>

<?php if (!$rentals): ?>
    <?= empty_state(
        $cari || $fStatus ? 'Tidak ada transaksi yang cocok dengan pencarian ini.' : 'Belum ada transaksi penyewaan. Buat transaksi pertama untuk mulai mencatat.',
        'Buat penyewaan',
        'sewa_form.php'
    ) ?>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Kode</th><th>Pelanggan</th><th>Sewa</th><th>Diambil</th><th>Kembali</th>
                <th>Status</th><th class="num">Total</th><th class="num">Sisa</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rentals as $r): ?>
                <?php $sisa = max(0, (float)$r['total'] - (float)$r['paid']); ?>
                <tr>
                    <td class="cell-main"><?= e($r['code']) ?></td>
                    <td><?= e($r['customer']) ?></td>
                    <td><?= tgl($r['rent_date']) ?></td>
                    <td><?= tgl($r['pickup_date']) ?></td>
                    <td><?= tgl($r['return_date']) ?></td>
                    <td>
                        <?php if (rental_overdue($r)): ?>
                            <span class="badge st-late">Terlambat <?= late_days($r) ?> hari</span>
                        <?php else: ?>
                            <?= badge($r['status']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= rupiah($r['total']) ?></td>
                    <td class="num"><?= $sisa > 0 ? rupiah($sisa) : '-' ?></td>
                    <td>
                        <span class="rowactions">
                            <a class="btn btn-ghost btn-sm" href="sewa_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
                            <?php if ($r['status'] !== 'batal'): ?>
                                <a class="btn btn-ghost btn-sm" href="invoice.php?id=<?= (int)$r['id'] ?>" target="_blank">Invoice</a>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
page_end();
