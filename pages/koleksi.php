<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
$action = $_GET['action'] ?? '';
$id = post_int('id') ?: (int)($_GET['id'] ?? 0);
$kategoriList = ['dress', 'kebaya', 'gaun', 'lainnya'];
$kategoriLabel = ['dress' => 'Dress', 'kebaya' => 'Kebaya', 'gaun' => 'Gaun', 'lainnya' => 'Lainnya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'hapus') {
    csrf_check();
    if (!can_delete()) {
        flash_set('error', 'Hanya admin yang dapat menghapus data.');
        redirect('koleksi.php');
    }
    $dress = qone('SELECT * FROM dresses WHERE id = ?', [$id]);
    if (!$dress) {
        flash_set('error', 'Baju tidak ditemukan.');
        redirect('koleksi.php');
    }
    $dipakai = (int)qval('SELECT COUNT(*) FROM rental_items WHERE dress_id = ?', [$id]);
    if ($dipakai > 0) {
        flash_set('error', 'Baju pernah masuk transaksi sewa sehingga tidak bisa dihapus. Ubah statusnya menjadi Tidak tersedia.');
        redirect('koleksi.php');
    }
    q('DELETE FROM dresses WHERE id = ?', [$id]);
    if ($dress['photo'] && is_file(app_dir($dress['photo']))) {
        unlink(app_dir($dress['photo']));
    }
    flash_set('ok', 'Baju ' . $dress['code'] . ' dihapus.');
    redirect('koleksi.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'simpan')) {
    csrf_check();
    $nama = post('nama');
    if ($nama === '') {
        flash_set('error', 'Nama baju wajib diisi.');
        redirect($id ? 'koleksi.php?action=edit&id=' . $id : 'koleksi.php?action=baru');
    }
    $hargaSewa = post_amount('harga_sewa');
    $hargaBeli = post_amount('harga_beli');
    if ($hargaSewa === null || $hargaBeli === null) {
        flash_set('error', 'Nominal harga sewa atau harga beli tidak valid.');
        redirect($id ? 'koleksi.php?action=edit&id=' . $id : 'koleksi.php?action=baru');
    }
    $data = [
        'name'            => $nama,
        'category'        => post_enum('kategori', $kategoriList, 'dress'),
        'size'            => post('size') ?: null,
        'color'           => post('warna') ?: null,
        'rent_price'      => $hargaSewa,
        'buy_price'       => $hargaBeli,
        'purchase_date'   => post_date('tanggal_beli'),
        'item_condition'  => post_enum('kondisi', ['baik', 'perlu_perbaikan', 'rusak'], 'baik'),
        'status'          => post_enum('status', ['tersedia', 'dicuci', 'perbaikan', 'tidak_tersedia'], 'tersedia'),
        'notes'           => post('notes') ?: null,
    ];
    $statusSekarang = $id ? (string)qval('SELECT status FROM dresses WHERE id = ?', [$id]) : '';
    if (in_array($statusSekarang, ['booking', 'disewa'], true)) {
        $data['status'] = $statusSekarang; // status transaksi hanya berubah lewat transaksi
    }
    try {
        if ($id) {
            $old = (string)qval('SELECT photo FROM dresses WHERE id = ?', [$id]);
            $data['photo'] = upload_image('foto', 'dresses', $old ?: null) ?? $old;
            q('UPDATE dresses SET name=:name, category=:category, size=:size, color=:color, rent_price=:rent_price,
               buy_price=:buy_price, purchase_date=:purchase_date, item_condition=:item_condition, status=:status,
               notes=:notes, photo=:photo WHERE id=:id', $data + ['id' => $id]);
            flash_set('ok', 'Baju ' . $nama . ' diperbarui.');
        } else {
            $fotoBaru = upload_image('foto', 'dresses');
            if ($fotoBaru) {
                $data['photo'] = $fotoBaru;
            }
            $data['code'] = next_code('dresses', 'YG-');
            $cols = implode(', ', array_keys($data));
            $ph = implode(', ', array_map(fn($c) => ":$c", array_keys($data)));
            q("INSERT INTO dresses ($cols) VALUES ($ph)", $data);
            flash_set('ok', 'Baju tersimpan dengan kode ' . $data['code'] . '.');
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
        redirect($id ? 'koleksi.php?action=edit&id=' . $id : 'koleksi.php?action=baru');
    }
    redirect('koleksi.php');
}

$edit = null;
if ($action === 'edit' && $id) {
    $edit = qone('SELECT * FROM dresses WHERE id = ?', [$id]);
    if (!$edit) {
        flash_set('error', 'Baju tidak ditemukan.');
        redirect('koleksi.php');
    }
}

$cari = trim((string)($_GET['cari'] ?? ''));
$fKat = (string)($_GET['kategori'] ?? '');
$fStatus = (string)($_GET['status'] ?? '');
$where = [];
$params = [];
if ($cari !== '') {
    $where[] = '(name LIKE :cari OR code LIKE :cari)';
    $params['cari'] = "%$cari%";
}
if (in_array($fKat, $kategoriList, true)) {
    $where[] = 'category = :kat';
    $params['kat'] = $fKat;
}
if (in_array($fStatus, ['tersedia', 'booking', 'disewa', 'dicuci', 'perbaikan', 'tidak_tersedia'], true)) {
    $where[] = 'status = :st';
    $params['st'] = $fStatus;
}
$dresses = qall(
    'SELECT * FROM dresses ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC',
    $params
);

page_start('Koleksi Baju', 'koleksi.php');
render_flash();
?>
<?php if ($action === 'baru' || $action === 'edit'): ?>
    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0"><?= $edit ? 'Edit baju ' . e($edit['code']) : 'Tambah baju baru' ?></h2>
        <form method="post" action="koleksi.php?action=simpan" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div class="span-all">
                    <label for="nama">Nama baju</label>
                    <input type="text" id="nama" name="nama" required maxlength="150"
                           placeholder="mis. Kebaya Modern Merah"
                           value="<?= e($edit['name'] ?? '') ?>">
                </div>
                <div>
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['category'] ?? 'dress') === $k ? 'selected' : '' ?>><?= $kategoriLabel[$k] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="size">Ukuran</label>
                    <input type="text" id="size" name="size" placeholder="mis. M" value="<?= e($edit['size'] ?? '') ?>">
                </div>
                <div>
                    <label for="warna">Warna</label>
                    <input type="text" id="warna" name="warna" placeholder="mis. Merah" value="<?= e($edit['color'] ?? '') ?>">
                </div>
                <div>
                    <label for="tanggal_beli">Tanggal pembelian</label>
                    <input type="date" id="tanggal_beli" name="tanggal_beli" value="<?= e($edit['purchase_date'] ?? '') ?>">
                </div>
                <div>
                    <label for="harga_sewa">Harga sewa (Rp)</label>
                    <input type="text" id="harga_sewa" name="harga_sewa" inputmode="numeric" required
                           placeholder="mis. 150.000" value="<?= isset($edit) ? angka($edit['rent_price']) : '' ?>">
                </div>
                <div>
                    <label for="harga_beli">Harga beli / modal (Rp)</label>
                    <input type="text" id="harga_beli" name="harga_beli" inputmode="numeric"
                           placeholder="mis. 450.000" value="<?= isset($edit) ? angka($edit['buy_price']) : '' ?>">
                </div>
                <div>
                    <label for="kondisi">Kondisi baju</label>
                    <select id="kondisi" name="kondisi">
                        <option value="baik" <?= ($edit['item_condition'] ?? 'baik') === 'baik' ? 'selected' : '' ?>>Baik</option>
                        <option value="perlu_perbaikan" <?= ($edit['item_condition'] ?? '') === 'perlu_perbaikan' ? 'selected' : '' ?>>Perlu perbaikan</option>
                        <option value="rusak" <?= ($edit['item_condition'] ?? '') === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                    </select>
                </div>
                <div>
                    <label for="status">Status ketersediaan</label>
                    <select id="status" name="status" <?= in_array($edit['status'] ?? '', ['booking', 'disewa'], true) ? 'disabled' : '' ?>>
                        <?php
                        $opsiStatus = ['tersedia' => 'Tersedia', 'dicuci' => 'Dicuci', 'perbaikan' => 'Perbaikan', 'tidak_tersedia' => 'Tidak tersedia'];
                        foreach ($opsiStatus as $k => $l): ?>
                            <option value="<?= $k ?>" <?= ($edit['status'] ?? 'tersedia') === $k ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (in_array($edit['status'] ?? '', ['booking', 'disewa'], true)): ?>
                        <p class="hint">Status <?= badge($edit['status']) ?> diatur otomatis oleh transaksi penyewaan.</p>
                    <?php endif; ?>
                </div>
                <div class="span-all">
                    <label for="photoInput">Foto baju</label>
                    <?php if (!empty($edit['photo'])): ?>
                        <img src="<?= file_url($edit["photo"]) ?>" alt="Foto <?= e($edit['name']) ?>" width="120" height="120"
                             style="border-radius:var(--radius-s);object-fit:cover;margin-bottom:8px;display:block">
                    <?php endif; ?>
                    <img id="photoPreview" src="" alt="" width="120" height="120" hidden
                         style="border-radius:var(--radius-s);object-fit:cover;margin-bottom:8px;display:block">
                    <input type="file" id="photoInput" name="foto" accept="image/jpeg,image/png,image/webp">
                    <p class="hint">JPG, PNG, atau WebP, maksimal 3 MB.</p>
                </div>
                <div class="span-all">
                    <label for="notes">Catatan</label>
                    <textarea id="notes" name="notes" placeholder="mis. kerah perlu diperbaiki"><?= e($edit['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-ghost" href="koleksi.php">Batal</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="pagebar">
        <form method="get" class="btn-row growform">
            <input type="text" name="cari" aria-label="Cari baju berdasarkan nama atau kode" value="<?= e($cari) ?>" class="searchfield">
            <select name="kategori" aria-label="Filter kategori" style="max-width:150px">
                <option value="">Semua kategori</option>
                <?php foreach ($kategoriList as $k): ?>
                    <option value="<?= $k ?>" <?= $fKat === $k ? 'selected' : '' ?>><?= $kategoriLabel[$k] ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" aria-label="Filter status" style="max-width:170px">
                <option value="">Semua status</option>
                <?php foreach (['tersedia', 'booking', 'disewa', 'dicuci', 'perbaikan', 'tidak_tersedia'] as $st): ?>
                    <option value="<?= $st ?>" <?= $fStatus === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost">Cari</button>
        </form>
        <span class="spacer"></span>
        <a class="btn btn-primary" href="koleksi.php?action=baru">Tambah baju</a>
    </div>

    <?php if (!$dresses): ?>
        <?= empty_state(
            $cari || $fKat || $fStatus ? 'Tidak ada baju yang cocok dengan pencarian ini.' : 'Koleksi masih kosong. Tambahkan baju pertama untuk mulai disewakan.',
            'Tambah baju',
            'koleksi.php?action=baru'
        ) ?>
    <?php else: ?>
        <div class="catalog">
            <?php foreach ($dresses as $d): ?>
                <article class="dresscard">
                    <div class="photo">
                        <?php if ($d['photo']): ?>
                            <img src="<?= file_url($d["photo"]) ?>" alt="Foto <?= e($d['name']) ?>" width="400" height="300" loading="lazy">
                        <?php else: ?>
                            <div class="pick-noimg">Belum ada foto</div>
                        <?php endif; ?>
                    </div>
                    <div class="dresscard-body">
                        <span class="dresscard-code"><?= e($d['code']) ?> · <?= $kategoriLabel[$d['category']] ?></span>
                        <h2 class="dresscard-name"><?= e($d['name']) ?></h2>
                        <span class="dresscard-meta"><?= e($d['size'] ?: '-') ?> · <?= e($d['color'] ?: '-') ?> · kondisi <?= e(str_replace('_', ' ', $d['item_condition'])) ?></span>
                        <span class="dresscard-price"><?= rupiah($d['rent_price']) ?></span>
                        <div class="dresscard-foot">
                            <?= badge($d['status']) ?>
                            <span class="rowactions">
                                <a class="btn btn-ghost btn-sm" href="koleksi.php?action=edit&id=<?= (int)$d['id'] ?>">Edit</a>
                                <?php if (can_delete()): ?>
                                    <form method="post" action="koleksi.php?action=hapus" data-confirm="Hapus <?= e($d['code']) ?> dari koleksi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
page_end();
