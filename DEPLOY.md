# Panduan Deploy ke Hosting (cPanel)

Aplikasi ini PHP 8 + MySQL biasa, jadi bisa dipasang di hampir semua shared hosting
cPanel. Ada dua cara menghubungkan hosting dengan repo GitHub ini.

## Persiapan (sekali)

1. **Buat database** di cPanel > MySQL Databases:
   - nama database, mis. `user_yaya`
   - user + password database, lalu beri akses user ke database (ALL PRIVILEGES).
2. **Import skema** lewat cPanel > phpMyAdmin: pilih database, tab Import,
   unggah `database/database.sql`. (Opsional: `database/database_contoh.sql`
   untuk data demo; boleh dihapus setelahnya dari aplikasi.)
3. **Buat file `app/config.local.php`** (tidak ikut repo, jangan di-commit) berisi
   kredensial database hosting:

   ```php
   <?php
   $DB_HOST = 'localhost';
   $DB_NAME = 'user_yaya';
   $DB_USER = 'user_yaya';
   $DB_PASS = 'passwordDatabaseAnda';
   ```

   Jika file ini tidak ada, aplikasi memakai konfigurasi default XAMPP
   (`root` tanpa password) dan tidak akan bisa konek di hosting.

## Cara A: deploy dari GitHub lewat cPanel Git

1. Buka repo GitHub, klik **Code > Download ZIP** hanya jika ingin cara manual;
   untuk auto-deploy ikuti langkah berikut.
2. Di cPanel, buka **Git Version Control** (atau Terminal). Clone repo:

   ```
   git clone https://github.com/repowahyuillahi/yaya-gallery.git
   ```

   (via cPanel Git: Create, isi clone URL di atas, branch `main`.)
3. Aplikasi harus bisa diakses dari web. Pilih salah satu:
   - **Subdomain/domain khusus**: arahkan document root subdomain ke folder repo.
   - **Subfolder domain utama**: salin isi repo ke `public_html/yaya-gallery`.
     Gunakan fitur **Deploy** cPanel dengan file `.cpanel.yml` (contoh tersedia di
     `.cpanel.yml.example`: salin jadi `.cpanel.yml`, ganti `user` dan path tujuan
     sesuai akun Anda), atau salin manual lewat File Manager.
4. Pastikan folder `uploads/` bisa ditulis (default cPanel biasanya sudah; bila
   upload foto gagal, ubah izinnya ke 775 di File Manager).
5. Buka domain, login `admin` / `admin123`, lalu **segera ganti password** di menu
   Pengguna.

## Cara B: unduh ZIP dan unggah manual

1. GitHub > **Code > Download ZIP**, ekstrak di komputer.
2. Upload isi folder ke `public_html/yaya-gallery` lewat File Manager cPanel
   (atau FTP), termasuk folder `app/`, `pages/`, `assets/`, `database/`, `uploads/`.
3. Lanjut langkah 1-5 di bagian Persiapan.

## Update setelah ada perubahan

- Cara A: `git pull` di server (atau tombol *Pull & Deploy* di cPanel Git).
- Cara B: unggah ulang file yang berubah.

## Catatan penting

- Versi PHP minimal **8.0** (cek cPanel > MultiPHP Manager). Ekstensi yang perlu
  aktif: `pdo_mysql`, `fileinfo` (default aktif di hampir semua hosting).
- Jalur URL aplikasi dihitung otomatis, jadi boleh dipasang di root domain maupun
  subfolder seperti `domain.com/yaya-gallery/`.
- Jangan pernah commit `app/config.local.php`; file itu berisi password database.
- Kunci akun awal ada di `database/database.sql` (hash password `admin123`).
  Ini wajar untuk demo, tetapi ganti password sebelum dipakai sungguhan.
