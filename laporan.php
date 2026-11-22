<?php
require_once __DIR__ . '/layout.php';

require_login();

$dari = (string)($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string)($_GET['sampai'] ?? date('Y-m-d'));

$incomeRows = qall(
    "SELECT i.date, i.category, i.description, i.amount, r.code, c.name AS customer
     FROM incomes i
     LEFT JOIN rentals r ON r.id = i.rental_id
     LEFT JOIN customers c ON c.id = i.customer_id
     WHERE i.date BETWEEN :dari AND :sampai
     ORDER BY i.date ASC",
    ['dari' => $dari, 'sampai' => $sampai]
);
$expenseRows = qall(
    'SELECT e.date, e.category, e.description, e.amount
     FROM expenses e
     WHERE e.date BETWEEN :dari AND :sampai
     ORDER BY e.date ASC',
    ['dari' => $dari, 'sampai' => $sampai]
);
$totalIn = array_sum(array_column($incomeRows, 'amount'));
$totalOut = array_sum(array_column($expenseRows, 'amount'));
$laba = $totalIn - $totalOut;

$inKat = $outKat = [];
foreach ($incomeRows as $r) {
    $inKat[$r['category']] = ($inKat[$r['category']] ?? 0) + (float)$r['amount'];
}
foreach ($expenseRows as $r) {
    $outKat[$r['category']] = ($outKat[$r['category']] ?? 0) + (float)$r['amount'];
}

$harian = qall(
    'SELECT date, SUM(amount) total FROM incomes WHERE date BETWEEN :dari AND :sampai GROUP BY date ORDER BY date ASC',
    ['dari' => $dari, 'sampai' => $sampai]
);

// 12 bulan terakhir: pemasukan, pengeluaran, dan selisihnya berdampingan.
$mulai12 = date('Y-m-01', strtotime('-11 months'));
$bulanan = qall(
    "SELECT DATE_FORMAT(date, '%Y-%m') ym,
            SUM(CASE WHEN sumber = 'in' THEN amount ELSE 0 END) pemasukan,
            SUM(CASE WHEN sumber = 'out' THEN amount ELSE 0 END) pengeluaran
     FROM (
         SELECT date, amount, 'in' sumber FROM incomes
         UNION ALL
         SELECT date, amount, 'out' sumber FROM expenses
     ) t
     WHERE date >= :mulai
     GROUP BY ym ORDER BY ym DESC",
    ['mulai' => $mulai12]
);

$piutang = qall(
    "SELECT r.code, r.total, r.due_date, r.return_date, c.name AS customer, COALESCE(p.paid,0) AS paid
     FROM rentals r
     JOIN customers c ON c.id = r.customer_id
     LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p ON p.rental_id = r.id
     WHERE r.status <> 'batal' AND r.total > COALESCE(p.paid,0)
     ORDER BY COALESCE(r.due_date, r.return_date) ASC"
);
$utang = qall(
    "SELECT d.creditor_name, d.amount, d.due_date, COALESCE(e.paid,0) AS paid
     FROM debts d
     LEFT JOIN (SELECT utang_id, SUM(amount) paid FROM expenses WHERE utang_id IS NOT NULL GROUP BY utang_id) e ON e.utang_id = d.id
     WHERE d.amount > COALESCE(e.paid,0)
     ORDER BY d.due_date ASC"
);

$populer = qall(
    "SELECT d.id, d.code, d.name, COUNT(ri.id) jml, SUM(ri.price) pendapatan
     FROM rental_items ri
     JOIN dresses d ON d.id = ri.dress_id
     JOIN rentals r ON r.id = ri.rental_id
     WHERE r.status <> 'batal'
     GROUP BY d.id, d.code, d.name
     ORDER BY jml DESC, pendapatan DESC
     LIMIT 10"
);
$profit = qall(
    "SELECT d.code, d.name, d.buy_price, COALESCE(SUM(ri.price),0) revenue
     FROM dresses d
     LEFT JOIN rental_items ri ON ri.dress_id = d.id
     LEFT JOIN rentals r ON r.id = ri.rental_id AND r.status <> 'batal'
     GROUP BY d.id, d.code, d.name, d.buy_price
     HAVING revenue > 0
     ORDER BY revenue DESC"
);

