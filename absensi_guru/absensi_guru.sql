-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 06 Apr 2026 pada 09.35
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi_guru`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `status` enum('hadir','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
  `jam_masuk` time DEFAULT NULL,
  `keterangan_masuk` enum('Tepat Waktu','Terlambat') DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `lat_masuk` decimal(10,7) DEFAULT NULL,
  `lng_masuk` decimal(10,7) DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `keterangan_pulang` enum('Tepat Waktu','Lebih Awal') DEFAULT NULL,
  `foto_pulang` varchar(255) DEFAULT NULL,
  `lat_pulang` decimal(10,7) DEFAULT NULL,
  `lng_pulang` decimal(10,7) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `klarifikasi_alasan` text DEFAULT NULL,
  `klarifikasi_bukti` varchar(255) DEFAULT NULL,
  `klarifikasi_status` enum('pending','approved','rejected') DEFAULT NULL,
  `klarifikasi_at` timestamp NULL DEFAULT NULL,
  `klarifikasi_catatan_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `guru_id`, `tanggal`, `status`, `jam_masuk`, `keterangan_masuk`, `foto_masuk`, `lat_masuk`, `lng_masuk`, `jam_pulang`, `keterangan_pulang`, `foto_pulang`, `lat_pulang`, `lng_pulang`, `keterangan`, `bukti_file`, `klarifikasi_alasan`, `klarifikasi_bukti`, `klarifikasi_status`, `klarifikasi_at`, `klarifikasi_catatan_admin`, `created_at`) VALUES
