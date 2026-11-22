-- YAYA Gallery: skema database (tanpa data contoh)
-- Import: C:\xampp\mysql\bin\mysql.exe -u root < database.sql
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS yaya_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yaya_gallery;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','karyawan') NOT NULL DEFAULT 'karyawan',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  business_name VARCHAR(100) NOT NULL DEFAULT 'YAYA Gallery',
  logo VARCHAR(255) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  late_fee_per_day DECIMAL(12,2) NOT NULL DEFAULT 0,
  default_deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
  invoice_notes TEXT
) ENGINE=InnoDB;

CREATE TABLE dresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category ENUM('dress','kebaya','gaun','lainnya') NOT NULL DEFAULT 'dress',
  size VARCHAR(20) DEFAULT NULL,
  color VARCHAR(50) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  rent_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  buy_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  purchase_date DATE DEFAULT NULL,
  item_condition ENUM('baik','perlu_perbaikan','rusak') NOT NULL DEFAULT 'baik',
  status ENUM('tersedia','booking','disewa','dicuci','perbaikan','tidak_tersedia') NOT NULL DEFAULT 'tersedia',
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  whatsapp VARCHAR(20) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rentals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  rent_date DATE NOT NULL,
  pickup_date DATE DEFAULT NULL,
  return_date DATE DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  actual_return_date DATE DEFAULT NULL,
  deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
  deposit_used TINYINT(1) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  extra_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  late_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  damage_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','transfer','qris','lainnya') NOT NULL DEFAULT 'cash',
  status ENUM('booking','disewa','dikembalikan','batal') NOT NULL DEFAULT 'booking',
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX (status), INDEX (rent_date), INDEX (return_date)
) ENGINE=InnoDB;

CREATE TABLE rental_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rental_id INT UNSIGNED NOT NULL,
  dress_id INT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  condition_on_return ENUM('baik','rusak','hilang') DEFAULT NULL,
  damage_note VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE,
  FOREIGN KEY (dress_id) REFERENCES dresses(id),
  INDEX (dress_id)
) ENGINE=InnoDB;

CREATE TABLE incomes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  category ENUM('dp','pelunasan','denda','kerusakan','lainnya') NOT NULL,
  rental_id INT UNSIGNED DEFAULT NULL,
  customer_id INT UNSIGNED DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  method ENUM('cash','transfer','qris','lainnya') NOT NULL DEFAULT 'cash',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE SET NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  INDEX (date), INDEX (rental_id)
) ENGINE=InnoDB;

CREATE TABLE debts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creditor_name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  debt_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE DEFAULT NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (due_date)
) ENGINE=InnoDB;

-- Pembayaran utang dicatat sebagai pengeluaran (kategori pembayaran_utang)
-- yang tertaut ke utangnya, supaya uang keluar dan sisa utang tidak dobel catat.
CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  category ENUM('pembelian_baju','laundry','perbaikan_baju','aksesoris','packaging','transportasi','listrik_internet','gaji','sewa_tempat','pembayaran_utang','lainnya') NOT NULL,
  utang_id INT UNSIGNED DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  method ENUM('cash','transfer','qris','lainnya') NOT NULL DEFAULT 'cash',
  receipt_photo VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utang_id) REFERENCES debts(id) ON DELETE SET NULL,
  INDEX (date), INDEX (utang_id)
) ENGINE=InnoDB;

INSERT INTO settings (id, business_name, invoice_notes)
VALUES (1, 'YAYA Gallery', 'Terima kasih telah menyewa di YAYA Gallery.');

-- Akun awal: username "admin", password "admin123". Segera ganti di menu Pengguna.
INSERT INTO users (name, username, password_hash, role)
VALUES ('Admin', 'admin', '$2y$12$jBGOcJQ8PGqHMwKpRVOHH.cKfv9vEcrdVbyRKraNAPwHZg1e7Mtx6', 'admin');
