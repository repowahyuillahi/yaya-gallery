<?php
require_once __DIR__ . '/layout.php';

require_login();

$type = (string)($_GET['type'] ?? '');
$dari = (string)($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string)($_GET['sampai'] ?? date('Y-m-d'));

$filename = 'laporan-' . $type . '-' . $dari . '-sd-' . $sampai . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar

$row = function (array $cells) use ($out) {
    fputcsv($out, $cells, ';');
};

switch ($type) {
    case 'pemasukan':
        $rows = qall(
            'SELECT i.date, i.category, i.description, i.amount, i.method, r.code, c.name AS customer
             FROM incomes i
             LEFT JOIN rentals r ON r.id = i.rental_id
             LEFT JOIN customers c ON c.id = i.customer_id
             WHERE i.date BETWEEN :dari AND :sampai ORDER BY i.date ASC',
            ['dari' => $dari, 'sampai' => $sampai]
        );
        $row(['Tanggal', 'Jenis', 'Keterangan', 'Transaksi', 'Pelanggan', 'Metode', 'Jumlah']);
        foreach ($rows as $r) {
            $row([$r['date'], $r['category'], $r['description'], $r['code'], $r['customer'], $r['method'], (float)$r['amount']]);
        }
        break;

    case 'pengeluaran':
        $rows = qall(
            'SELECT e.date, e.category, e.description, e.amount, e.method
             FROM expenses e WHERE e.date BETWEEN :dari AND :sampai ORDER BY e.date ASC',
            ['dari' => $dari, 'sampai' => $sampai]
        );
        $row(['Tanggal', 'Kategori', 'Keterangan', 'Metode', 'Jumlah']);
        foreach ($rows as $r) {
            $row([$r['date'], $r['category'], $r['description'], $r['method'], (float)$r['amount']]);
        }
        break;

    case 'harian':
        $rows = qall(
            'SELECT date, SUM(amount) total, COUNT(*) jml FROM incomes WHERE date BETWEEN :dari AND :sampai GROUP BY date ORDER BY date ASC',
            ['dari' => $dari, 'sampai' => $sampai]
        );
        $row(['Tanggal', 'Jumlah catatan', 'Total pemasukan']);
        foreach ($rows as $r) {
            $row([$r['date'], (int)$r['jml'], (float)$r['total']]);
        }
        break;

    case 'bulanan':
        $rows = qall(
            "SELECT DATE_FORMAT(date, '%Y-%m') ym,
                    SUM(CASE WHEN sumber = 'in' THEN amount ELSE 0 END) pemasukan,
                    SUM(CASE WHEN sumber = 'out' THEN amount ELSE 0 END) pengeluaran
             FROM (SELECT date, amount, 'in' sumber FROM incomes UNION ALL SELECT date, amount, 'out' sumber FROM expenses) t
             GROUP BY ym ORDER BY ym ASC"
        );
        $row(['Bulan', 'Pemasukan', 'Pengeluaran', 'Selisih']);
        foreach ($rows as $r) {
            $row([$r['ym'], (float)$r['pemasukan'], (float)$r['pengeluaran'], (float)$r['pemasukan'] - (float)$r['pengeluaran']]);
        }
        break;

    case 'piutang':
        $rows = qall(
            "SELECT r.code, c.name AS customer, r.total, COALESCE(p.paid,0) AS paid, COALESCE(r.due_date, r.return_date) AS jatuh_tempo
             FROM rentals r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN (SELECT rental_id, SUM(amount) paid FROM incomes GROUP BY rental_id) p ON p.rental_id = r.id
             WHERE r.status <> 'batal' AND r.total > COALESCE(p.paid,0)
             ORDER BY jatuh_tempo ASC"
        );
        $row(['Kode transaksi', 'Pelanggan', 'Total tagihan', 'Sudah dibayar', 'Sisa', 'Jatuh tempo']);
        foreach ($rows as $r) {
            $row([$r['code'], $r['customer'], (float)$r['total'], (float)$r['paid'], (float)$r['total'] - (float)$r['paid'], $r['jatuh_tempo']]);
        }
        break;

    case 'utang':
        $rows = qall(
            "SELECT d.creditor_name, d.description, d.amount, COALESCE(e.paid,0) AS paid, d.due_date
             FROM debts d
             LEFT JOIN (SELECT utang_id, SUM(amount) paid FROM expenses WHERE utang_id IS NOT NULL GROUP BY utang_id) e ON e.utang_id = d.id
             WHERE d.amount > COALESCE(e.paid,0)
             ORDER BY d.due_date ASC"
        );
        $row(['Pemberi utang', 'Keterangan', 'Nominal', 'Sudah dibayar', 'Sisa', 'Jatuh tempo']);
        foreach ($rows as $r) {
            $row([$r['creditor_name'], $r['description'], (float)$r['amount'], (float)$r['paid'], (float)$r['amount'] - (float)$r['paid'], $r['due_date']]);
        }
        break;

    case 'populer':
        $rows = qall(
            "SELECT d.code, d.name, COUNT(ri.id) jml, SUM(ri.price) pendapatan
             FROM rental_items ri JOIN dresses d ON d.id = ri.dress_id JOIN rentals r ON r.id = ri.rental_id
             WHERE r.status <> 'batal'
             GROUP BY d.id, d.code, d.name ORDER BY jml DESC"
        );
        $row(['Kode', 'Nama baju', 'Kali disewa', 'Pendapatan']);
        foreach ($rows as $r) {
            $row([$r['code'], $r['name'], (int)$r['jml'], (float)$r['pendapatan']]);
        }
        break;

    case 'profit':
        $rows = qall(
            "SELECT d.code, d.name, d.buy_price, COALESCE(SUM(ri.price),0) revenue
             FROM dresses d
             LEFT JOIN rental_items ri ON ri.dress_id = d.id
             LEFT JOIN rentals r ON r.id = ri.rental_id AND r.status <> 'batal'
             GROUP BY d.id, d.code, d.name, d.buy_price HAVING revenue > 0 ORDER BY revenue DESC"
        );
        $row(['Kode', 'Nama baju', 'Pendapatan sewa', 'Modal', 'Selisih']);
        foreach ($rows as $r) {
            $row([$r['code'], $r['name'], (float)$r['revenue'], (float)$r['buy_price'], (float)$r['revenue'] - (float)$r['buy_price']]);
        }
        break;

    default:
        $row(['Jenis laporan tidak dikenal.']);
}

fclose($out);
exit;