$katLabel = ['dp' => 'DP', 'pelunasan' => 'Pelunasan', 'denda' => 'Denda', 'kerusakan' => 'Kerusakan', 'lainnya' => 'Lainnya'];
$outLabel = [
    'pembelian_baju' => 'Pembelian baju', 'laundry' => 'Laundry', 'perbaikan_baju' => 'Perbaikan baju',
    'aksesoris' => 'Pembelian aksesoris', 'packaging' => 'Packaging', 'transportasi' => 'Transportasi',
    'listrik_internet' => 'Listrik dan internet', 'gaji' => 'Gaji karyawan', 'sewa_tempat' => 'Sewa tempat',
    'pembayaran_utang' => 'Pembayaran utang', 'lainnya' => 'Pengeluaran lainnya',
];

page_start('Laporan', 'laporan.php');
render_flash();
?>
<div class="pagebar">
    <form method="get" class="btn-row" style="align-items:flex-end">
        <div>
            <label for="dari">Dari</label>
            <input type="date" id="dari" name="dari" value="<?= e($dari) ?>">
        </div>
        <div>
            <label for="sampai">Sampai</label>
            <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
        </div>
        <button type="submit" class="btn btn-ghost">Terapkan</button>
    </form>
    <span class="spacer"></span>
    <button type="button" class="btn btn-ghost" id="printBtn">Cetak / PDF</button>
</div>