(10, 14, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:29'),
(11, 6, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(12, 17, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(13, 9, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(14, 15, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(15, 5, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(16, 11, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(17, 4, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(18, 12, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(19, 7, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(20, 13, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(21, 10, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(22, 16, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30'),
(23, 8, '2026-04-06', 'alpha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 07:30:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `nama`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '2026-04-04 03:54:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nip` varchar(30) NOT NULL,
  `sk` varchar(50) DEFAULT NULL,
  `spmt` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id`, `nama`, `nip`, `sk`, `spmt`, `password`, `created_at`) VALUES
(4, 'AGUS ABDUL GOFUR, S.Pd', '199108062025211119', '620 Tahun 2025', '800.1.13.2/20810-Dindikbud/2025', '$2y$10$sF4ooNwCX.mA.uMmgGZ.UOrse96OCczKWVu9nuSltkrwcWQpR5eJO', '2026-04-06 07:28:25'),
(5, 'AHMAD FAOZI, S.Pd', '198911212025211113', '620 Tahun 2025', '800.1.13.2/20811-Dindikbud/2025', '$2y$10$v0nX8iCBukcExnwvD7S3XeUvvP6h6VFXsEd4BOA5OVyJcffsYW8pC', '2026-04-06 07:28:25'),
(6, 'ALI ROHMAN, S.Ag', '197102062025211035', '620 Tahun 2025', '800.1.13.2/20812-Dindikbud/2025', '$2y$10$QDxI8P1yNQVY.NDTIydfh.vE.k31gBIpdKz0oYqIaekbYJi0HeHNS', '2026-04-06 07:28:26'),
(7, 'AMELIA PRATIWI, S.Pd', '199305282025212173', '620 Tahun 2025', '800.1.13.2/20807-Dindikbud/2025', '$2y$10$RRgGQlt7o5Ng2k7g5lL/GOw9pdBtxDQ5UI5oVtctbzOXAmBYd9jgq', '2026-04-06 07:28:26'),
(8, 'ARY CATUR PRIYANTO', '200101152025211041', '620 Tahun 2025', '800.1.13.2/20813-Dindikbud/2025', '$2y$10$ubySXONDro/GZZW7aKzUR.ClWSNxUBkGodJAuopwRO.vxOwM8ez3S', '2026-04-06 07:28:26'),
(9, 'BUNAJAT TOHIR, S.Pd', '198709272025211083', '620 Tahun 2025', '800.1.13.2/20668-Dindikbud/2025', '$2y$10$Kt2YcgT4FYhnPoHQMbho9uiE6VZiCpO7szXxITxvAobLy/IGzichO', '2026-04-06 07:28:27'),
(10, 'NENEG KIKI, S.Pd', '199510082025212163', '620 Tahun 2025', '800.1.13.2/20808-Dindikbud/2025', '$2y$10$czmMUu4oXWGsTxyPtmOoHeyY104dDf4CBzCqxNQeHpPtVfNVvJ4ti', '2026-04-06 07:28:27'),
(11, 'NINING NURHAYATI, S.Pd', '199006122025212220', '620 Tahun 2025', '800.1.13.2/20806-Dindikbud/2025', '$2y$10$WvZBt84IqR3BE1hdIKNbGuohIPgrB5Xm4iln7X2eCjEGZ1pr3Mqtq', '2026-04-06 07:28:28'),
(12, 'SEPTIAN HERNAWAN, SE', '199109152025211099', '620 Tahun 2025', '800.1.13.2/20667-Dindikbud/2025', '$2y$10$MsdO1HmPBJv0uAGhx5V2eeGShca00LKsOCFs0fINlR7vJGzDjXbjm', '2026-04-06 07:28:28'),
(13, 'SITI WIDIASTUTI PUJIASWATI, S.Pd', '199505182025212080', '620 Tahun 2025', '800.1.13.2/20666-Dindikbud/2025', '$2y$10$pwEKp.mwh/u/udmdknqFd.ruZFmlSnHVUxYCdExew0NuXm7zCIYVe', '2026-04-06 07:28:29'),
(14, 'ENDANG', '196909142025211027', '620 Tahun 2025', '800.1.13.2/19767-Dindikbud/2025', '$2y$10$FHjBUKUtOF/Fyg95jBMCcexJ5kkwA1IH/OMq6Q1ndErrAZGqIs1Me', '2026-04-06 07:28:29'),
(15, 'HARDINATA', '198807222025211075', '620 Tahun 2025', '800.1.13.2/19768-Dindikbud/2025', '$2y$10$2EZxJgJx1Cj6eC8IpiP.0OgsNTPInMJVIlngJm1JNRO/JWk2HSyae', '2026-04-06 07:28:29'),
(16, 'NURHOLIS MAJID, MM', '199906102025211049', '620 Tahun 2025', '800.1.13.2/19765-Dindikbud/2025', '$2y$10$TLDG5j13vTKNGIZRigVYCO95QSTFCoJxVqxBLW0cHiLE6j.kgcmja', '2026-04-06 07:28:30'),
(17, 'SAYATI', '198306282025212053', '620 Tahun 2025', '800.1.13.2/19766-Dindikbud/2025', '$2y$10$5pBOhTVbCdzMih1EcT1nHOX/xIGvCW.jNJYGFY77dFRE1Xc3yrFb.', '2026-04-06 07:28:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `izin_absen`
--

CREATE TABLE `izin_absen` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `dibuat_oleh` int(11) NOT NULL COMMENT 'admin.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `izin_absen`
--

INSERT INTO `izin_absen` (`id`, `guru_id`, `tanggal`, `dibuat_oleh`, `created_at`) VALUES
(4, 4, '2026-04-06', 1, '2026-04-06 07:29:00'),
(6, 5, '2026-04-06', 1, '2026-04-06 07:29:01'),
(7, 6, '2026-04-06', 1, '2026-04-06 07:29:01'),
(8, 7, '2026-04-06', 1, '2026-04-06 07:29:01'),
(9, 8, '2026-04-06', 1, '2026-04-06 07:29:01'),
(11, 9, '2026-04-06', 1, '2026-04-06 07:29:02'),
(12, 14, '2026-04-06', 1, '2026-04-06 07:29:02'),
(13, 15, '2026-04-06', 1, '2026-04-06 07:29:02'),
(14, 10, '2026-04-06', 1, '2026-04-06 07:29:02'),
(15, 11, '2026-04-06', 1, '2026-04-06 07:29:02'),
(16, 16, '2026-04-06', 1, '2026-04-06 07:29:02'),
(17, 17, '2026-04-06', 1, '2026-04-06 07:29:02'),
(18, 12, '2026-04-06', 1, '2026-04-06 07:29:02'),
(20, 13, '2026-04-06', 1, '2026-04-06 07:29:03');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_absensi` (`guru_id`,`tanggal`);

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`);

--
-- Indeks untuk tabel `izin_absen`
--
ALTER TABLE `izin_absen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_izin` (`guru_id`,`tanggal`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `izin_absen`
--
ALTER TABLE `izin_absen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `izin_absen`
--
ALTER TABLE `izin_absen`
  ADD CONSTRAINT `izin_absen_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
