<?php
require_once __DIR__ . '/../app/layout.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'bayar') {
    csrf_check();
    $rid = post_int('rental_id');
    $amount = post_amount('jumlah');
    $r = qone("SELECT r.id, r.code, r.customer_id, r.status, r.total FROM rentals r WHERE r.id = ?", [$rid]);
    if (!$r || $r['status'] === 'batal') {
        flash_set('error', 'Transaksi tidak ditemukan atau sudah dibatalkan.');
        redirect('piutang.php');
    }
    if ($amount === null) {
        flash_set('error', 'Nominal pembayaran tidak valid.');
        redirect('piutang.php');
    }
    if ($amount <= 0) {
        flash_set('error', 'Nominal pembayaran harus lebih dari nol.');
        redirect('piutang.php');
    }
    $paid = rental_paid($rid);
    $sisa = (float)$r['total'] - $paid;
    if ($sisa <= 0) {
        flash_set('error', 'Tagihan sudah lunas.');
        redirect('piutang.php');
    }
    if ($amount > $sisa) {
        flash_set('error', 'Nominal melebihi sisa tagihan (' . rupiah($sisa) . ').');
        redirect('piutang.php');
    }
    q(
        'INSERT INTO incomes (date, category, rental_id, customer_id, description, amount, method)
         VALUES (:d, "pelunasan", :rid, :cid, :desc, :amt, :m)',
        [
            'd' => post_date('tanggal') ?: date('Y-m-d'), 'rid' => $rid, 'cid' => (int)$r['customer_id'],
            'desc' => 'Pembayaran ' . $r['code'], 'amt' => $amount,
            'm' => post_enum('method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'),
        ]
    );
    flash_set('ok', 'Pembayaran ' . rupiah($amount) . ' untuk ' . $r['code'] . ' tercatat.');
    redirect('piutang.php');
}

$piutang = qall(
    "SELECT r.id, r.code, r.total, r.due_date, r.status, c.name AS customer, COALESCE(p.paid,0) AS paid
     FROM rentals r
     JOIN customers c ON c.id = r.customer_id
     LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p ON p.rental_id = r.id
     WHERE r.status <> 'batal' AND r.total > COALESCE(p.paid,0)
     ORDER BY COALESCE(r.due_date, r.return_date) ASC, r.id ASC"
);
$today = date('Y-m-d');
$totalSisa = 0;
$jatuhTempo = 0;
foreach ($piutang as $p) {
    $sisa = (float)$p['total'] - (float)$p['paid'];
    $totalSisa += $sisa;
    $due = $p['due_date'] ?: $p['return_date'];
    if ($due && $due < $today) {
        $jatuhTempo++;
    }
}

page_start('Piutang Pelanggan', 'piutang.php');
render_flash();
?>
<div class="statgrid">
    <div class="stat rose">
        <span class="stat-label">Total piutang berjalan</span>
        <span class="stat-value money"><?= rupiah($totalSisa) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Transaksi belum lunas</span>
        <span class="stat-value"><?= count($piutang) ?> transaksi</span>
    </div>
    <div class="stat">
        <span class="stat-label">Melewati jatuh tempo</span>
        <span class="stat-value"><?= $jatuhTempo ?> transaksi</span>
    </div>
</div>

<section class="section">
    <h2>Daftar piutang</h2>
    <p class="section-sub">Sumber piutang adalah sisa pembayaran tiap transaksi penyewaan, jadi selalu konsisten dengan data sewa.</p>
    <?php if (!$piutang): ?>
        <?= empty_state('Semua tagihan pelanggan sudah lunas.', 'Lihat penyewaan', 'sewa.php') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Kode</th><th>Pelanggan</th><th>Jatuh tempo</th><th>Status</th>
                    <th class="num hide-sm">Tagihan</th><th class="num hide-sm">Dibayar</th><th class="num">Sisa</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($piutang as $p): ?>
                    <?php
                    $sisa = (float)$p['total'] - (float)$p['paid'];
                    $due = $p['due_date'] ?: $p['return_date'];
                    $telat = $due && $due < $today;
                    if ($telat) {
                        $stHtml = '<span class="badge st-late">Melewati jatuh tempo</span>';
                    } elseif ((float)$p['paid'] > 0) {
                        $stHtml = badge('sebagian');
                    } else {
                        $stHtml = badge('belum_lunas');
                    }
                    ?>
                    <tr>
                        <td class="cell-main table-code"><?= e($p["code"]) ?></td>
                        <td><?= e($p['customer']) ?></td>
                        <td><?= tgl($due) ?></td>
                        <td><?= $stHtml ?></td>
                        <td class="num hide-sm"><?= rupiah($p["total"]) ?></td>
                        <td class="num hide-sm"><?= rupiah($p["paid"]) ?></td>
                        <td class="num"><?= rupiah($sisa) ?></td>
                        <td>
                            <span class="rowactions">
                                <button type="button" class="btn btn-primary btn-sm" data-bayar data-dialog="bayarDialog" data-id="<?= (int)$p['id'] ?>" data-sisa="<?= angka($sisa) ?>">Bayar</button>
                                <a class="btn btn-ghost btn-sm" href="sewa_detail.php?id=<?= (int)$p['id'] ?>">Detail</a>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<dialog id="bayarDialog" class="modal">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="bayar">
        <input type="hidden" name="rental_id" data-field="id" value="">
        <h2>Bayar piutang</h2>
        <div>
            <label for="bayarJumlah">Jumlah (Rp)</label>
            <input type="text" id="bayarJumlah" name="jumlah" data-field="jumlah" inputmode="numeric">
        </div>
        <div style="margin-top:10px">
            <label for="bayarTanggal">Tanggal</label>
            <input type="date" id="bayarTanggal" name="tanggal" value="<?= date('Y-m-d') ?>">
        </div>
        <div style="margin-top:10px">
            <label for="bayarMethod">Metode</label>
            <select id="bayarMethod" name="method">
                <option value="cash">Tunai</option>
                <option value="transfer">Transfer</option>
                <option value="qris">QRIS</option>
                <option value="lainnya">Lainnya</option>
            </select>
        </div>
        <div class="form-actions" style="margin-top:14px">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <button type="button" class="btn btn-ghost" data-close>Batal</button>
        </div>
    </form>
</dialog>
<?php
page_end();
