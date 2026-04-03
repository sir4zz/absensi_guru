-- ============================================
-- DATABASE: absensi_guru
-- Sistem Absensi Guru
-- ============================================

CREATE DATABASE IF NOT EXISTS absensi_guru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE absensi_guru;

-- ============================================
-- TABEL: guru
-- ============================================
CREATE TABLE IF NOT EXISTS guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nip VARCHAR(30) NOT NULL UNIQUE,
    sk VARCHAR(50) DEFAULT NULL,
    stmp VARCHAR(50) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- TABEL: absensi
-- ============================================
CREATE TABLE IF NOT EXISTS absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_id INT NOT NULL,
    tanggal DATE NOT NULL,
    status ENUM('hadir','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
    jam_masuk TIME DEFAULT NULL,
    keterangan_masuk ENUM('Tepat Waktu','Terlambat') DEFAULT NULL,
    foto_masuk VARCHAR(255) DEFAULT NULL,
    lat_masuk DECIMAL(10,7) DEFAULT NULL,
    lng_masuk DECIMAL(10,7) DEFAULT NULL,
    jam_pulang TIME DEFAULT NULL,
    keterangan_pulang ENUM('Tepat Waktu','Lebih Awal') DEFAULT NULL,
    foto_pulang VARCHAR(255) DEFAULT NULL,
    lat_pulang DECIMAL(10,7) DEFAULT NULL,
    lng_pulang DECIMAL(10,7) DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    bukti_file VARCHAR(255) DEFAULT NULL,
    klarifikasi_alasan TEXT DEFAULT NULL,
    klarifikasi_bukti VARCHAR(255) DEFAULT NULL,
    klarifikasi_status ENUM('pending','approved','rejected') DEFAULT NULL,
    klarifikasi_at TIMESTAMP NULL DEFAULT NULL,
    klarifikasi_catatan_admin TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    UNIQUE KEY unique_absensi (guru_id, tanggal)
) ENGINE=InnoDB;

-- ============================================
-- Jika database SUDAH ADA, jalankan ALTER ini di phpMyAdmin:
-- ============================================
-- Tambah status alpha + kolom klarifikasi (jalankan jika database sudah ada):
-- ALTER TABLE absensi MODIFY COLUMN status ENUM('hadir','izin','sakit','alpha') NOT NULL DEFAULT 'hadir';
-- ALTER TABLE absensi ADD COLUMN klarifikasi_alasan TEXT DEFAULT NULL AFTER bukti_file;
-- ALTER TABLE absensi ADD COLUMN klarifikasi_bukti VARCHAR(255) DEFAULT NULL AFTER klarifikasi_alasan;
-- ALTER TABLE absensi ADD COLUMN klarifikasi_status ENUM('pending','approved','rejected') DEFAULT NULL AFTER klarifikasi_bukti;
-- ALTER TABLE absensi ADD COLUMN klarifikasi_at TIMESTAMP NULL DEFAULT NULL AFTER klarifikasi_status;
-- ALTER TABLE absensi ADD COLUMN klarifikasi_catatan_admin TEXT DEFAULT NULL AFTER klarifikasi_at;

-- ALTER TABLE absensi ADD COLUMN status ENUM('hadir','izin','sakit') NOT NULL DEFAULT 'hadir' AFTER tanggal;
-- ALTER TABLE absensi ADD COLUMN keterangan TEXT DEFAULT NULL AFTER keterangan_pulang;
-- ALTER TABLE absensi ADD COLUMN bukti_file VARCHAR(255) DEFAULT NULL AFTER keterangan;
-- ALTER TABLE absensi ADD COLUMN keterangan_masuk ENUM('Tepat Waktu','Terlambat') DEFAULT NULL AFTER jam_masuk;
-- ALTER TABLE absensi ADD COLUMN keterangan_pulang ENUM('Tepat Waktu','Lebih Awal') DEFAULT NULL AFTER jam_pulang;
-- ALTER TABLE absensi ADD COLUMN foto_masuk VARCHAR(255) DEFAULT NULL AFTER keterangan_masuk;
-- ALTER TABLE absensi ADD COLUMN lat_masuk DECIMAL(10,7) DEFAULT NULL AFTER foto_masuk;
-- ALTER TABLE absensi ADD COLUMN lng_masuk DECIMAL(10,7) DEFAULT NULL AFTER lat_masuk;
-- ALTER TABLE absensi ADD COLUMN foto_pulang VARCHAR(255) DEFAULT NULL AFTER keterangan_pulang;
-- ALTER TABLE absensi ADD COLUMN lat_pulang DECIMAL(10,7) DEFAULT NULL AFTER foto_pulang;
-- ALTER TABLE absensi ADD COLUMN lng_pulang DECIMAL(10,7) DEFAULT NULL AFTER lat_pulang;

-- ============================================
-- TABEL: admin
-- ============================================
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- DATA AWAL: Admin default
-- Password: admin123
-- ============================================
INSERT INTO admin (username, password, nama) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');

-- ============================================
-- DATA CONTOH: Guru
-- Password semua: guru123
-- ============================================
INSERT INTO guru (nama, nip, sk, stmp, password) VALUES
('Budi Santoso, S.Pd', '198501012010011001', 'SK/2010/001', 'S1 Pendidikan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Siti Aminah, S.Pd', '198703152012012002', 'SK/2012/002', 'S1 Matematika', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Ahmad Fauzi, M.Pd', '199001202015011003', 'SK/2015/003', 'S2 IPA', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
