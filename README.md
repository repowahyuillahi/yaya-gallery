# YAYA Gallery: Rental Management System

Aplikasi manajemen rental baju dan keuangan untuk butik YAYA Gallery. Berjalan di XAMPP
(PHP 8 + MySQL/MariaDB), tanpa framework dan tanpa dependensi komposer. Panduan pemasangan
di hosting cPanel ada di [DEPLOY.md](DEPLOY.md).

## Menjalankan

1. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
2. Import skema database (sekali saja):

   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database/database.sql
   ```

3. (Opsional) Isi data contoh supaya aplikasi langsung terlihat berisi. Data contoh
   hanya aman di database yang benar-benar kosong (mengacu ID 1..4). Baju dan pelanggan
   yang sudah bertransaksi tidak bisa dihapus lewat aplikasi, hanya bisa dibatalkan atau
   diubah statusnya; untuk reset total, drop database lalu import ulang:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root yaya_gallery < database/database_contoh.sql
   ```

4. Buka `http://localhost/yaya-gallery/` (halaman utama aplikasi).
5. Login awal: username **admin**, password **admin123**. Segera ganti password di
   menu **Pengguna**.

## Akun dan hak akses

- **Admin**: semua menu, termasuk Pengaturan, Pengguna, dan menghapus data.
- **Karyawan**: operasional harian dan pencatatan keuangan, tanpa hak hapus dan
  tanpa menu sistem.

## Catatan penting

- **Foto dan bukti** disimpan di folder `uploads/`. Pastikan folder ini bisa ditulis
  oleh PHP (default XAMPP di Windows sudah bisa).
- **Deposit** tidak dihitung sebagai pendapatan. Saat proses pengembalian ada
  pilihan memakai deposit untuk pelunasan.
- **Utang**: pembayaran utang otomatis tercatat sebagai pengeluaran kategori
  "Pembayaran utang" yang tertaut ke utangnya, jadi uang keluar dan sisa utang
  tidak dobel catat. Pembelian barang secara tunai cukup dicatat sebagai pengeluaran
  biasa, bukan utang.
- **Piutang** dihitung langsung dari sisa tagihan tiap transaksi sewa, sehingga
  selalu konsisten dengan data penyewaan.
- **Ekspor**: tombol "Unduh Excel (CSV)" menghasilkan file CSV dengan pemisah `;`
  yang langsung terbuka di Excel. Tombol "Cetak / PDF" memakai dialog cetak browser
  (pilih "Save as PDF").
- **Invoice WhatsApp** membuka wa.me dengan pesan rincian invoice yang sudah terisi;
  nomor di halaman Pelanggan sebaiknya dalam format 08xx atau 62xx.

## Struktur

```
index.php, login.php, logout.php   titik masuk (beranda, sesi)
app/
  config.php                       koneksi DB, helper, jalur URL
  config.local.php                 kredensial hosting (tidak ikut repo)
  auth.php                         sesi login dan hak akses
  layout.php                       kerangka UI (sidebar, topbar, badge)
pages/
  dashboard.php                    ringkasan bisnis
  koleksi.php                      inventaris baju + foto
  pelanggan.php                    database pelanggan + riwayat
  sewa.php                         daftar transaksi
  sewa_form.php                    form transaksi baru
  sewa_detail.php                  detail, pembayaran, batalkan
  invoice.php                      invoice cetak/PDF
  pengembalian.php                 proses retur, laundry, perbaikan
  piutang.php                      tagihan pelanggan
  utang.php                        utang usaha + pembayaran
  pemasukan.php                    uang masuk + pendapatan lainnya
  pengeluaran.php                  biaya operasional + bukti foto
  laporan.php                      laporan keuangan
  laporan_export.php               ekspor CSV
  pengaturan.php                   profil bisnis, denda, deposit, logo
  users.php                        manajemen akun
assets/                            CSS, JS, gambar
database/                          skema + data contoh
uploads/                           foto baju, bukti, logo (lokal)
```
