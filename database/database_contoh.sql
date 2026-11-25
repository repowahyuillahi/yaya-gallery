-- Data contoh YAYA Gallery (opsional): untuk melihat aplikasi berjalan dengan isi.
-- Hanya aman di database yang benar-benar kosong (mengacu ID 1..4). Baju/pelanggan
-- yang sudah bertransaksi tidak bisa dihapus lewat aplikasi; reset total dengan drop
-- database lalu import ulang.
-- Import SETELAH database.sql: C:\xampp\mysql\bin\mysql.exe -u root yaya_gallery < database_contoh.sql
USE yaya_gallery;

INSERT INTO dresses (code, name, category, size, color, rent_price, buy_price, purchase_date, item_condition, status) VALUES
('YG-0001', 'Kebaya Modern Merah', 'kebaya', 'M', 'Merah', 150000, 450000, CURDATE() - INTERVAL 90 DAY, 'baik', 'tersedia'),
('YG-0002', 'Dress Pesta Cocktail Hitam', 'dress', 'S', 'Hitam', 200000, 650000, CURDATE() - INTERVAL 75 DAY, 'baik', 'disewa'),
('YG-0003', 'Gaun Malam Biru Outer', 'gaun', 'L', 'Navy', 250000, 800000, CURDATE() - INTERVAL 60 DAY, 'baik', 'disewa'),
('YG-0004', 'Dress Kasual Floral', 'dress', 'M', 'Krem', 120000, 350000, CURDATE() - INTERVAL 50 DAY, 'baik', 'dicuci'),
('YG-0005', 'Gaun Pernikahan Putih', 'gaun', 'M', 'Putih', 500000, 2500000, CURDATE() - INTERVAL 45 DAY, 'baik', 'tersedia'),
('YG-0006', 'Kebaya Hijab Navy', 'kebaya', 'M', 'Navy', 175000, 550000, CURDATE() - INTERVAL 30 DAY, 'perlu_perbaikan', 'perbaikan');

INSERT INTO customers (name, whatsapp, address, notes) VALUES
('Siti Rahma', '6281234567890', 'Jl. Melati No. 12', 'Sewa untuk kondangan'),
('Dewi Lestari', '6281298765432', 'Perum Griya Asri B2', 'Pelanggan langganan'),
('Ayu Putri', '6281277665544', NULL, NULL);

INSERT INTO rentals (code, customer_id, rent_date, pickup_date, return_date, due_date, deposit, subtotal, total, payment_method, status) VALUES
('YG-INV-0001', 1, CURDATE() - INTERVAL 2 DAY, CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 3 DAY, CURDATE() + INTERVAL 3 DAY, 100000, 200000, 200000, 'cash', 'disewa'),
('YG-INV-0002', 2, CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 38 DAY, CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 35 DAY, 50000, 270000, 250000, 'transfer', 'dikembalikan'),
('YG-INV-0003', 3, CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 9 DAY, CURDATE() - INTERVAL 2 DAY, CURDATE() - INTERVAL 2 DAY, 0, 250000, 250000, 'qris', 'disewa');

UPDATE rentals SET actual_return_date = CURDATE() - INTERVAL 35 DAY, deposit_used = 1 WHERE code = 'YG-INV-0002';

INSERT INTO rental_items (rental_id, dress_id, price, condition_on_return) VALUES
(1, 2, 200000, NULL),
(2, 1, 150000, 'baik'),
(2, 4, 120000, 'baik'),
(3, 3, 250000, NULL);

INSERT INTO incomes (date, category, rental_id, customer_id, description, amount, method) VALUES
(CURDATE() - INTERVAL 2 DAY, 'dp', 1, 1, 'DP sewa YG-INV-0001', 100000, 'cash'),
(CURDATE() - INTERVAL 40 DAY, 'dp', 2, 2, 'DP sewa YG-INV-0002', 100000, 'transfer'),
(CURDATE() - INTERVAL 35 DAY, 'pelunasan', 2, 2, 'Pelunasan YG-INV-0002 (termasuk deposit Rp 50.000)', 150000, 'transfer');

INSERT INTO debts (creditor_name, description, debt_date, amount, due_date) VALUES
('Toko Kain Melati', 'Pembelian 3 kebaya baru', CURDATE() - INTERVAL 30 DAY, 1500000, CURDATE() + INTERVAL 20 DAY);

INSERT INTO expenses (date, category, utang_id, description, amount, method) VALUES
(CURDATE() - INTERVAL 20 DAY, 'listrik_internet', NULL, 'Listrik dan internet bulanan', 300000, 'transfer'),
(CURDATE() - INTERVAL 10 DAY, 'pembayaran_utang', 1, 'Pembayaran utang Toko Kain Melati', 500000, 'transfer'),
(CURDATE() - INTERVAL 5 DAY, 'laundry', NULL, 'Laundry 4 set baju sewa', 85000, 'cash'),
(CURDATE() - INTERVAL 3 DAY, 'transportasi', NULL, 'Antar-jemput baju ke pelanggan', 50000, 'cash');
