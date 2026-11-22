<?php
require_once __DIR__ . '/../app/layout.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'hapus') {
    csrf_check();
    if (!can_delete()) {
        flash_set('error', 'Hanya admin yang dapat menghapus catatan pemasukan.');
        redirect('pemasukan.php');
    }
    $inc = qone('SELECT * FROM incomes WHERE id = ?', [post_int('id')]);
    if (!$inc) {
        flash_set('error', 'Catatan tidak ditemukan.');
        redirect('pemasukan.php');
    }
    q('DELETE FROM incomes WHERE id = ?', [$inc['id']]);
    flash_set('ok', 'Pemasukan ' . rupiah($inc['amount']) . ' dihapus. Jika ini pembayaran sewa, sisa tagihan transaksi terkait akan bertambah kembali.');
    redirect('pemasukan.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'lainnya') {
    csrf_check();
    $amount = post_amount('jumlah');
    if ($amount <= 0) {
        flash_set('error', 'Nominal harus lebih dari nol.');
        redirect('pemasukan.php');
    }
    q(
        'INSERT INTO incomes (date, category, description, amount, method)
         VALUES (:d, "lainnya", :desc, :amt, :m)',
        [
            'd' => post_date('tanggal') ?: date('Y-m-d'),
            'desc' => post('keterangan') ?: 'Pendapatan lainnya',
            'amt' => $amount,
            'm' => post_enum('method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'),
        ]
    );
    flash_set('ok', 'Pendapatan lainnya ' . rupiah($amount) . ' tercatat.');
    redirect('pemasukan.php');
}

$dari = (string)($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string)($_GET['sampai'] ?? date('Y-m-d'));
$fKat = (string)($_GET['kategori'] ?? '');
$where = ['i.date BETWEEN :dari AND :sampai'];
$params = ['dari' => $dari, 'sampai' => $sampai];
if (in_array($fKat, ['dp', 'pelunasan', 'denda', 'kerusakan', 'lainnya'], true)) {
    $where[] = 'i.category = :kat';
    $params['kat'] = $fKat;
}
$rows = qall(
    "SELECT i.*, r.code, c.name AS customer
     FROM incomes i
     LEFT JOIN rentals r ON r.id = i.rental_id
     LEFT JOIN customers c ON c.id = i.customer_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY i.date DESC, i.id DESC",
    $params
);
$totalPeriode = array_sum(array_column($rows, 'amount'));
$perKat = [];
foreach ($rows as $r) {
    $perKat[$r['category']] = ($perKat[$r['category']] ?? 0) + (float)$r['amount'];
}
$katLabel = ['dp' => 'DP', 'pelunasan' => 'Pelunasan', 'denda' => 'Denda', 'kerusakan' => 'Kerusakan', 'lainnya' => 'Lainnya'];

page_start('Pemasukan', 'pemasukan.php');
render_flash();
?>
<div class="statgrid">
    <div class="stat rose">
        <span class="stat-label">Total periode ini</span>
        <span class="stat-value money"><?= rupiah($totalPeriode) ?></span>
    </div>
    <?php foreach (['dp', 'pelunasan', 'denda', 'kerusakan'] as $k): ?>
        <div class="stat">
            <span class="stat-label"><?= $katLabel[$k] ?></span>
            <span class="stat-value money"><?= rupiah($perKat[$k] ?? 0) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<details class="section" style="margin-top:22px">
    <summary class="btn btn-ghost" style="list-style:none">Catat pendapatan lainnya</summary>
    <form method="post" class="card" style="margin-top:12px;max-width:760px">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="lainnya">
        <div class="form-grid">
            <div>
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label for="jumlah">Jumlah (Rp)</label>
                <input type="text" id="jumlah" name="jumlah" inputmode="numeric" placeholder="mis. 50.000">
            </div>
            <div>
                <label for="method">Metode</label>
                <select id="method" name="method">
                    <option value="cash">Tunai</option>
                    <option value="transfer">Transfer</option>
                    <option value="qris">QRIS</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>
            <div class="span-all">
                <label for="keterangan">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" placeholder="mis. penjualan aksesoris bekas">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</details>

<section class="section">
    <h2>Daftar pemasukan</h2>
    <form method="get" class="btn-row pagebar">
        <div>
            <label for="dari">Dari</label>
            <input type="date" id="dari" name="dari" value="<?= e($dari) ?>">
        </div>
        <div>
            <label for="sampai">Sampai</label>
            <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
        </div>
        <div>
            <label for="kategori">Jenis</label>
            <select id="kategori" name="kategori">
                <option value="">Semua jenis</option>
                <?php foreach ($katLabel as $k => $l): ?>
                    <option value="<?= $k ?>" <?= $fKat === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-ghost" style="align-self:flex-end">Terapkan</button>
    </form>

    <?php if (!$rows): ?>
        <?= empty_state('Tidak ada pemasukan pada periode ini. Pendapatan sewa tercatat otomatis dari DP, pelunasan, denda, dan biaya kerusakan di tiap transaksi.', 'Buat penyewaan', 'sewa_form.php') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Transaksi</th><th>Metode</th><th class="num">Jumlah</th><?php if (can_delete()): ?><th></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= tgl($r['date']) ?></td>
                        <td><?= badge($r['category']) ?></td>
                        <td><?= e($r['description']) ?></td>
                        <td><?= $r['code'] ? '<a class="textlink" href="sewa_detail.php?id=' . (int)$r['rental_id'] . '">' . e($r['code']) . '</a><span class="cell-sub">' . e($r['customer']) . '</span>' : '-' ?></td>
                        <td><?= badge($r['method']) ?></td>
                        <td class="num"><?= rupiah($r['amount']) ?></td>
                        <?php if (can_delete()): ?>
                            <td>
                                <form method="post" data-confirm="Hapus catatan pemasukan ini?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="back" value="<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'kategori' => $fKat])) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="5">Total <?= tgl($dari) ?> sampai <?= tgl($sampai) ?></td><td class="num"><?= rupiah($totalPeriode) ?></td><?php if (can_delete()): ?><td></td><?php endif; ?></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
page_end();
