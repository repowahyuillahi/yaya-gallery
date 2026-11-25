<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
refresh_booking_statuses();
$rentalId = post_int('rental_id') ?: (int)($_GET['rental'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'selesai_cuci') {
    csrf_check();
    q('UPDATE dresses SET status = "tersedia" WHERE id = ? AND status = "dicuci"', [post_int('dress_id')]);
    flash_set('ok', 'Baju selesai dicuci dan kembali tersedia untuk disewakan.');
    redirect('pengembalian.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'selesai_perbaikan') {
    csrf_check();
    q('UPDATE dresses SET status = "tersedia", item_condition = "baik" WHERE id = ? AND status = "perbaikan"', [post_int('dress_id')]);
    flash_set('ok', 'Perbaikan selesai, baju kembali tersedia untuk disewakan.');
    redirect('pengembalian.php');
}

$rental = null;
if ($rentalId) {
    $rental = qone(
        'SELECT r.*, c.name AS customer, c.whatsapp
         FROM rentals r JOIN customers c ON c.id = r.customer_id
         WHERE r.id = ?',
        [$rentalId]
    );
    if (!$rental || !in_array($rental['status'], ['booking', 'disewa'], true)) {
        flash_set('error', $rental ? 'Transaksi ini sudah tidak dalam proses penyewaan.' : 'Transaksi tidak ditemukan.');
        redirect('pengembalian.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'proses') {
    csrf_check();
    $items = qall('SELECT ri.id, ri.dress_id, d.name FROM rental_items ri JOIN dresses d ON d.id = ri.dress_id WHERE ri.rental_id = ?', [$rentalId]);
    $tglKembali = post_date('tanggal_kembali');
    $denda = post_amount('denda');
    $kerusakan = post_amount('kerusakan');

    $err = [];
    if ($tglKembali === null) {
        $err[] = 'Tanggal dikembalikan tidak valid.';
    }
    if ($denda === null) {
        $err[] = 'Nominal denda tidak valid.';
    }
    if ($kerusakan === null) {
        $err[] = 'Nominal biaya kerusakan tidak valid.';
    }
    $byItem = (array)($_POST['kondisi'] ?? []);
    $catatan = (array)($_POST['damage_note'] ?? []);
    foreach ($items as $it) {
        if (!in_array($byItem[$it['id']] ?? '', ['baik', 'rusak', 'hilang'], true)) {
            $err[] = 'Kondisi baju ' . $it['name'] . ' belum dipilih.';
        }
    }
    if ($err) {
        flash_set('error', implode(' ', $err));
        redirect('pengembalian.php?rental=' . $rentalId);
    }

    $tglKembali = $tglKembali ?: date('Y-m-d');
    db()->beginTransaction();
    try {
        $adaRusak = false;
        $adaHilang = false;
        foreach ($items as $it) {
            $kondisi = $byItem[$it['id']];
            $note = trim((string)($catatan[$it['id']] ?? ''));
            q('UPDATE rental_items SET condition_on_return = :k, damage_note = :n WHERE id = :id', [
                'k' => $kondisi, 'n' => $note ?: null, 'id' => $it['id'],
            ]);
            if ($kondisi === 'baik') {
                q('UPDATE dresses SET status = "dicuci", item_condition = "baik" WHERE id = ?', [$it['dress_id']]);
            } elseif ($kondisi === 'rusak') {
                q('UPDATE dresses SET status = "perbaikan", item_condition = "rusak" WHERE id = ?', [$it['dress_id']]);
                $adaRusak = true;
            } else {
                q('UPDATE dresses SET status = "tidak_tersedia", item_condition = "rusak" WHERE id = ?', [$it['dress_id']]);
                $adaHilang = true;
            }
        }
        q('UPDATE rentals SET status = "dikembalikan", actual_return_date = :t, late_fee = :lf, damage_fee = :df WHERE id = :id', [
            't' => $tglKembali, 'lf' => $denda, 'df' => $kerusakan, 'id' => $rentalId,
        ]);
        rental_refresh_totals($rentalId);

        // Deposit hanya dipakai bila benar ada sisa tagihan setelah denda/kerusakan.
        $totalBaru = (float)qval('SELECT total FROM rentals WHERE id = ?', [$rentalId]);
        $sisa = $totalBaru - rental_paid($rentalId);
        $deposit = (float)$rental['deposit'];
        $pakaiDeposit = post('pakai_deposit') === '1' && $deposit > 0 && $sisa > 0;
        if ($pakaiDeposit) {
            $pakai = min($deposit, $sisa);
            q(
                'INSERT INTO incomes (date, category, rental_id, customer_id, description, amount, method)
                 VALUES (:d, "pelunasan", :rid, :cid, :desc, :amt, "cash")',
                [
                    'd' => $tglKembali, 'rid' => $rentalId, 'cid' => (int)$rental['customer_id'],
                    'desc' => 'Deposit dipakai untuk ' . $rental['code'], 'amt' => $pakai,
                ]
            );
            $sisa -= $pakai;
        }
        q('UPDATE rentals SET deposit_used = :du WHERE id = :id', ['du' => $pakaiDeposit ? 1 : 0, 'id' => $rentalId]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash_set('error', 'Gagal memproses pengembalian: ' . $e->getMessage());
        redirect('pengembalian.php?rental=' . $rentalId);
    }

    $ringkas = 'Pengembalian ' . $rental['code'] . ' tercatat.';
    if ($denda > 0) {
        $ringkas .= ' Denda ' . rupiah($denda) . '.';
    }
    if ($kerusakan > 0) {
        $ringkas .= ' Biaya kerusakan ' . rupiah($kerusakan) . '.';
    }
    if ($adaHilang) {
        $ringkas .= ' Ada baju hilang, statusnya menjadi Tidak tersedia.';
    }
    $ringkas .= ' Baju kondisi baik masuk antrean laundry.';
    if ($sisa > 0) {
        $ringkas .= ' Sisa pembayaran ' . rupiah($sisa) . '.';
    } else {
        $ringkas .= ' Tagihan sudah lunas.';
    }
    flash_set('ok', $ringkas);
    redirect('pengembalian.php');
}

$harusKembali = qall(
    "SELECT r.id, r.code, r.status, r.return_date, r.pickup_date, c.name AS customer
     FROM rentals r JOIN customers c ON c.id = r.customer_id
     WHERE r.status IN ('booking','disewa')
     ORDER BY r.return_date ASC"
);
$laundry = qall("SELECT id, code, name, size, color, photo FROM dresses WHERE status = 'dicuci' ORDER BY name ASC");
$perbaikan = qall("SELECT id, code, name, size, color, photo FROM dresses WHERE status = 'perbaikan' ORDER BY name ASC");
$riwayat = qall(
    "SELECT r.id, r.code, r.actual_return_date, r.late_fee, r.damage_fee, c.name AS customer,
        (SELECT COUNT(*) FROM rental_items ri WHERE ri.rental_id = r.id AND ri.condition_on_return = 'rusak') rusak,
        (SELECT COUNT(*) FROM rental_items ri WHERE ri.rental_id = r.id AND ri.condition_on_return = 'hilang') hilang
     FROM rentals r JOIN customers c ON c.id = r.customer_id
     WHERE r.status = 'dikembalikan'
     ORDER BY r.actual_return_date DESC, r.id DESC
     LIMIT 15"
);

$s = settings();
page_start('Pengembalian', 'pengembalian.php');
render_flash();
?>
<?php if ($rental): ?>
    <?php $items = qall('SELECT ri.*, d.code, d.name, d.size, d.color FROM rental_items ri JOIN dresses d ON d.id = ri.dress_id WHERE ri.rental_id = ? ORDER BY ri.id ASC', [$rentalId]); ?>
    <div class="pagebar">
        <a class="btn btn-ghost" href="pengembalian.php">Ke daftar pengembalian</a>
    </div>
    <div class="card">
        <h2 class="section" style="margin-top:0;padding-left:0">Proses pengembalian <?= e($rental['code']) ?></h2>
        <p class="section-sub">Pelanggan: <?= e($rental['customer']) ?> · seharusnya kembali <?= tgl($rental['return_date']) ?>
            <?php if (rental_overdue($rental)): ?>
                · <span class="badge st-late">Terlambat <?= late_days($rental) ?> hari</span>
            <?php endif; ?>
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="proses">
            <input type="hidden" name="rental_id" value="<?= (int)$rentalId ?>">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Baju</th><th>Kondisi saat dikembalikan</th><th>Catatan kerusakan</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= e($it['name']) ?></span>
                                <span class="cell-sub"><?= e($it['code']) ?> · <?= e($it['size'] ?: '-') ?></span>
                            </td>
                            <td>
                                <select name="kondisi[<?= (int)$it['id'] ?>]" aria-label="Kondisi <?= e($it['name']) ?>">
                                    <option value="baik">Baik (masuk laundry)</option>
                                    <option value="rusak">Rusak (masuk perbaikan)</option>
                                    <option value="hilang">Hilang</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="damage_note[<?= (int)$it['id'] ?>]" aria-label="Catatan kerusakan <?= e($it['name']) ?>" placeholder="mis. robek di lengan">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-grid" style="margin-top:16px">
                <div>
                    <label for="tanggal_kembali">Tanggal dikembalikan</label>
                    <input type="date" id="tanggal_kembali" name="tanggal_kembali" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label for="denda">Denda keterlambatan (Rp)</label>
                    <input type="text" id="denda" name="denda" inputmode="numeric"
                           value="<?= angka(late_days($rental) * (float)$s['late_fee_per_day']) ?>">
                    <p class="hint">Terlambat <?= late_days($rental) ?> hari × <?= rupiah($s['late_fee_per_day']) ?>/hari, boleh diubah.</p>
                </div>
                <div>
                    <label for="kerusakan">Biaya kerusakan / pengganti (Rp)</label>
                    <input type="text" id="kerusakan" name="kerusakan" inputmode="numeric" value="0">
                    <p class="hint">Termasuk biaya ganti baju yang hilang.</p>
                </div>
                <?php if ($rental['deposit'] > 0): ?>
                    <div>
                        <div class="checkline" style="margin-top:24px">
                            <input type="checkbox" id="pakai_deposit" name="pakai_deposit" value="1">
                            <label for="pakai_deposit">Pakai deposit <?= rupiah($rental['deposit']) ?> untuk pelunasan</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Tandai selesai dikembalikan</button>
                <a class="btn btn-ghost" href="pengembalian.php">Batal</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <section class="section" style="margin-top:0">
        <h2>Harus dikembalikan</h2>
        <?php if (!$harusKembali): ?>
            <?= empty_state('Tidak ada penyewaan yang sedang berjalan saat ini.', 'Lihat penyewaan', 'sewa.php') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Pelanggan</th><th class="hide-sm">Diambil</th><th>Jadwal kembali</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($harusKembali as $r): ?>
                        <?php
                        $today = date('Y-m-d');
                        if (rental_overdue($r)) {
                            $statusHtml = '<span class="badge st-late">Terlambat ' . late_days($r) . ' hari</span>';
                        } elseif ($r['return_date'] === $today && $r['status'] === 'disewa') {
                            $statusHtml = '<span class="badge st-out">Kembali hari ini</span>';
                        } else {
                            $statusHtml = badge($r['status']);
                        }
                        ?>
                        <tr>
                            <td class="cell-main"><?= e($r['code']) ?></td>
                            <td><?= e($r['customer']) ?></td>
                            <td class="hide-sm"><?= tgl($r["pickup_date"]) ?></td>
                            <td><?= tgl($r['return_date']) ?></td>
                            <td><?= $statusHtml ?></td>
                            <td><a class="btn btn-primary btn-sm" href="pengembalian.php?rental=<?= (int)$r['id'] ?>">Proses</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid-2 section">
        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Antrean laundry</h2>
            <?php if (!$laundry): ?>
                <p class="section-sub">Tidak ada baju yang sedang dicuci.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="items-table">
                        <thead><tr><th>Baju</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($laundry as $d): ?>
                            <tr>
                                <td>
                                    <span class="cell-main"><?= e($d['name']) ?></span>
                                    <span class="cell-sub"><?= e($d['code']) ?> · <?= e($d['size'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="selesai_cuci">
                                        <input type="hidden" name="dress_id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Selesai cuci</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Sedang perbaikan</h2>
            <?php if (!$perbaikan): ?>
                <p class="section-sub">Tidak ada baju dalam perbaikan.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="items-table">
                        <thead><tr><th>Baju</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($perbaikan as $d): ?>
                            <tr>
                                <td>
                                    <span class="cell-main"><?= e($d['name']) ?></span>
                                    <span class="cell-sub"><?= e($d['code']) ?> · <?= e($d['size'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="do" value="selesai_perbaikan">
                                        <input type="hidden" name="dress_id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Selesai perbaikan</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <section class="section">
        <h2>Riwayat pengembalian</h2>
        <?php if (!$riwayat): ?>
            <?= empty_state('Belum ada pengembalian yang tercatat.') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Pelanggan</th><th>Dikembalikan</th><th class="num">Denda</th><th class="num">Kerusakan</th><th>Kondisi</th></tr></thead>
                    <tbody>
                    <?php foreach ($riwayat as $r): ?>
                        <tr>
                            <td class="cell-main"><?= e($r['code']) ?></td>
                            <td><?= e($r['customer']) ?></td>
                            <td><?= tgl($r['actual_return_date']) ?></td>
                            <td class="num"><?= $r['late_fee'] > 0 ? rupiah($r['late_fee']) : '-' ?></td>
                            <td class="num"><?= $r['damage_fee'] > 0 ? rupiah($r['damage_fee']) : '-' ?></td>
                            <td>
                                <?php
                                $k = [];
                                if ($r['rusak']) { $k[] = (int)$r['rusak'] . ' rusak'; }
                                if ($r['hilang']) { $k[] = (int)$r['hilang'] . ' hilang'; }
                                echo $k ? '<span class="badge st-late">' . e(implode(', ', $k)) . '</span>' : badge('baik');
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
page_end();
