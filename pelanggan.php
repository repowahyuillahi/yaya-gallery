<?php
require_once __DIR__ . '/layout.php';

require_login();
$action = $_GET['action'] ?? '';
$id = post_int('id') ?: (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'hapus') {
    csrf_check();
    if (!can_delete()) {
        flash_set('error', 'Hanya admin yang dapat menghapus data.');
        redirect('pelanggan.php');
    }
    $c = qone('SELECT * FROM customers WHERE id = ?', [$id]);
    if (!$c) {
        flash_set('error', 'Pelanggan tidak ditemukan.');
        redirect('pelanggan.php');
    }
    $ada = (int)qval('SELECT COUNT(*) FROM rentals WHERE customer_id = ?', [$id]);
    if ($ada > 0) {
        flash_set('error', 'Pelanggan memiliki riwayat transaksi sehingga tidak bisa dihapus.');
        redirect('pelanggan.php');
    }
    q('DELETE FROM customers WHERE id = ?', [$id]);
    flash_set('ok', 'Pelanggan ' . $c['name'] . ' dihapus.');
    redirect('pelanggan.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'simpan') {
    csrf_check();
    $nama = post('nama');
    if ($nama === '') {
        flash_set('error', 'Nama pelanggan wajib diisi.');
        redirect($id ? 'pelanggan.php?action=edit&id=' . $id : 'pelanggan.php?action=baru');
    }
    $wa = preg_replace('/[^0-9+]/', '', post('whatsapp'));
    if ($id) {
        q('UPDATE customers SET name=:nama, whatsapp=:wa, address=:alamat, notes=:notes WHERE id=:id', [
            'nama' => $nama, 'wa' => $wa ?: null, 'alamat' => post('alamat') ?: null, 'notes' => post('notes') ?: null, 'id' => $id,
        ]);
        flash_set('ok', 'Pelanggan ' . $nama . ' diperbarui.');
    } else {
        q('INSERT INTO customers (name, whatsapp, address, notes) VALUES (:nama, :wa, :alamat, :notes)', [
            'nama' => $nama, 'wa' => $wa ?: null, 'alamat' => post('alamat') ?: null, 'notes' => post('notes') ?: null,
        ]);
        flash_set('ok', 'Pelanggan ' . $nama . ' tersimpan.');
    }
    redirect('pelanggan.php');
}

if ($action === 'edit' && $id) {
    $edit = qone('SELECT * FROM customers WHERE id = ?', [$id]);
    if (!$edit) {
        flash_set('error', 'Pelanggan tidak ditemukan.');
        redirect('pelanggan.php');
    }
}

// Detail pelanggan: profil + riwayat penyewaan.
$detail = null;
if ($action === 'detail' && $id) {
    $detail = qone('SELECT * FROM customers WHERE id = ?', [$id]);
    if ($detail) {
        $riwayat = qall(
            "SELECT r.*, COALESCE(p.paid,0) AS paid
             FROM rentals r
             LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p ON p.rental_id = r.id
             WHERE r.customer_id = :cid AND r.status <> 'batal'
             ORDER BY r.rent_date DESC",
            ['cid' => $id]
        );
        $totalTagihan = array_sum(array_column($riwayat, 'total'));
        $totalBayar = array_sum(array_column($riwayat, 'paid'));
        $totalSisa = 0;
        foreach ($riwayat as $r) {
            $totalSisa += max(0, (float)$r['total'] - (float)$r['paid']);
        }
    }
}

$cari = trim((string)($_GET['cari'] ?? ''));
$where = '';
$params = [];
if ($cari !== '') {
    $where = 'WHERE name LIKE :cari OR whatsapp LIKE :cari';
    $params['cari'] = "%$cari%";
}
$customers = qall(
    "SELECT c.*,
        (SELECT COUNT(*) FROM rentals r WHERE r.customer_id = c.id AND r.status <> 'batal') AS jml_sewa,
        (SELECT COALESCE(SUM(r2.total),0) FROM rentals r2 WHERE r2.customer_id = c.id AND r2.status <> 'batal') AS total_tagihan,
        (SELECT COALESCE(SUM(GREATEST(r3.total - COALESCE(p3.paid,0),0)),0)
           FROM rentals r3
           LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p3 ON p3.rental_id = r3.id
          WHERE r3.customer_id = c.id AND r3.status <> 'batal') AS total_utang
     FROM customers c $where
     ORDER BY c.name ASC",
    $params
);

page_start('Pelanggan', 'pelanggan.php');
render_flash();
?>
<?php $v = $edit ?? []; $isEdit = !empty($edit); ?>
<?php if (!empty($edit) || $action === 'baru'): ?>
    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0"><?= $isEdit ? 'Edit pelanggan' : 'Tambah pelanggan' ?></h2>
        <form method="post" action="pelanggan.php?action=simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div>
                    <label for="nama">Nama lengkap</label>
                    <input type="text" id="nama" name="nama" required value="<?= e($v['name'] ?? '') ?>" placeholder="mis. Siti Rahma">
                </div>
                <div>
                    <label for="whatsapp">Nomor WhatsApp</label>
                    <input type="text" id="whatsapp" name="whatsapp" inputmode="tel" value="<?= e($v['whatsapp'] ?? '') ?>" placeholder="mis. 081234567890">
                </div>
                <div class="span-all">
                    <label for="alamat">Alamat</label>
                    <input type="text" id="alamat" name="alamat" value="<?= e($v['address'] ?? '') ?>" placeholder="mis. Jl. Melati No. 12">
                </div>
                <div class="span-all">
                    <label for="notes">Catatan khusus</label>
                    <textarea id="notes" name="notes" placeholder="mis. sering sewa untuk kondangan"><?= e($v['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-ghost" href="pelanggan.php">Batal</a>
            </div>
        </form>
    </div>
<?php elseif (!empty($detail)): ?>
    <div class="grid-2">
        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Profil</h2>
            <dl class="kv">
                <div><dt>Nama</dt><dd class="strong"><?= e($detail['name']) ?></dd></div>
                <div><dt>WhatsApp</dt><dd><?= e($detail['whatsapp'] ?: '-') ?></dd></div>
                <div><dt>Alamat</dt><dd><?= e($detail['address'] ?: '-') ?></dd></div>
                <div><dt>Catatan</dt><dd><?= e($detail['notes'] ?: '-') ?></dd></div>
                <div><dt>Terdaftar sejak</dt><dd><?= tgl($detail['created_at']) ?></dd></div>
            </dl>
            <div class="btn-row" style="margin-top:14px">
                <a class="btn btn-ghost" href="pelanggan.php?action=edit&id=<?= (int)$detail['id'] ?>">Edit profil</a>
                <a class="btn btn-ghost" href="sewa_form.php?customer=<?= (int)$detail['id'] ?>">Buat penyewaan</a>
            </div>
        </div>
        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Rekap</h2>
            <ul class="stat-list">
                <li><span>Jumlah transaksi</span><strong><?= count($riwayat) ?></strong></li>
                <li><span>Total tagihan</span><strong><?= rupiah($totalTagihan) ?></strong></li>
                <li><span>Sudah dibayar</span><strong><?= rupiah($totalBayar) ?></strong></li>
                <li><span>Total utang pelanggan</span><strong class="totline"><?= rupiah($totalSisa) ?></strong></li>
            </ul>
        </div>
    </div>
    <section class="section">
        <h2>Riwayat penyewaan</h2>
        <?php if (!$riwayat): ?>
            <?= empty_state('Pelanggan ini belum pernah menyewa.', 'Buat penyewaan', 'sewa_form.php?customer=' . (int)$detail['id']) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Tanggal sewa</th><th>Kembali</th><th>Status</th><th class="num">Total</th><th class="num">Sisa</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($riwayat as $r): ?>
                        <tr>
                            <td class="cell-main"><?= e($r['code']) ?></td>
                            <td><?= tgl($r['rent_date']) ?></td>
                            <td><?= tgl($r['return_date']) ?></td>
                            <td><?= badge($r['status']) ?></td>
                            <td class="num"><?= rupiah($r['total']) ?></td>
                            <td class="num"><?= rupiah(max(0, (float)$r['total'] - (float)$r['paid'])) ?></td>
                            <td><a class="btn btn-ghost btn-sm" href="sewa_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <p style="margin-top:14px"><a class="textlink" href="pelanggan.php">Kembali ke daftar pelanggan</a></p>
<?php else: ?>
    <div class="pagebar">
        <form method="get" class="btn-row growform">
            <input type="text" name="cari" aria-label="Cari pelanggan berdasarkan nama atau nomor WhatsApp" value="<?= e($cari) ?>" class="searchfield">
            <button type="submit" class="btn btn-ghost">Cari</button>
        </form>
        <span class="spacer"></span>
        <a class="btn btn-primary" href="pelanggan.php?action=baru">Tambah pelanggan</a>
    </div>

    <?php if (!$customers): ?>
        <?= empty_state(
            $cari ? 'Tidak ada pelanggan yang cocok dengan pencarian ini.' : 'Database pelanggan masih kosong. Pelanggan bisa juga dibuat langsung saat mencatat penyewaan.',
            'Tambah pelanggan',
            'pelanggan.php?action=baru'
        ) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nama</th><th>WhatsApp</th><th class="num">Transaksi</th><th class="num">Total tagihan</th><th class="num">Utang pelanggan</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>
                            <span class="cell-main"><?= e($c['name']) ?></span>
                            <?php if ($c['address']): ?><span class="cell-sub"><?= e($c['address']) ?></span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['whatsapp']): ?>
                                <a class="textlink" target="_blank" rel="noopener"
                                   href="<?= e(wa_link($c['whatsapp'], 'Halo ' . $c['name'] . ', terima kasih sudah menyewa di ' . (settings()['business_name'] ?: 'YAYA Gallery') . '.')) ?>">Chat <?= e($c['whatsapp']) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= angka($c['jml_sewa']) ?></td>
                        <td class="num"><?= rupiah($c['total_tagihan']) ?></td>
                        <td class="num"><?= rupiah($c['total_utang']) ?></td>
                        <td>
                            <span class="rowactions">
                                <a class="btn btn-ghost btn-sm" href="pelanggan.php?action=detail&id=<?= (int)$c['id'] ?>">Riwayat</a>
                                <a class="btn btn-ghost btn-sm" href="pelanggan.php?action=edit&id=<?= (int)$c['id'] ?>">Edit</a>
                                <?php if (can_delete()): ?>
                                    <form method="post" action="pelanggan.php?action=hapus" data-confirm="Hapus pelanggan <?= e($c['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
    <?php endif; ?>
<?php endif; ?>
<?php
page_end();
