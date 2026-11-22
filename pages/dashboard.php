<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
refresh_booking_statuses();

$month = date('Y-m');
$today = date('Y-m-d');

$pendapatanBulan = (float)qval("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE DATE_FORMAT(date, '%Y-%m') = ?", [$month]);
$pengeluaranBulan = (float)qval("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?", [$month]);
$sewaBulan = (int)qval("SELECT COUNT(*) FROM rentals WHERE DATE_FORMAT(rent_date, '%Y-%m') = ? AND status <> 'batal'", [$month]);
$labaBulan = $pendapatanBulan - $pengeluaranBulan;

$pendapatanTotal = (float)qval('SELECT COALESCE(SUM(amount),0) FROM incomes');
$pengeluaranTotal = (float)qval('SELECT COALESCE(SUM(amount),0) FROM expenses');

$dressStatus = [];
foreach (qall('SELECT status, COUNT(*) c FROM dresses GROUP BY status') as $row) {
    $dressStatus[$row['status']] = (int)$row['c'];
}
$totalKoleksi = array_sum($dressStatus);

$piutang = qone(
    "SELECT COALESCE(SUM(GREATEST(r.total - COALESCE(p.paid,0),0)),0) sisa, COUNT(*) jml
     FROM rentals r
     LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p ON p.rental_id = r.id
     WHERE r.status <> 'batal' AND r.total > COALESCE(p.paid,0)"
);
$utang = qone(
    "SELECT COALESCE(SUM(GREATEST(d.amount - COALESCE(e.paid,0),0)),0) sisa, COUNT(*) jml
     FROM debts d
     LEFT JOIN (SELECT utang_id, SUM(amount) paid FROM expenses WHERE utang_id IS NOT NULL GROUP BY utang_id) e ON e.utang_id = d.id
     WHERE d.amount > COALESCE(e.paid,0)"
);

$aktif = qall(
    "SELECT r.id, r.code, r.status, r.return_date, r.pickup_date, c.name AS customer
     FROM rentals r JOIN customers c ON c.id = r.customer_id
     WHERE r.status IN ('booking','disewa')
     ORDER BY r.return_date ASC
     LIMIT 10"
);

$recent = qall(
    "SELECT r.id, r.code, r.status, r.rent_date, r.total, c.name AS customer
     FROM rentals r JOIN customers c ON c.id = r.customer_id
     ORDER BY r.created_at DESC
     LIMIT 6"
);

