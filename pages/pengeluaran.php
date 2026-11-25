<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
$action = $_GET['action'] ?? '';
$id = post_int('id') ?: (int)($_GET['id'] ?? 0);

$kategori = [
    'pembelian_baju'   => 'Pembelian baju',
    'laundry'          => 'Laundry',
    'perbaikan_baju'   => 'Perbaikan baju',
    'aksesoris'        => 'Pembelian aksesoris',
    'packaging'        => 'Packaging',
    'transportasi'     => 'Transportasi',
    'listrik_internet' => 'Listrik dan internet',
    'gaji'             => 'Gaji karyawan',
    'sewa_tempat'      => 'Sewa tempat',
    'pembayaran_utang' => 'Pembayaran utang',
    'lainnya'          => 'Pengeluaran lainnya',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'hapus') {
    csrf_check();
    if (!can_delete()) {
        flash_set('error', 'Hanya admin yang dapat menghapus catatan pengeluaran.');
        redirect('pengeluaran.php');
    }
    $exp = qone('SELECT * FROM expenses WHERE id = ?', [$id]);
    if (!$exp) {
        flash_set('error', 'Catatan tidak ditemukan.');
        redirect('pengeluaran.php');
    }
    q('DELETE FROM expenses WHERE id = ?', [$id]);
    if ($exp['receipt_photo'] && is_file(app_dir($exp['receipt_photo']))) {
        unlink(app_dir($exp['receipt_photo']));
    }
    flash_set('ok', 'Pengeluaran ' . rupiah($exp['amount']) . ' dihapus.');
    redirect('pengeluaran.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'simpan') {
    csrf_check();
    $amount = post_amount('jumlah');
    if ($amount === null) {
        flash_set('error', 'Nominal pengeluaran tidak valid.');
        redirect($id ? 'pengeluaran.php?action=edit&id=' . $id : 'pengeluaran.php?action=baru');
    }
    if ($amount <= 0) {
        flash_set('error', 'Nominal pengeluaran harus lebih dari nol.');
        redirect($id ? 'pengeluaran.php?action=edit&id=' . $id : 'pengeluaran.php?action=baru');
    }
    $cat = post_enum('kategori', array_keys($kategori), 'lainnya');
    // Pembayaran utang tidak boleh diubah kategorinya; saldo utang bergantung padanya.
    if ($id && (int)qval('SELECT utang_id FROM expenses WHERE id = ?', [$id]) > 0) {
        $cat = 'pembayaran_utang';
    }
    $desc = post('keterangan') ?: ($kategori[$cat] ?? 'Pengeluaran');
    try {
        if ($id) {
            $old = (string)qval('SELECT receipt_photo FROM expenses WHERE id = ?', [$id]);
            $foto = upload_image('bukti', 'receipts', $old ?: null) ?? $old;
            q('UPDATE expenses SET date=:d, category=:c, description=:desc, amount=:amt, method=:m, receipt_photo=:f WHERE id=:id', [
                'd' => post_date('tanggal') ?: date('Y-m-d'), 'c' => $cat, 'desc' => $desc, 'amt' => $amount,
                'm' => post_enum('method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'), 'f' => $foto ?: null, 'id' => $id,
            ]);
            flash_set('ok', 'Pengeluaran diperbarui.');
        } else {
            $foto = upload_image('bukti', 'receipts');
            q('INSERT INTO expenses (date, category, description, amount, method, receipt_photo) VALUES (:d, :c, :desc, :amt, :m, :f)', [
                'd' => post_date('tanggal') ?: date('Y-m-d'), 'c' => $cat, 'desc' => $desc, 'amt' => $amount,
                'm' => post_enum('method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'), 'f' => $foto,
            ]);
            flash_set('ok', 'Pengeluaran ' . rupiah($amount) . ' tercatat.');
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
        redirect($id ? 'pengeluaran.php?action=edit&id=' . $id : 'pengeluaran.php?action=baru');
    }
    redirect('pengeluaran.php');
}

if ($action === 'edit' && $id) {
    $edit = qone('SELECT * FROM expenses WHERE id = ?', [$id]);
    if (!$edit) {
        flash_set('error', 'Catatan tidak ditemukan.');
        redirect('pengeluaran.php');
    }
}

$dari = (string)($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string)($_GET['sampai'] ?? date('Y-m-d'));
$fKat = (string)($_GET['kategori'] ?? '');
$where = ['e.date BETWEEN :dari AND :sampai'];
$params = ['dari' => $dari, 'sampai' => $sampai];
if (in_array($fKat, array_keys($kategori), true)) {
    $where[] = 'e.category = :kat';
    $params['kat'] = $fKat;
}
$rows = qall(
    'SELECT e.*, d.creditor_name FROM expenses e LEFT JOIN debts d ON d.id = e.utang_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY e.date DESC, e.id DESC',
    $params
);
$totalPeriode = array_sum(array_column($rows, 'amount'));
$perKat = [];
foreach ($rows as $r) {
    $perKat[$r['category']] = ($perKat[$r['category']] ?? 0) + (float)$r['amount'];
}
arsort($perKat);

page_start('Pengeluaran', 'pengeluaran.php');
render_flash();
?>
<?php $v = $edit ?? []; $isEdit = !empty($edit); ?>
<?php if (!empty($edit) || $action === 'baru'): ?>
    <div class="card" style="max-width:760px">
        <h2 class="section" style="margin-top:0;padding-left:0"><?= $isEdit ? 'Edit pengeluaran' : 'Catat pengeluaran' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div>
                    <label for="tanggal">Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal" value="<?= e($v['date'] ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" <?= !empty($edit['utang_id']) ? 'disabled' : '' ?>>
                        <?php foreach ($kategori as $k => $l): ?>
                            <option value="<?= $k ?>" <?= ($v['category'] ?? 'lainnya') === $k ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($edit['utang_id'])): ?>
                        <input type="hidden" name="kategori" value="pembayaran_utang">
                        <p class="hint">Ini pembayaran utang, kategorinya tidak bisa diubah.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="jumlah">Nominal (Rp)</label>
                    <input type="text" id="jumlah" name="jumlah" inputmode="numeric" required value="<?= isset($edit) ? angka($edit['amount']) : '' ?>" placeholder="mis. 85.000">
                </div>
                <div>
                    <label for="method">Metode pembayaran</label>
                    <select id="method" name="method">
                        <?php foreach (['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'lainnya' => 'Lainnya'] as $k => $l): ?>
                            <option value="<?= $k ?>" <?= ($v['method'] ?? 'cash') === $k ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="span-all">
                    <label for="keterangan">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" value="<?= e($v['description'] ?? '') ?>" placeholder="mis. laundry 4 set baju sewa">
                </div>
                <div class="span-all">
                    <label for="buktiInput">Bukti pembayaran (foto)</label>
                    <?php if (!empty($v['receipt_photo'])): ?>
                        <img src="<?= file_url($v['receipt_photo']) ?>" alt="Bukti pembayaran" width="120" height="120"
                             style="border-radius:var(--radius-s);object-fit:cover;margin-bottom:8px;display:block">
                    <?php endif; ?>
                    <input type="file" id="buktiInput" name="bukti" accept="image/jpeg,image/png,image/webp">
                    <p class="hint">JPG, PNG, atau WebP, maksimal 3 MB.</p>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-ghost" href="pengeluaran.php">Batal</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="statgrid">
        <div class="stat rose">
            <span class="stat-label">Total periode ini</span>
            <span class="stat-value money"><?= rupiah($totalPeriode) ?></span>
        </div>
        <?php foreach (array_slice($perKat, 0, 3, true) as $k => $t): ?>
            <div class="stat">
                <span class="stat-label"><?= e($kategori[$k]) ?></span>
                <span class="stat-value money"><?= rupiah($t) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="pagebar section">
        <form method="get" class="btn-row" style="flex:1;align-items:flex-end">
            <div>
                <label for="dari">Dari</label>
                <input type="date" id="dari" name="dari" value="<?= e($dari) ?>">
            </div>
            <div>
                <label for="sampai">Sampai</label>
                <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
            </div>
            <div>
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($kategori as $k => $l): ?>
                        <option value="<?= $k ?>" <?= $fKat === $k ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-ghost">Terapkan</button>
        </form>
        <span class="spacer"></span>
        <a class="btn btn-primary" href="pengeluaran.php?action=baru">Catat pengeluaran</a>
    </div>

    <?php if (!$rows): ?>
        <?= empty_state('Tidak ada pengeluaran pada periode ini.', 'Catat pengeluaran', 'pengeluaran.php?action=baru') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Metode</th><th>Bukti</th><th class="num">Nominal</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= tgl($r['date']) ?></td>
                        <td><?= e($kategori[$r['category']] ?? $r['category']) ?></td>
                        <td><?= e($r['description']) ?><?= $r['creditor_name'] ? '<span class="cell-sub">utang: ' . e($r['creditor_name']) . '</span>' : '' ?></td>
                        <td><?= badge($r['method']) ?></td>
                        <td><?= $r['receipt_photo'] ? '<a class="textlink" href="' . file_url($r['receipt_photo']) . '" target="_blank" rel="noopener">Lihat</a>' : '-' ?></td>
                        <td class="num"><?= rupiah($r['amount']) ?></td>
                        <td>
                            <span class="rowactions">
                                <a class="btn btn-ghost btn-sm" href="pengeluaran.php?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
                                <?php if (can_delete()): ?>
                                    <form method="post" data-confirm="Hapus pengeluaran ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="5">Total <?= tgl($dari) ?> sampai <?= tgl($sampai) ?></td><td class="num"><?= rupiah($totalPeriode) ?></td><td></td></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
page_end();
