# Panduan Deploy ke Hosting (cPanel)

Aplikasi ini PHP 8 + MySQL biasa, jadi bisa dipasang di hampir semua shared hosting
cPanel. Ada dua cara menghubungkan hosting dengan repo GitHub ini.

## Halaman produk di GitHub Pages (opsional)

GitHub Pages hanya menyajikan file statis: PHP dan MySQL tidak bisa jalan di sana,
jadi **aplikasi lengkap tidak bisa hidup di GitHub Pages**. Branch `gh-pages` di repo
ini berisi halaman produk statis (beranda dengan screenshot) untuk dibagikan.

1. Di GitHub, buka **Settings > Pages**.
2. **Build and deployment > Source**: pilih *Deploy from a branch*.
3. **Branch**: pilih `gh-pages`, folder `/(root)`, klik **Save**.
4. Halaman hidup di `https://repowahyuillahi.github.io/yaya-gallery/` dalam 1-2 menit.

Menjalankan ulang halaman produk setelah perubahan: salin `index.html` statis beserta
`assets/` ke branch `gh-pages`. Catatan: tombol "Masuk ke aplikasi" di halaman statis
masih menunjuk ke repo GitHub; setelah aplikasi live di hosting, ganti `href`-nya
(ada komentar penanda di `index.html` branch `gh-pages`).

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

## CI/CD dengan GitHub Actions

Repo ini sudah punya dua workflow di `.github/workflows/`:

- `ci.yml` — tiap push/PR ke `main`, lint semua file PHP (`php -l`) di PHP 8.1.
- `deploy.yml` — tiap push ke `main` (atau manual dari tab *Actions*), deploy ke
  server lewat SSH: `git pull` di folder target. Bisa dipantau di
  tab **Actions** repo.

### Setup sekali (di server)

1. Pastikan folder target sudah berisi clone repo ini, mis.
   `git clone https://github.com/repowahyuillahi/yaya-gallery.git /home/USER/public_html/yaya-gallery`.
2. Buat `app/config.local.php` di server seperti di bagian Persiapan (tapi di server,
   bukan di komputer lokal). File ini **tidak ikut repo** (ada di `.gitignore`),
   jadi `git pull` tidak akan menimpanya.
3. Buat user SSH khusus deploy (opsional tapi disarankan), mis. `deploy`, dan isi
   public key-nya di `~/.ssh/authorized_keys`.

### Secrets yang harus diisi di GitHub

Buka **Settings > Secrets and variables > Actions > New repository secret**, isi:

| Nama secret | Isi |
|---|---|
| `DEPLOY_HOST` | IP server (mis. `203.x.x.x`) atau panel aaPanel host |
| `DEPLOY_USER` | username SSH, mis. `root` atau user khusus |
| `DEPLOY_SSH_KEY` | isi **private key** SSH (`.pem`/key), bukan public key |
| `DEPLOY_PORT` | port SSH aaPanel (lihat langkah 1 di bawah, sering bukan 22) |
| `DEPLOY_PATH` | root folder site di aaPanel, mis. `/www/wwwroot/ymgallery/` |

Setelah itu, push ke `main` → workflow `Deploy` jalan otomatis.

> Catatan: `DEPLOY_PATH` tidak rahasia (path folder biasa), tapi disimpan sebagai
> secret supaya satu pintu. Kalau mau lebih transparan, pindahkan ke
> **Settings > Variables** dengan nama `DEPLOY_PATH` dan ganti di `deploy.yml`
> dari `${{ secrets.DEPLOY_PATH }}` ke `${{ vars.DEPLOY_PATH }}`.

## Server aaPanel

Pipeline-nya sama dengan cPanel (SSH + `git pull`), hanya beda cara menyiapkan
SSH di server dan path folder site. Langkah di aaPanel:

1. **Cek port SSH aaPanel.** aaPanel sering mengaktifkan SSH di port non-22
   (mis. `62222` atau port yang kamu set saat install). Di panel aaPanel, buka
   **Terminal** / bagian SSH, atau `ssh -p PORT user@IP`. Catat port-nya → isi ke
   secret `DEPLOY_PORT`. Kalau port-nya memang 22, bisa dikosongkan.
2. **Buat site + folder.** Di aaPanel: **Websites > Create Site**, isi domain,
   root folder default `/www/wwwroot/<nama-site>/`. Folder ini yang jadi
   `DEPLOY_PATH`.
3. **Clone repo ke folder site (sekali).** Lewat **Terminal** aaPanel atau SSH:

   ```
   cd /www/wwwroot/<nama-site>
   git clone https://github.com/repowahyuillahi/yaya-gallery.git .
   ```

   (titik `.` di akhir = clone isi langsung ke folder site, bukan subfolder.)
4. **PHP & ekstensi.** Di aaPanel: **Software Store**, pasang PHP 8.x, pastikan
   ekstensi `pdo_mysql` dan `fileinfo` aktif (default aktif).
5. **Buat database + import.** **Databases > Create MySQL Database**
   (nama mis. `yaya_gallery`), lalu import `database/database.sql` lewat tab
   import, atau:

   ```
   mysql -u root -p < database/database.sql
   ```
6. **Buat `app/config.local.php`** di folder site (sesuai bagian Persiapan):

   ```php
   <?php
   $DB_HOST = 'localhost';
   $DB_NAME = 'yaya_gallery';
   $DB_USER = 'userDB';
   $DB_PASS = 'passDB';
   ```

   File ini tidak ikut repo, jadi aman dari `git pull`.
7. **Hak akses folder.** aaPanel umumnya sudah membuat folder site writable oleh
   user web (`www`). Jika upload foto gagal, set izin folder `uploads/` ke 755
   dan pastikan owner `www:www` (via File Manager aaPanel).
8. **Isi Secrets di GitHub** sesuai tabel di atas, lalu push ke `main`.
9. **Cek hasil** di domain site. Login awal `admin` / `admin123`, segera ganti.

> Jika aaPanel memakai user web khusus (mis. `www`), `git pull` yang dijalankan
> oleh user SSH harus bisa menulis ke folder site. Pastikan user SSH punya akses
> (owner/same group dengan folder site). Biasanya `root` sudah bisa.

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
