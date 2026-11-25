<?php
require_once __DIR__ . '/../app/layout.php';

require_login();
$id = post_int('id') ?: (int)($_GET['id'] ?? 0);
$rental = qone(
    'SELECT r.*, c.name AS customer, c.whatsapp, c.address
     FROM rentals r JOIN customers c ON c.id = r.customer_id
     WHERE r.id = ?',
    [$id]
);
if (!$rental) {
    flash_set('error', 'Transaksi tidak ditemukan.');
    redirect('sewa.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'batal') {
    csrf_check();
    if (in_array($rental['status'], ['dikembalikan', 'batal'], true)) {
        flash_set('error', 'Transaksi ini tidak bisa dibatalkan.');
        redirect('sewa_detail.php?id=' . $id);
    }
    $paid = rental_paid($id);
    db()->beginTransaction();
    try {
        q('UPDATE rentals SET status = "batal" WHERE id = ?', [$id]);
        // Bebaskan baju hanya bila tidak sedang disewa transaksi aktif lain.
        q(
            'UPDATE dresses d
             JOIN rental_items ri ON ri.dress_id = d.id
             SET d.status = "tersedia"
             WHERE ri.rental_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM rental_items ri2
                   JOIN rentals r2 ON r2.id = ri2.rental_id
                   WHERE ri2.dress_id = d.id AND ri2.rental_id <> ? AND r2.status IN ("booking","disewa")
               )',
            [$id, $id]
        );
        // Refund: catat pemasukan negatif supaya jejak uang tetap utuh.
        if ($paid > 0) {
            q(
                'INSERT INTO incomes (date, category, rental_id, customer_id, description, amount, method)
                 VALUES (:d, "lainnya", :rid, :cid, :desc, :amt, :m)',
                [
                    'd' => date('Y-m-d'), 'rid' => $id, 'cid' => (int)$rental['customer_id'],
                    'desc' => 'Refund pembatalan ' . $rental['code'], 'amt' => -$paid,
                    'm' => $rental['payment_method'],
                ]
            );
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash_set('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
        redirect('sewa_detail.php?id=' . $id);
    }
    flash_set('ok', 'Transaksi ' . $rental['code'] . ' dibatalkan.' . ($paid > 0 ? ' Pembayaran ' . rupiah($paid) . ' dicatat sebagai refund.' : ''));
    redirect('sewa_detail.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'bayar') {
    csrf_check();
    $amount = post_amount('jumlah');
    $jenis = post_enum('jenis', ['dp', 'pelunasan', 'denda', 'kerusakan', 'lainnya'], 'pelunasan');
    $date = post_date('tanggal') ?: date('Y-m-d');
    if ($rental['status'] === 'batal') {
        flash_set('error', 'Transaksi sudah dibatalkan.');
        redirect('sewa_detail.php?id=' . $id);
    }
    if ($amount === null) {
        flash_set('error', 'Nominal pembayaran tidak valid.');
        redirect('sewa_detail.php?id=' . $id);
    }
    if ($amount <= 0) {
        flash_set('error', 'Nominal pembayaran harus lebih dari nol.');
        redirect('sewa_detail.php?id=' . $id);
    }
    $paid = rental_paid($id);
    $sisa = (float)$rental['total'] - $paid;
    if ($sisa <= 0) {
        flash_set('error', 'Tagihan sudah lunas.');
        redirect('sewa_detail.php?id=' . $id);
    }
    if ($amount > $sisa) {
        flash_set('error', 'Nominal melebihi sisa tagihan (' . rupiah($sisa) . ').');
        redirect('sewa_detail.php?id=' . $id);
    }
    q(
        'INSERT INTO incomes (date, category, rental_id, customer_id, description, amount, method)
         VALUES (:d, :c, :rid, :cid, :desc, :amt, :m)',
        [
            'd' => $date, 'c' => $jenis, 'rid' => $id, 'cid' => (int)$rental['customer_id'],
            'desc' => post('keterangan') ?: ($jenis === 'pelunasan' ? 'Pembayaran ' . $rental['code'] : ucfirst($jenis) . ' ' . $rental['code']),
            'amt' => $amount,
            'm' => post_enum('method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'),
        ]
    );
    flash_set('ok', 'Pembayaran ' . rupiah($amount) . ' tercatat.');
    redirect('sewa_detail.php?id=' . $id);
}

$items = qall(
    'SELECT ri.*, d.code, d.name, d.size, d.color, d.photo
     FROM rental_items ri JOIN dresses d ON d.id = ri.dress_id
     WHERE ri.rental_id = ?
     ORDER BY ri.id ASC',
    [$id]
);
$payments = qall('SELECT * FROM incomes WHERE rental_id = ? ORDER BY date ASC, id ASC', [$id]);
$paid = array_sum(array_column($payments, 'amount'));
$sisa = (float)$rental['total'] - $paid;

$s = settings();
$brand = $s['business_name'] ?: 'YAYA Gallery';

$waText = "*$brand*\nInvoice {$rental['code']}\n"
    . "Pelanggan: {$rental['customer']}\n"
    . "Diambil: " . tgl($rental['pickup_date']) . " · Kembali: " . tgl($rental['return_date']) . "\n\n"
    . "Rincian baju:\n";
foreach ($items as $it) {
    $waText .= '- ' . $it['name'] . ' (' . $it['code'] . '): ' . rupiah($it['price']) . "\n";
}
$waText .= "\n"
    . "Diskon: " . rupiah($rental['discount']) . "\n"
    . "Ongkos tambahan: " . rupiah($rental['extra_fee']) . "\n"
    . ($rental['late_fee'] > 0 ? "Denda keterlambatan: " . rupiah($rental['late_fee']) . "\n" : '')
    . ($rental['damage_fee'] > 0 ? "Biaya kerusakan: " . rupiah($rental['damage_fee']) . "\n" : '')
    . "*Total: " . rupiah($rental['total']) . "*\n"
    . "Sudah dibayar: " . rupiah($paid) . "\n"
    . "Sisa: " . rupiah(max(0, $sisa)) . "\n"
    . "\nJatuh tempo: " . tgl($rental['due_date']) . "\n"
    . ($rental['deposit'] > 0 ? "Deposit: " . rupiah($rental['deposit']) . ($rental['deposit_used'] ? " (sudah dipakai)" : " (dikembalikan saat baju kembali)") . "\n" : '')
    . ($s['invoice_notes'] ? "\n" . $s['invoice_notes'] : '');

page_start('Detail Penyewaan ' . $rental['code'], 'sewa.php');
render_flash();
?>
<div class="pagebar">
    <a class="btn btn-ghost" href="sewa.php">Ke daftar penyewaan</a>
    <span class="spacer"></span>
    <?php if (in_array($rental['status'], ['booking', 'disewa'], true)): ?>
        <a class="btn btn-primary" href="pengembalian.php?rental=<?= (int)$id ?>">Proses pengembalian</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="invoice.php?id=<?= (int)$id ?>" target="_blank"><?= icon('print') ?> Invoice</a>
    <?php if ($rental['whatsapp']): ?>
        <a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= e(wa_link($rental['whatsapp'], $waText)) ?>"><?= icon('wa') ?> Kirim WhatsApp</a>
    <?php endif; ?>
</div>

<div class="cols split">
    <div class="cols">
        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Baju yang disewa</h2>
            <div class="table-wrap">
                <table class="items-table">
                    <thead><tr><th>Baju</th><th class="num">Harga sewa</th><th>Kondisi kembali</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= e($it['name']) ?></span>
                                <span class="cell-sub"><?= e($it['code']) ?> · <?= e($it['size'] ?: '-') ?> · <?= e($it['color'] ?: '-') ?></span>
                            </td>
                            <td class="num"><?= rupiah($it['price']) ?></td>
                            <td><?= $it['condition_on_return'] ? badge($it['condition_on_return']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <dl class="kv" style="margin-top:14px">
                <div><dt>Subtotal</dt><dd><?= rupiah($rental['subtotal']) ?></dd></div>
                <div><dt>Diskon</dt><dd>- <?= rupiah($rental['discount']) ?></dd></div>
                <div><dt>Ongkos tambahan</dt><dd><?= rupiah($rental['extra_fee']) ?></dd></div>
                <?php if ($rental['late_fee'] > 0): ?><div><dt>Denda keterlambatan</dt><dd><?= rupiah($rental['late_fee']) ?></dd></div><?php endif; ?>
                <?php if ($rental['damage_fee'] > 0): ?><div><dt>Biaya kerusakan</dt><dd><?= rupiah($rental['damage_fee']) ?></dd></div><?php endif; ?>
                <div><dt>Total tagihan</dt><dd class="strong totline"><?= rupiah($rental['total']) ?></dd></div>
            </dl>
        </div>

        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Riwayat pembayaran</h2>
            <?php if (!$payments): ?>
                <p class="section-sub">Belum ada pembayaran tercatat.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Metode</th><th>Keterangan</th><th class="num">Jumlah</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= tgl($p['date']) ?></td>
                                <td><?= badge($p['category']) ?></td>
                                <td><?= badge($p['method']) ?></td>
                                <td><?= e($p['description']) ?></td>
                                <td class="num"><?= rupiah($p['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="4">Sudah dibayar</td><td class="num"><?= rupiah($paid) ?></td></tr></tfoot>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($rental['status'] !== 'batal'): ?>
                <dl class="kv" style="margin-top:12px">
                    <div>
                        <dt><?= $sisa >= 0 ? 'Sisa pembayaran' : 'Lebih bayar' ?></dt>
                        <dd class="strong <?= $sisa > 0 ? 'totline' : '' ?>"><?= rupiah(abs($sisa)) ?></dd>
                    </div>
                </dl>
            <?php endif; ?>
            <?php if ($rental['status'] !== 'batal'): ?>
                <details style="margin-top:12px">
                    <summary style="cursor:pointer;font-weight:700;font-size:14px">Tambah pembayaran</summary>
                    <form method="post" style="margin-top:12px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="bayar">
                        <div class="form-grid">
                            <div>
                                <label for="jenis">Jenis</label>
                                <select id="jenis" name="jenis">
                                    <option value="pelunasan">Pelunasan</option>
                                    <option value="dp">DP</option>
                                    <option value="denda">Denda keterlambatan</option>
                                    <option value="kerusakan">Biaya kerusakan</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label for="jumlah">Jumlah (Rp)</label>
                                <input type="text" id="jumlah" name="jumlah" inputmode="numeric" value="<?= $sisa > 0 ? angka($sisa) : '' ?>" placeholder="mis. 100.000">
                            </div>
                            <div>
                                <label for="tanggal">Tanggal</label>
                                <input type="date" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>">
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
                                <input type="text" id="keterangan" name="keterangan" placeholder="Kosongkan untuk keterangan otomatis">
                                <p class="hint">Denda dan biaya kerusakan biasanya sudah masuk total saat proses Pengembalian.</p>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Catat pembayaran</button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <div class="cols">
        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Pelanggan</h2>
            <dl class="kv">
                <div><dt>Nama</dt><dd class="strong"><?= e($rental['customer']) ?></dd></div>
                <div><dt>WhatsApp</dt><dd><?= e($rental['whatsapp'] ?: '-') ?></dd></div>
                <div><dt>Alamat</dt><dd><?= e($rental['address'] ?: '-') ?></dd></div>
            </dl>
            <div class="btn-row" style="margin-top:10px">
                <a class="btn btn-ghost btn-sm" href="pelanggan.php?action=detail&id=<?= (int)$rental['customer_id'] ?>">Riwayat pelanggan</a>
            </div>
        </div>

        <div class="card">
            <h2 class="section" style="margin-top:0;padding-left:0">Waktu &amp; status</h2>
            <dl class="kv">
                <div><dt>Status</dt><dd>
                    <?php if (rental_overdue($rental)): ?>
                        <span class="badge st-late">Terlambat <?= late_days($rental) ?> hari</span>
                    <?php else: ?>
                        <?= badge($rental['status']) ?>
                    <?php endif; ?>
                </dd></div>
                <div><dt>Tanggal sewa</dt><dd><?= tgl($rental['rent_date']) ?></dd></div>
                <div><dt>Pengambilan</dt><dd><?= tgl($rental['pickup_date']) ?></dd></div>
                <div><dt>Pengembalian</dt><dd><?= tgl($rental['return_date']) ?></dd></div>
                <div><dt>Jatuh tempo bayar</dt><dd><?= tgl($rental['due_date']) ?></dd></div>
                <?php if ($rental['actual_return_date']): ?>
                    <div><dt>Dikembalikan</dt><dd><?= tgl($rental['actual_return_date']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Deposit</dt><dd><?= $rental['deposit'] > 0 ? rupiah($rental['deposit']) . ($rental['deposit_used'] ? ' (terpakai)' : ' (dititipkan)') : '-' ?></dd></div>
                <div><dt>Metode bayar</dt><dd><?= badge($rental['payment_method']) ?></dd></div>
            </dl>
            <?php if ($rental['notes']): ?>
                <p class="hint">Catatan: <?= e($rental['notes']) ?></p>
            <?php endif; ?>
            <?php if (in_array($rental['status'], ['booking', 'disewa'], true)): ?>
                <form method="post" data-confirm="Batalkan transaksi <?= e($rental['code']) ?>? Baju akan kembali tersedia untuk disewakan." style="margin-top:12px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="batal">
                    <button type="submit" class="btn btn-danger btn-sm">Batalkan transaksi</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
page_end();
