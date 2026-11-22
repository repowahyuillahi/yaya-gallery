<?php
require_once __DIR__ . '/../app/layout.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'simpan') {
    csrf_check();
    $old = (string)qval('SELECT logo FROM settings WHERE id = 1');
    $logo = upload_image('logo', 'logo', $old ?: null) ?? $old;
    q('UPDATE settings SET business_name = :bn, address = :ad, phone = :ph, late_fee_per_day = :lf,
       default_deposit = :dd, invoice_notes = :in, logo = :lg WHERE id = 1', [
        'bn' => post('business_name') ?: 'YAYA Gallery',
        'ad' => post('address') ?: null,
        'ph' => post('phone') ?: null,
        'lf' => post_amount('late_fee_per_day'),
        'dd' => post_amount('default_deposit'),
        'in' => post('invoice_notes') ?: null,
        'lg' => $logo,
    ]);
    flash_set('ok', 'Pengaturan tersimpan.');
    redirect('pengaturan.php');
}

$s = settings();
page_start('Pengaturan', 'pengaturan.php');
render_flash();
?>
<div class="card" style="max-width:760px">
    <h2 class="section" style="margin-top:0;padding-left:0">Profil bisnis</h2>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="simpan">
        <div class="form-grid">
            <div>
                <label for="business_name">Nama bisnis</label>
                <input type="text" id="business_name" name="business_name" value="<?= e($s['business_name']) ?>" required>
            </div>
            <div>
                <label for="phone">Kontak / nomor telepon</label>
                <input type="text" id="phone" name="phone" value="<?= e($s['phone']) ?>" placeholder="mis. 0812-3456-7890">
            </div>
            <div class="span-all">
                <label for="address">Alamat</label>
                <input type="text" id="address" name="address" value="<?= e($s['address']) ?>" placeholder="Alamat lengkap toko">
            </div>
            <div>
                <label for="late_fee_per_day">Denda keterlambatan (Rp / hari)</label>
                <input type="text" id="late_fee_per_day" name="late_fee_per_day" inputmode="numeric" value="<?= angka($s['late_fee_per_day']) ?>">
                <p class="hint">Dipakai sebagai saran denda otomatis saat proses pengembalian.</p>
            </div>
            <div>
                <label for="default_deposit">Deposit default (Rp)</label>
                <input type="text" id="default_deposit" name="default_deposit" inputmode="numeric" value="<?= angka($s['default_deposit']) ?>">
                <p class="hint">Mengisi otomatis kolom deposit pada form penyewaan baru.</p>
            </div>
            <div class="span-all">
                <label for="logoInput">Logo bisnis</label>
                <?php if (!empty($s['logo'])): ?>
                    <img src="<?= u($s["logo"]) ?>" alt="Logo bisnis" width="64" height="64"
                         style="border-radius:var(--radius-s);object-fit:cover;margin-bottom:8px;display:block">
                <?php endif; ?>
                <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png,image/webp">
                <p class="hint">Ditampilkan di samping nama bisnis pada menu samping. JPG, PNG, atau WebP, maksimal 3 MB.</p>
            </div>
            <div class="span-all">
                <label for="invoice_notes">Catatan pada invoice</label>
                <textarea id="invoice_notes" name="invoice_notes" placeholder="mis. Terima kasih telah menyewa di YAYA Gallery."><?= e($s['invoice_notes']) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
        </div>
    </form>
</div>

<div class="card section" style="max-width:760px">
    <h2 class="section" style="margin-top:0;padding-left:0">Hak akses</h2>
    <ul class="stat-list">
        <li><span>Admin</span><strong>Semua menu, termasuk pengaturan, pengguna, dan hapus data</strong></li>
        <li><span>Karyawan</span><strong>Operasional harian dan pencatatan keuangan, tanpa hapus data</strong></li>
    </ul>
    <p class="hint">Kelola akun di menu <a href="users.php">Pengguna</a>.</p>
</div>
<?php
page_end();
