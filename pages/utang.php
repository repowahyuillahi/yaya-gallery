<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
$action = $_GET['action'] ?? '';
$id = post_int('id') ?: (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'bayar') {
    csrf_check();
    $d = qone('SELECT * FROM debts WHERE id = ?', [post_int('debt_id')]);
    if (!$d) {
        flash_set('error', 'Utang tidak ditemukan.');
        redirect('utang.php');
    }
    $amount = post_amount('jumlah');
    if ($amount <= 0) {
        flash_set('error', 'Nominal pembayaran harus lebih dari nol.');
        redirect('utang.php');
    }
    $sisaSekarang = (float)$d['amount'] - debt_paid($d['id']);
    if ($amount > $sisaSekarang) {
        flash_set('error', 'Nominal melebihi sisa utang (' . rupiah($sisaSekarang) . ').');
        redirect('utang.php');
    }
    q(
        'INSERT INTO expenses (date, category, utang_id, description, amount, method)
         VALUES (:d, "pembayaran_utang", :did, :desc, :amt, :m)',
        [
            'd' => post_date('tanggal') ?: date('Y-m-d'), 'did' => (int)$d['id'],
            'desc' => 'Pembayaran utang ' . $d['creditor_name'], 'amt' => $amount,
            'm' => post_enum('method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'),
        ]
    );
    flash_set('ok', 'Pembayaran utang ' . rupiah($amount) . ' tercatat sebagai pengeluaran.');
    redirect('utang.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'hapus') {
    csrf_check();
    if (!can_delete()) {
        flash_set('error', 'Hanya admin yang dapat menghapus data.');
        redirect('utang.php');
    }
    $adaBayar = (int)qval('SELECT COUNT(*) FROM expenses WHERE utang_id = ?', [$id]);
    if ($adaBayar > 0) {
        flash_set('error', 'Utang ini sudah memiliki catatan pembayaran sehingga tidak bisa dihapus.');
        redirect('utang.php');
    }
    q('DELETE FROM debts WHERE id = ?', [$id]);
    flash_set('ok', 'Utang dihapus.');
    redirect('utang.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'simpan') {
    csrf_check();
    $creditor = post('creditor');
    $amount = post_amount('amount');
    if ($creditor === '' || $amount <= 0) {
        flash_set('error', 'Nama pemberi utang dan nominal utang wajib diisi.');
        redirect($id ? 'utang.php?action=edit&id=' . $id : 'utang.php?action=baru');
    }
    $data = [
        'creditor_name' => $creditor,
        'description'   => post('description') ?: null,
        'debt_date'     => post_date('debt_date') ?: date('Y-m-d'),
        'amount'        => $amount,
        'due_date'      => post_date('due_date'),
        'notes'         => post('notes') ?: null,
    ];
    if ($id) {
        q('UPDATE debts SET creditor_name=:creditor_name, description=:description, debt_date=:debt_date,
           amount=:amount, due_date=:due_date, notes=:notes WHERE id=:id', $data + ['id' => $id]);
        flash_set('ok', 'Utang ' . $creditor . ' diperbarui.');
    } else {
        q('INSERT INTO debts (creditor_name, description, debt_date, amount, due_date, notes)
          VALUES (:creditor_name, :description, :debt_date, :amount, :due_date, :notes)', $data);
        flash_set('ok', 'Utang ' . $creditor . ' tercatat.');
    }
    redirect('utang.php');
}

if ($action === 'edit' && $id) {
    $edit = qone('SELECT * FROM debts WHERE id = ?', [$id]);
    if (!$edit) {
        flash_set('error', 'Utang tidak ditemukan.');
        redirect('utang.php');
    }
}

$today = date('Y-m-d');
$debts = qall(
    "SELECT d.*, COALESCE(e.paid,0) AS paid
     FROM debts d
     LEFT JOIN (SELECT utang_id, SUM(amount) paid FROM expenses WHERE utang_id IS NOT NULL GROUP BY utang_id) e ON e.utang_id = d.id
     ORDER BY d.debt_date DESC"
);
$totalSisa = 0;
$totalPaid = 0;
$jatuhTempo = 0;
foreach ($debts as $d) {
    $sisa = (float)$d['amount'] - (float)$d['paid'];
    $totalSisa += max(0, $sisa);
    $totalPaid += (float)$d['paid'];
    if ($sisa > 0 && $d['due_date'] && $d['due_date'] < $today) {
        $jatuhTempo++;
    }
}

page_start('Utang Usaha', 'utang.php');
render_flash();
?>
<?php $v = $edit ?? []; $isEdit = !empty($edit); ?>
<?php if (!empty($edit) || $action === 'baru'): ?>
    <div class="card" style="max-width:760px">
        <h2 class="section" style="margin-top:0;padding-left:0"><?= $isEdit ? 'Edit utang' : 'Catat utang baru' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div>
                    <label for="creditor">Nama pemberi utang</label>
                    <input type="text" id="creditor" name="creditor" required value="<?= e($v['creditor_name'] ?? '') ?>" placeholder="mis. Toko Kain Melati">
                </div>
                <div>
                    <label for="amount">Nominal utang (Rp)</label>
                    <input type="text" id="amount" name="amount" inputmode="numeric" required value="<?= isset($edit) ? angka($edit['amount']) : '' ?>">
                </div>
                <div>
                    <label for="debt_date">Tanggal utang</label>
                    <input type="date" id="debt_date" name="debt_date" value="<?= e($v['debt_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label for="due_date">Jatuh tempo</label>
                    <input type="date" id="due_date" name="due_date" value="<?= e($v['due_date'] ?? '') ?>">
                </div>
                <div class="span-all">
                    <label for="description">Keterangan utang</label>
                    <input type="text" id="description" name="description" value="<?= e($v['description'] ?? '') ?>" placeholder="mis. pembelian 3 kebaya baru">
                </div>
                <div class="span-all">
                    <label for="notes">Catatan</label>
                    <textarea id="notes" name="notes"><?= e($v['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-ghost" href="utang.php">Batal</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="statgrid">
        <div class="stat rose">
            <span class="stat-label">Sisa utang berjalan</span>
            <span class="stat-value money"><?= rupiah($totalSisa) ?></span>
        </div>
        <div class="stat">
            <span class="stat-label">Sudah dibayar</span>
            <span class="stat-value money"><?= rupiah($totalPaid) ?></span>
        </div>
        <div class="stat">
            <span class="stat-label">Melewati jatuh tempo</span>
            <span class="stat-value"><?= $jatuhTempo ?> utang</span>
        </div>
    </div>

    <div class="pagebar section">
        <h2 style="margin:0">Daftar utang</h2>
        <span class="spacer"></span>
        <a class="btn btn-primary" href="utang.php?action=baru">Catat utang</a>
    </div>

    <?php if (!$debts): ?>
        <?= empty_state('Belum ada utang usaha tercatat.', 'Catat utang', 'utang.php?action=baru') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Pemberi utang</th><th class="hide-sm">Tanggal</th><th>Jatuh tempo</th><th>Status</th>
                    <th class="num">Nominal</th><th class="num hide-sm">Dibayar</th><th class="num">Sisa</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($debts as $d): ?>
                    <?php
                    $sisa = (float)$d['amount'] - (float)$d['paid'];
                    $lunas = $sisa <= 0;
                    $telat = !$lunas && $d['due_date'] && $d['due_date'] < $today;
                    if ($lunas) {
                        $stHtml = badge('lunas');
                    } elseif ($telat) {
                        $stHtml = '<span class="badge st-late">Melewati jatuh tempo</span>';
                    } else {
                        $stHtml = badge('belum_lunas');
                    }
                    ?>
                    <tr>
                        <td>
                            <span class="cell-main"><?= e($d['creditor_name']) ?></span>
                            <span class="cell-sub"><?= e($d['description'] ?: '') ?></span>
                        </td>
                        <td class="hide-sm"><?= tgl($d["debt_date"]) ?></td>
                        <td><?= tgl($d['due_date']) ?></td>
                        <td><?= $stHtml ?></td>
                        <td class="num"><?= rupiah($d['amount']) ?></td>
                        <td class="num hide-sm"><?= rupiah($d["paid"]) ?></td>
                        <td class="num"><?= rupiah(max(0, $sisa)) ?></td>
                        <td>
                            <span class="rowactions">
                                <?php if (!$lunas): ?>
                                    <details class="bayar-details">
                                        <summary class="btn btn-primary btn-sm" style="list-style:none">Bayar</summary>
                                        <form method="post" class="card bayar-form" style="padding:14px;margin-top:8px;min-width:240px">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="do" value="bayar">
                                            <input type="hidden" name="debt_id" value="<?= (int)$d['id'] ?>">
                                            <div>
                                                <label>Jumlah (Rp)</label>
                                                <input type="text" name="jumlah" inputmode="numeric" value="<?= angka($sisa) ?>">
                                            </div>
                                            <div style="margin-top:8px">
                                                <label>Tanggal</label>
                                                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div style="margin-top:8px">
                                                <label>Metode</label>
                                                <select name="method">
                                                    <option value="cash">Tunai</option>
                                                    <option value="transfer">Transfer</option>
                                                    <option value="qris">QRIS</option>
                                                    <option value="lainnya">Lainnya</option>
                                                </select>
                                            </div>
                                            <div class="form-actions" style="margin-top:10px">
                                                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                            </div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-sm" href="utang.php?action=edit&id=<?= (int)$d['id'] ?>">Edit</a>
                                <?php if (can_delete()): ?>
                                    <form method="post" data-confirm="Hapus utang <?= e($d['creditor_name']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint">Pembayaran utang otomatis tercatat sebagai pengeluaran dengan kategori Pembayaran utang.</p>
    <?php endif; ?>
<?php endif; ?>
<?php
page_end();