page_start('Dashboard', 'dashboard.php');
render_flash();
?>
<div class="dash">
    <div class="dash-main">
        <div class="statgrid">
            <div class="stat rose">
                <span class="stat-label">Pendapatan <?= date('n') ?> <?= date('Y') ?></span>
                <span class="stat-value money"><?= rupiah($pendapatanBulan) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Pengeluaran <?= date('n') ?> <?= date('Y') ?></span>
                <span class="stat-value money"><?= rupiah($pengeluaranBulan) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Laba bersih <?= date('n') ?> <?= date('Y') ?></span>
                <span class="stat-value money"><?= rupiah($labaBulan) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Penyewaan <?= date('n') ?> <?= date('Y') ?></span>
                <span class="stat-value"><?= angka($sewaBulan) ?> transaksi</span>
            </div>
        </div>

        <section class="section">
            <h2>Jatuh tempo hari ini</h2>
            <p class="section-sub">Baju yang harus kembali hari ini, terlambat, dan booking yang harus diambil.</p>
            <?php if (!$aktif): ?>
                <?= empty_state('Tidak ada penyewaan yang sedang berjalan. Transaksi baru bisa dibuat dari menu Penyewaan.', 'Buat penyewaan', 'sewa_form.php') ?>
            <?php else: ?>
                <ul class="todaylist card" style="padding:8px 18px">
                    <?php foreach ($aktif as $r): ?>
                        <?php
                        $due = $r['return_date'] ?? '';
                        $late = rental_overdue($r);
                        $todayDue = $due === $today;
                        $pickupToday = $r['status'] === 'booking' && ($r['pickup_date'] ?? '') === $today;
                        if ($late) {
                            $note = '<span class="badge st-late">Terlambat ' . late_days($r) . ' hari</span>';
                        } elseif ($todayDue && $r['status'] === 'disewa') {
                            $note = '<span class="badge st-out">Kembali hari ini</span>';
                        } elseif ($pickupToday) {
                            $note = '<span class="badge st-book">Diambil hari ini</span>';
                        } else {
                            $note = badge($r['status']);
                        }
                        ?>
                        <li>
                            <span class="grow"><span class="cell-main"><?= e($r['customer']) ?></span>
                                <span class="cell-sub"><?= e($r['code']) ?> · kembali <?= tgl($r['return_date']) ?></span></span>
                            <?= $note ?>
                            <a class="btn btn-ghost btn-sm" href="pengembalian.php?rental=<?= (int)$r['id'] ?>">Proses</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>Penyewaan terbaru</h2>
            <?php if (!$recent): ?>
                <?= empty_state('Belum ada transaksi penyewaan.', 'Catat penyewaan pertama', 'sewa_form.php') ?>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Kode</th><th>Pelanggan</th><th class="hide-sm">Tanggal sewa</th><th>Status</th><th class="num">Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td class="cell-main table-code"><?= e($r["code"]) ?></td>
                                <td><?= e($r['customer']) ?></td>
                                <td class="hide-sm"><?= tgl($r["rent_date"]) ?></td>
                                <td><?= badge($r['status']) ?></td>
                                <td class="num"><?= rupiah($r['total']) ?></td>
                                <td><a class="btn btn-ghost btn-sm" href="sewa_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="dash-side">
        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Koleksi</h2>
            <?php if (!$totalKoleksi): ?>
                <p class="section-sub">Belum ada baju terdaftar.</p>
                <a class="btn btn-primary" href="koleksi.php?action=baru">Tambah koleksi pertama</a>
            <?php else: ?>
                <ul class="stat-list">
                    <li><span>Total koleksi</span><strong><?= angka($totalKoleksi) ?></strong></li>
                    <li><span><?= badge('tersedia') ?></span><strong><?= angka($dressStatus['tersedia'] ?? 0) ?></strong></li>
                    <li><span><?= badge('booking') ?></span><strong><?= angka($dressStatus['booking'] ?? 0) ?></strong></li>
                    <li><span><?= badge('disewa') ?></span><strong><?= angka($dressStatus['disewa'] ?? 0) ?></strong></li>
                    <li><span><?= badge('dicuci') ?></span><strong><?= angka($dressStatus['dicuci'] ?? 0) ?></strong></li>
                    <li><span><?= badge('perbaikan') ?></span><strong><?= angka($dressStatus['perbaikan'] ?? 0) ?></strong></li>
                    <li><span><?= badge('tidak_tersedia') ?></span><strong><?= angka($dressStatus['tidak_tersedia'] ?? 0) ?></strong></li>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Piutang &amp; utang</h2>
            <ul class="stat-list">
                <li>
                    <span>Piutang pelanggan<br><span class="cell-sub"><?= (int)$piutang['jml'] ?> transaksi belum lunas</span></span>
                    <strong><?= rupiah($piutang['sisa']) ?></strong>
                </li>
                <li>
                    <span>Sisa utang usaha<br><span class="cell-sub"><?= (int)$utang['jml'] ?> utang berjalan</span></span>
                    <strong><?= rupiah($utang['sisa']) ?></strong>
                </li>
            </ul>
            <div class="btn-row" style="margin-top:10px">
                <a class="btn btn-ghost btn-sm" href="piutang.php">Lihat piutang</a>
                <a class="btn btn-ghost btn-sm" href="utang.php">Lihat utang</a>
            </div>
        </div>

        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Sepanjang waktu</h2>
            <ul class="stat-list">
                <li><span>Total pendapatan</span><strong><?= rupiah($pendapatanTotal) ?></strong></li>
                <li><span>Total pengeluaran</span><strong><?= rupiah($pengeluaranTotal) ?></strong></li>
                <li><span>Laba bersih</span><strong class="totline"><?= rupiah($pendapatanTotal - $pengeluaranTotal) ?></strong></li>
            </ul>
        </div>
    </div>
</div>
<?php
page_end();