<div class="cols" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Laba rugi <?= tgl($dari) ?> sampai <?= tgl($sampai) ?></h2>
        <ul class="stat-list">
            <?php foreach ($inKat as $k => $t): ?>
                <li><span>Pemasukan: <?= e($katLabel[$k]) ?></span><strong><?= rupiah($t) ?></strong></li>
            <?php endforeach; ?>
            <?php if (!$inKat): ?><li><span>Pemasukan</span><strong><?= rupiah(0) ?></strong></li><?php endif; ?>
            <li><span><strong>Total pemasukan</strong></span><strong><?= rupiah($totalIn) ?></strong></li>
        </ul>
        <ul class="stat-list" style="margin-top:12px">
            <?php foreach ($outKat as $k => $t): ?>
                <li><span>Pengeluaran: <?= e($outLabel[$k]) ?></span><strong><?= rupiah($t) ?></strong></li>
            <?php endforeach; ?>
            <?php if (!$outKat): ?><li><span>Pengeluaran</span><strong><?= rupiah(0) ?></strong></li><?php endif; ?>
            <li><span><strong>Total pengeluaran</strong></span><strong><?= rupiah($totalOut) ?></strong></li>
        </ul>
        <ul class="stat-list" style="margin-top:12px">
            <li><span>Laba bersih periode</span><strong class="totline" style="font-size:17px"><?= rupiah($laba) ?></strong></li>
        </ul>
        <p class="hint">Piutang berjalan saat ini <?= rupiah(array_sum(array_map(fn($p) => max(0, (float)$p['total'] - (float)$p['paid']), $piutang))) ?> dan sisa utang <?= rupiah(array_sum(array_map(fn($u) => max(0, (float)$u['amount'] - (float)$u['paid']), $utang))) ?>, dihitung terpisah dari laba periode ini.</p>
    </div>

    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Pemasukan harian</h2>
        <a class="btn btn-ghost btn-sm no-print" href="laporan_export.php?type=harian&amp;dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>">Unduh Excel (CSV)</a>
        <?php if (!$harian): ?>
            <p class="section-sub">Tidak ada pemasukan pada periode ini.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="items-table">
                    <thead><tr><th>Tanggal</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($harian as $h): ?>
                        <tr><td><?= tgl($h['date']) ?></td><td class="num"><?= rupiah($h['total']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="num"><?= rupiah($totalIn) ?></td></tr></tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<section class="section">
    <h2>Per bulan (12 bulan terakhir)</h2>
    <a class="btn btn-ghost btn-sm no-print" href="laporan_export.php?type=bulanan">Unduh Excel (CSV)</a>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Bulan</th><th class="num">Pemasukan</th><th class="num">Pengeluaran</th><th class="num">Selisih</th></tr></thead>
            <tbody>
            <?php foreach ($bulanan as $b): ?>
                <tr>
                    <td><?= bulan_indo((int)substr($b['ym'], 5)) . ' ' . substr($b['ym'], 0, 4) ?></td>
                    <td class="num"><?= rupiah($b['pemasukan']) ?></td>
                    <td class="num"><?= rupiah($b['pengeluaran']) ?></td>
                    <td class="num"><?= rupiah($b['pemasukan'] - $b['pengeluaran']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$bulanan): ?><tr><td colspan="4">Belum ada data transaksi.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="cols section" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Koleksi paling sering disewa</h2>
        <a class="btn btn-ghost btn-sm no-print" href="laporan_export.php?type=populer">Unduh Excel (CSV)</a>
        <?php if (!$populer): ?>
            <p class="section-sub">Belum ada transaksi penyewaan.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="items-table">
                    <thead><tr><th>Baju</th><th class="num">Disewa</th><th class="num">Pendapatan</th></tr></thead>
                    <tbody>
                    <?php foreach ($populer as $d): ?>
                        <tr>
                            <td><span class="cell-main"><?= e($d['name']) ?></span><span class="cell-sub"><?= e($d['code']) ?></span></td>
                            <td class="num"><?= angka($d['jml']) ?>×</td>
                            <td class="num"><?= rupiah($d['pendapatan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Keuntungan setiap baju</h2>
        <a class="btn btn-ghost btn-sm no-print" href="laporan_export.php?type=profit">Unduh Excel (CSV)</a>
        <?php if (!$profit): ?>
            <p class="section-sub">Belum ada baju yang menghasilkan pendapatan.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="items-table">
                    <thead><tr><th>Baju</th><th class="num">Pendapatan sewa</th><th class="num">Modal</th><th class="num">Selisih</th></tr></thead>
                    <tbody>
                    <?php foreach ($profit as $d): ?>
                        <tr>
                            <td><span class="cell-main"><?= e($d['name']) ?></span><span class="cell-sub"><?= e($d['code']) ?></span></td>
                            <td class="num"><?= rupiah($d['revenue']) ?></td>
                            <td class="num"><?= rupiah($d['buy_price']) ?></td>
                            <td class="num"><?= rupiah($d['revenue'] - (float)$d['buy_price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint">Modal dihitung sekali per baju; selisih mengabaikan biaya laundry dan perbaikan yang ada di pengeluaran.</p>
        <?php endif; ?>
    </div>
</div>

<div class="cols section" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Laporan piutang</h2>
        <a class="btn btn-ghost btn-sm no-print" href="laporan_export.php?type=piutang">Unduh Excel (CSV)</a>
        <?php if (!$piutang): ?>
            <p class="section-sub">Tidak ada piutang berjalan.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="items-table">
                    <thead><tr><th>Kode</th><th>Pelanggan</th><th>Jatuh tempo</th><th class="num">Sisa</th></tr></thead>
                    <tbody>
                    <?php foreach ($piutang as $p): ?>
                        <tr>
                            <td><?= e($p['code']) ?></td>
                            <td><?= e($p['customer']) ?></td>
                            <td><?= tgl($p['due_date'] ?: $p['return_date']) ?></td>
                            <td class="num"><?= rupiah(max(0, (float)$p['total'] - (float)$p['paid'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Laporan utang</h2>
        <a class="btn btn-ghost btn-sm no-print" href="laporan_export.php?type=utang">Unduh Excel (CSV)</a>
        <?php if (!$utang): ?>
            <p class="section-sub">Tidak ada utang berjalan.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="items-table">
                    <thead><tr><th>Pemberi utang</th><th>Jatuh tempo</th><th class="num">Sisa</th></tr></thead>
                    <tbody>
                    <?php foreach ($utang as $u): ?>
                        <tr>
                            <td><?= e($u['creditor_name']) ?></td>
                            <td><?= tgl($u['due_date']) ?></td>
                            <td class="num"><?= rupiah(max(0, (float)$u['amount'] - (float)$u['paid'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
page_end();
