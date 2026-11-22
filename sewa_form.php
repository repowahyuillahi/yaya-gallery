<?php
require_once __DIR__ . '/layout.php';

require_login();

$tersedia = qall(
    "SELECT id, code, name, size, color, rent_price, photo, category
     FROM dresses
     WHERE status = 'tersedia' AND item_condition <> 'rusak'
     ORDER BY name ASC"
);
$customers = qall('SELECT id, name, whatsapp FROM customers ORDER BY name ASC');
$s = settings();
$prefillCustomer = (int)($_GET['customer'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $items = array_map('intval', (array)($_POST['items'] ?? []));
    $items = array_values(array_filter($items));

    $err = [];
    if (!post_date('return_date')) {
        $err[] = 'Tanggal pengembalian wajib diisi.';
    }
    if (!$items) {
        $err[] = 'Pilih minimal satu baju.';
    }
    $waBaru = preg_replace('/[^0-9+]/', '', post('new_whatsapp'));
    $customerId = post_int('customer_id');
    $namaBaru = post('new_nama');

    if (!$err) {
        db()->beginTransaction();
        try {
            if ($namaBaru !== '') {
                q('INSERT INTO customers (name, whatsapp) VALUES (:n, :w)', ['n' => $namaBaru, 'w' => $waBaru ?: null]);
                $customerId = (int)db()->lastInsertId();
            }
            $c = qone('SELECT id FROM customers WHERE id = ?', [$customerId]);
            if (!$c) {
                throw new RuntimeException('Pelanggan belum dipilih.');
            }
            $rentDate = post_date('rent_date') ?: date('Y-m-d');
            $pickupDate = post_date('pickup_date') ?: $rentDate;
            $returnDate = post_date('return_date');
            $code = next_code('rentals', 'YG-INV-');
            q(
                'INSERT INTO rentals (code, customer_id, user_id, rent_date, pickup_date, return_date, due_date,
                    deposit, discount, extra_fee, payment_method, status, notes)
                 VALUES (:code, :cid, :uid, :rent, :pickup, :ret, :due, :dep, :disc, :extra, :method, :status, :notes)',
                [
                    'code'   => $code,
                    'cid'    => $customerId,
                    'uid'    => current_user()['id'],
                    'rent'   => $rentDate,
                    'pickup' => $pickupDate,
                    'ret'    => $returnDate,
                    'due'    => post_date('due_date') ?: $returnDate,
                    'dep'    => post_amount('deposit'),
                    'disc'   => post_amount('discount'),
                    'extra'  => post_amount('extra_fee'),
                    'method' => post_enum('payment_method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'),
                    'status' => $pickupDate <= date('Y-m-d') ? 'disewa' : 'booking',
                    'notes'  => post('notes') ?: null,
                ]
            );
            $rentalId = (int)db()->lastInsertId();
            foreach ($items as $dressId) {
                $d = qone('SELECT rent_price FROM dresses WHERE id = ?', [$dressId]);
                if ($d) {
                    q('INSERT INTO rental_items (rental_id, dress_id, price) VALUES (?, ?, ?)', [$rentalId, $dressId, $d['rent_price']]);
                }
            }
            rental_refresh_totals($rentalId);
            $statusBaju = $pickupDate <= date('Y-m-d') ? 'disewa' : 'booking';
            q('UPDATE dresses SET status = :s WHERE id IN (' . implode(',', $items) . ')', ['s' => $statusBaju]);
            $dp = post_amount('dp');
            if ($dp > 0) {
                q(
                    'INSERT INTO incomes (date, category, rental_id, customer_id, description, amount, method)
                     VALUES (:d, "dp", :rid, :cid, :desc, :amt, :m)',
                    [
                        'd' => $rentDate, 'rid' => $rentalId, 'cid' => $customerId,
                        'desc' => 'DP sewa ' . $code, 'amt' => $dp,
                        'm' => post_enum('payment_method', ['cash', 'transfer', 'qris', 'lainnya'], 'cash'),
                    ]
                );
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            if ($e instanceof RuntimeException) {
                flash_set('error', $e->getMessage());
            } else {
                flash_set('error', 'Gagal menyimpan transaksi: ' . $e->getMessage());
            }
            redirect('sewa_form.php');
        }
        flash_set('ok', 'Transaksi ' . $code . ' tersimpan.');
        redirect('sewa_detail.php?id=' . $rentalId);
    }
    flash_set('error', implode(' ', $err));
    redirect('sewa_form.php');
}

page_start('Buat Penyewaan', 'sewa.php');
render_flash();
?>
<?php if (!$tersedia): ?>
    <?= empty_state('Tidak ada baju yang tersedia untuk disewakan. Tambah atau kosongkan koleksi terlebih dahulu.', 'Ke koleksi baju', 'koleksi.php') ?>
<?php else: ?>
    <form method="post" action="sewa_form.php">
        <?= csrf_field() ?>
        <div class="cols" style="max-width:980px">
            <fieldset>
                <legend>Pelanggan</legend>
                <div class="form-grid">
                    <div>
                        <label for="customer_id">Pilih pelanggan terdaftar</label>
                        <select id="customer_id" name="customer_id" required>
                            <option value="">Pilih pelanggan</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $prefillCustomer === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?><?= $c['whatsapp'] ? ' (' . e($c['whatsapp']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="hint">Belum terdaftar? Isi panel di bawah, pelanggan baru dibuat otomatis.</p>
                    </div>
                </div>
                <details style="margin-top:12px">
                    <summary style="cursor:pointer;font-weight:700;font-size:14px">Atau buat pelanggan baru</summary>
                    <div class="form-grid" style="margin-top:12px">
                        <div>
                            <label for="new_nama">Nama pelanggan baru</label>
                            <input type="text" id="new_nama" name="new_nama" placeholder="Nama lengkap">
                        </div>
                        <div>
                            <label for="new_whatsapp">WhatsApp baru</label>
                            <input type="text" id="new_whatsapp" name="new_whatsapp" inputmode="tel" placeholder="mis. 081234567890">
                        </div>
                    </div>
                </details>
            </fieldset>

            <fieldset>
                <legend>Tanggal</legend>
                <div class="form-grid">
                    <div>
                        <label for="rent_date">Tanggal penyewaan</label>
                        <input type="date" id="rent_date" name="rent_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label for="pickup_date">Tanggal pengambilan</label>
                        <input type="date" id="pickup_date" name="pickup_date" value="<?= date('Y-m-d') ?>">
                        <p class="hint">Jika tanggal ambil sudah lewat, status langsung Disewa.</p>
                    </div>
                    <div>
                        <label for="return_date">Tanggal pengembalian</label>
                        <input type="date" id="return_date" name="return_date" required value="<?= date('Y-m-d', strtotime('+2 day')) ?>">
                    </div>
                    <div>
                        <label for="due_date">Jatuh tempo pembayaran</label>
                        <input type="date" id="due_date" name="due_date">
                        <p class="hint">Kosongkan untuk mengikuti tanggal pengembalian.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Pilih baju (<?= count($tersedia) ?> tersedia)</legend>
                <div class="form-grid" style="grid-template-columns:1fr 220px">
                    <div>
                        <label for="pickSearch">Cari baju</label>
                        <input type="text" id="pickSearch" placeholder="Nama atau kode baju">
                    </div>
                    <div>
                        <label for="pickCat">Kategori</label>
                        <select id="pickCat">
                            <option value="">Semua</option>
                            <option value="dress">Dress</option>
                            <option value="kebaya">Kebaya</option>
                            <option value="gaun">Gaun</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="pickgrid" style="margin-top:12px">
                    <?php foreach ($tersedia as $d): ?>
                        <label class="pick" data-search="<?= e($d['code'] . ' ' . $d['name']) ?>" data-cat="<?= e($d['category']) ?>">
                            <input type="checkbox" name="items[]" value="<?= (int)$d['id'] ?>" data-price="<?= (int)$d['rent_price'] ?>">
                            <span class="pick-body">
                                <?php if ($d['photo']): ?>
                                    <img src="<?= e($d['photo']) ?>" alt="Foto <?= e($d['name']) ?>" width="160" height="160" loading="lazy">
                                <?php else: ?>
                                    <span class="pick-noimg">Belum ada foto</span>
                                <?php endif; ?>
                                <span class="pick-name"><?= e($d['name']) ?></span>
                                <span class="pick-meta"><?= e($d['code']) ?> · <?= e($d['size'] ?: '-') ?> · <?= e($d['color'] ?: '-') ?></span>
                                <span class="dresscard-price"><?= rupiah($d['rent_price']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Biaya dan pembayaran</legend>
                <div class="form-grid">
                    <div>
                        <label for="deposit">Deposit / jaminan (Rp)</label>
                        <input type="text" id="deposit" name="deposit" inputmode="numeric" value="<?= angka($s['default_deposit']) ?>">
                        <p class="hint">Tidak dihitung sebagai pendapatan, dikembalikan saat baju kembali.</p>
                    </div>
                    <div>
                        <label for="discount">Diskon (Rp)</label>
                        <input type="text" id="discount" name="discount" inputmode="numeric" value="0">
                    </div>
                    <div>
                        <label for="extra_fee">Ongkos tambahan (Rp)</label>
                        <input type="text" id="extra_fee" name="extra_fee" inputmode="numeric" value="0">
                        <p class="hint">mis. ongkir antar-jemput, ditambahkan ke total.</p>
                    </div>
                    <div>
                        <label for="dp">DP yang dibayar sekarang (Rp)</label>
                        <input type="text" id="dp" name="dp" inputmode="numeric" value="0">
                        <p class="hint">Langsung tercatat sebagai pemasukan DP.</p>
                    </div>
                    <div>
                        <label for="payment_method">Metode pembayaran</label>
                        <select id="payment_method" name="payment_method">
                            <option value="cash">Tunai</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="span-all">
                        <label for="notes">Catatan</label>
                        <textarea id="notes" name="notes" placeholder="mis. diambil sore, dipakai untuk lamaran"></textarea>
                    </div>
                </div>
            </fieldset>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan transaksi</button>
            <a class="btn btn-ghost" href="sewa.php">Batal</a>
        </div>
    </form>
<?php endif; ?>
<?php
page_end();
