-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 05, 2025 at 07:05 PM
-- Server version: 8.0.30
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `payroll_absensi`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateRekapAbsensi` (IN `p_id_anggota` INT, IN `p_bulan` INT, IN `p_tahun` INT)   BEGIN
    INSERT INTO rekap_absensi 
        (id_anggota, bulan, tahun, total_hadir, total_izin, total_cuti, total_tanpa_keterangan, total_sakit)
    SELECT 
        id_anggota,
        MONTH(tanggal),
        YEAR(tanggal),
        SUM(status_kehadiran = 'hadir'),
        SUM(status_kehadiran = 'izin'),
        SUM(status_kehadiran = 'cuti'),
        SUM(status_kehadiran = 'tanpa_keterangan'),
        SUM(status_kehadiran = 'sakit')
    FROM absensi
    WHERE 
        id_anggota = p_id_anggota AND
        MONTH(tanggal) = p_bulan AND
        YEAR(tanggal) = p_tahun
    ON DUPLICATE KEY UPDATE
        total_hadir = VALUES(total_hadir),
        total_izin = VALUES(total_izin),
        total_cuti = VALUES(total_cuti),
        total_tanpa_keterangan = VALUES(total_tanpa_keterangan),
        total_sakit = VALUES(total_sakit);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateRekapMingguan` (IN `p_min_date` DATE, IN `p_max_date` DATE)   BEGIN
    /*
      Contoh logika:
      - Menghapus data rekap mingguan untuk periode yang terpengaruh,
      - Menghitung ulang rekap mingguan berdasarkan data absensi antara p_min_date dan p_max_date,
      - Mengelompokkan berdasarkan id_anggota, minggu ke berapa, dan tahun.
    */

    -- Hapus rekap mingguan lama (sesuaikan logika ini jika perlu)
    DELETE FROM rekap_mingguan 
    WHERE id_anggota IN (
        SELECT DISTINCT id_anggota 
        FROM absensi 
        WHERE tanggal BETWEEN p_min_date AND p_max_date
    );

    -- Masukkan data rekap baru
    INSERT INTO rekap_mingguan (id_anggota, minggu_ke, tahun, total_hadir, total_terlambat)
    SELECT 
        a.id_anggota,
        WEEK(a.tanggal, 1) AS minggu_ke, -- Menggunakan ISO week (opsional: sesuaikan jika perlu)
        YEAR(a.tanggal) AS tahun,
        SUM(a.status_kehadiran = 'hadir') AS total_hadir,
        SUM(a.terlambat) AS total_terlambat
    FROM absensi a
    WHERE a.tanggal BETWEEN p_min_date AND p_max_date
    GROUP BY a.id_anggota, WEEK(a.tanggal, 1), YEAR(a.tanggal)
    ON DUPLICATE KEY UPDATE 
        total_hadir = VALUES(total_hadir),
        total_terlambat = VALUES(total_terlambat);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jadwal` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jam_kerja` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `valid` tinyint(1) DEFAULT '0',
  `pin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `departemen` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lembur` tinyint(1) DEFAULT '0',
  `jam_masuk` time DEFAULT NULL,
  `scan_masuk` datetime DEFAULT NULL,
  `terlambat` tinyint(1) DEFAULT '0',
  `scan_istirahat_1` datetime DEFAULT NULL,
  `scan_istirahat_2` datetime DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `scan_pulang` datetime DEFAULT NULL,
  `jenis_absensi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '-',
  `status_kehadiran` enum('hadir','sakit','izin','cuti','tanpa_keterangan','libur') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'hadir',
  `id_anggota` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `tanggal`, `jadwal`, `jam_kerja`, `valid`, `pin`, `nip`, `nama`, `departemen`, `lembur`, `jam_masuk`, `scan_masuk`, `terlambat`, `scan_istirahat_1`, `scan_istirahat_2`, `jam_pulang`, `scan_pulang`, `jenis_absensi`, `status_kehadiran`, `id_anggota`) VALUES
(1, '2025-03-01', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2025-03-01 06:32:20', 0, '2025-03-01 00:00:00', '2025-03-01 00:00:00', '14:45:00', '2025-03-01 15:24:42', '-', 'hadir', 5),
(2, '2025-03-02', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2025-03-02 06:19:18', 0, '2025-03-02 00:00:00', '2025-03-02 00:00:00', '14:45:00', '2025-03-02 13:19:41', '-', 'hadir', 5),
(3, '2025-03-03', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2025-03-03 06:07:54', 0, '2025-03-03 00:00:00', '2025-03-03 00:00:00', '14:45:00', '2025-03-03 15:16:17', '-', 'hadir', 5),
(4, '2025-03-04', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2025-03-04 06:26:41', 0, '2025-03-04 00:00:00', '2025-03-04 00:00:00', '14:45:00', '2025-03-04 15:41:13', 'Bolos', 'tanpa_keterangan', 5),
(5, '2025-03-05', 'Guru', 'Jum\'at - Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2025-03-05 00:00:00', 0, '2025-03-05 00:00:00', '2025-03-05 00:00:00', '13:30:00', '2025-03-05 00:00:00', 'Izin', 'izin', 5),
(6, '2025-03-06', 'Guru', 'Libur Rutin', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '00:00:00', '2025-03-06 00:00:00', 0, '2025-03-06 00:00:00', '2025-03-06 00:00:00', '00:00:00', '2025-03-06 00:00:00', '-', 'hadir', 5),
(7, '2025-03-07', 'Guru', 'Libur Rutin', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '00:00:00', '2025-03-07 00:00:00', 0, '2025-03-07 00:00:00', '2025-03-07 00:00:00', '00:00:00', '2025-03-07 00:00:00', '-', 'hadir', 5);

-- --------------------------------------------------------

--
-- Table structure for table `anggota_sekolah`
--

CREATE TABLE `anggota_sekolah` (
  `id` int NOT NULL,
  `uid` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenjang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `job_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_kerja` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `join_start` date DEFAULT NULL,
  `masa_kerja_tahun` int DEFAULT NULL,
  `masa_kerja_bulan` int DEFAULT NULL,
  `masa_kerja_efektif` decimal(5,2) DEFAULT '0.00',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `jenis_kelamin` enum('L','P') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `usia` int DEFAULT NULL,
  `agama` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat_domisili` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `alamat_ktp` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `no_rekening` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_hp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pendidikan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_perkawinan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pasangan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jumlah_anak` int DEFAULT '0',
  `nama_anak_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `nama_anak_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `nama_anak_3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `salary_index_id` int DEFAULT NULL,
  `salary_index_level` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT '0.00',
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'default.jpg',
  `role` enum('P','TK','M') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_delete` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_sekolah`
--

INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `job_title`, `status_kerja`, `join_start`, `masa_kerja_tahun`, `masa_kerja_bulan`, `masa_kerja_efektif`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_pasangan`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `salary_index_level`, `gaji_pokok`, `foto_profil`, `role`, `is_delete`, `deleted_at`) VALUES
(1, 'G-001', '100001', 'e10adc3949ba59abbe56e057f20f883e', 'Ahmad Fauzi', 'SD', 'Guru Matematika', 'Tetap', '2023-01-27', 2, 1, 2.00, 'Berpengalaman mengajar matematika', 'L', '1980-01-15', 45, 'Islam', 'Jl. Melati No. 1', 'Jl. Melati No. 1', '1234567890', '081234567890', 'S1 Ilmu Komputer', 'Belum Menikah', 'ahmad.fauzi@example.com', 'Santi', 2, 'Buday', 'Siti', '', 1, 'Level 0', 3000000.00, '/payroll_absensi_v2/uploads/profile_pics/ahmad_fauzi_sd_p_1.jpg', 'P', 0, NULL),
(2, 'G-002', '100002', 'e10adc3949ba59abbe56e057f20f883e', 'Siti Rahma', 'SMP', 'Guru Fisika', 'Tetap', '2015-07-01', 10, 0, 9.00, 'Menyukai eksperimen fisika', 'P', '1985-05-10', 40, 'Islam', 'Jl. Kenanga No. 2', 'Jl. Kenanga No. 2', '098765', '081298765432', 'S1 Pendidikan', 'Menikah', 'siti.rahma@example.com', 'Andi Rahma', 1, 'Ayu', '', '', 3, 'Level 2', 5000000.00, '/payroll_absensi_v2/uploads/profile_pics/siti_rahma_smp_p_2.png', 'P', 0, NULL),
(3, 'G-003', '100003', 'e10adc3949ba59abbe56e057f20f883e', 'Budi Santoso', 'SMA', 'Guru Sejarah', 'Tetap', '2010-01-10', 15, 3, 15.00, 'Ahli sejarah Indonesia', 'L', '1975-12-25', 50, 'Kristen', 'Jl. Mawar No. 3', 'Jl. Mawar No. 3', '112233', '081345678901', 'S2 Pendidikan', 'Menikah', 'budi.santoso@example.com', '', 3, 'Tono', 'Rina', 'Dewi', 5, 'Level 4', 7000000.00, 'default.jpg', 'P', 0, NULL),
(4, 'G-004', '100004', 'e10adc3949ba59abbe56e057f20f883e', 'Rina Sari', 'SMK', 'Guru Bahasa', 'Tetap', '2012-03-15', 13, 0, 13.00, 'Mengajar dengan metode kreatif', 'P', '1982-07-20', 43, 'Islam', 'Jl. Melati No. 5', 'Jl. Melati No. 5', '445566', '081234000111', 'S1 Sastra', 'Menikah', 'rina.sari@example.com', 'Agus Sari', 1, 'Dewi', '', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'P', 0, NULL),
(5, 'G-005', '01011995', 'e10adc3949ba59abbe56e057f20f883e', 'Roosalin Chintia Dewi', 'TK', 'Wali Kelas TK', 'Tetap', '2016-08-01', 8, 7, 8.00, 'Wali kelas yang disiplin', 'L', '1983-11-30', 41, 'Islam', 'Jl. Pelita No. 3', 'Jl. Pelita No. 3', '667788', '081234112233', 'S1 Pendidikan', 'Menikah', 'dedi.prasetyo@example.com', '', 3, 'Sari', 'Agus', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P', 0, NULL),
(6, 'G-006', '100006', 'e10adc3949ba59abbe56e057f20f883e', 'Maya Putri', 'SMP', 'Wali Kelas 2A', 'Tetap', '2018-01-15', 7, 0, 7.00, 'Wali kelas kreatif', 'P', '1990-04-10', 35, 'Islam', 'Jl. Merdeka No. 4', 'Jl. Merdeka No. 4', '223344', '081234223344', 'S1 Pendidikan', 'Menikah', 'maya.putri@example.com', 'Budi Putri', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P', 0, NULL),
(7, 'G-007', '100007', 'e10adc3949ba59abbe56e057f20f883e', 'Fitriani', 'SMA', 'Wali Kelas 4 SMP Kelas 1', 'Tetap', '2014-05-01', 11, 2, 10.00, 'Wali kelas yang teliti', 'P', '1987-09-15', 38, 'Islam', 'Jl. Sejahtera No. 7', 'Jl. Sejahtera No. 7', '334455', '081234334455', 'S1 Pendidikan', 'Menikah', 'fitriani@example.com', '', 2, 'Agus', 'Siti', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P', 0, NULL),
(8, 'K-001', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Lestari', 'SMA', 'Tenaga Kependidikan Administrasi', 'Kontrak', '2025-01-01', 0, 1, 0.00, 'Staff administrasi yang efisien', 'P', '1993-08-15', 32, 'Islam', 'Jl. Pertiwi No. 4', 'Jl. Pertiwi No. 4', '556677', '081234556677', 'S1 Administrasi', 'Belum Menikah', 'dewi.lestari@example.com', '', 0, '', '', '', 1, 'Level 0', 3000000.00, '/payroll_absensi_v2/uploads/profile_pics/dewi_lestari_sma_tk_8.jpg', 'TK', 0, NULL),
(9, 'K-002', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Slamet Wijaya', 'SMK', 'Tenaga Kependidikan Operasional', 'Tetap', '2018-06-15', 7, 0, 6.00, 'Bertugas di operasional', 'L', '1988-03-05', 37, 'Islam', 'Jl. Industri No. 7', 'Jl. Industri No. 7', '778899', '081298778899', 'S1 Manajemen', 'Menikah', 'slamet.wijaya@example.com', 'Siti Wijaya', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'TK', 0, NULL),
(10, 'K-003', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Rizki Pratama', 'SMP', 'Tenaga Kependidikan Umum', 'Kontrak', '2022-01-01', 3, 1, 3.00, 'Staff pendukung operasional', 'L', '1998-11-12', 27, 'Islam', 'Jl. Sudirman No. 8', 'Jl. Sudirman No. 8', '889900', '081237889900', '', 'Belum Menikah', 'rizki.pratama@example.com', '', 0, '', '', '', 2, 'Level 1', 4000000.00, 'default.jpg', 'TK', 0, NULL),
(11, 'M-001', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Andini Permata', 'SMA', 'Kepala Sekolah SMA', 'Tetap', '2014-01-27', 11, 2, 11.00, 'Memimpin sekolah dengan visi', 'P', '1978-04-22', 47, 'Islam', 'Jl. Merdeka No. 10', 'Jl. Merdeka No. 10', '990011', '081290990011', 'S2 Kesenian', 'Menikah', 'andini.permata@example.com', 'Budi Permata', 2, 'Tina', 'Rina', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'P', 0, NULL),
(12, 'M-002', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Sie, Vincent Peter S.', 'SMA', 'Keuangan', 'Tetap', '2008-07-01', 16, 7, 16.67, 'Mengelola keuangan dengan transparansi', 'L', '1965-06-21', 60, 'Islam', 'Jl. Pendidikan No. 9', 'Jl. Pendidikan No. 9', '112233', '081298112233', 'S2 Teknologi Informasi', 'Menikah', 'joko.widodo@example.com', 'Iriana Widodo', 3, 'Gibran', 'Khalifah', 'Puan', 5, 'Level 4', 7000000.00, 'default.jpg', 'M', 0, NULL),
(13, 'M-003', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Sari Utami', 'SMA', 'SDM', 'Tetap', '2012-11-11', 12, 4, 12.33, 'Mengelola SDM dengan profesionalisme', 'P', '1982-02-28', 43, 'Kristen', 'Jl. Simpang Lima No. 5', 'Jl. Simpang Lima No. 56', '445577', '081298445577', 'S1 Akuntansi', 'Menikah', 'sari.utami@example.com', 'Agus Utomo', 2, 'Dina', 'Rini', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'M', 0, NULL),
(14, 'M-004', '300004', 'e10adc3949ba59abbe56e057f20f883e', 'Rudi Hartono', 'SMA', 'Superadmin', 'Tetap', '2010-01-01', 15, 2, 15.17, 'Administrator sistem IT sekolah', 'L', '1970-12-12', 54, 'Islam', '2A Jl. Empu Sendok Raya', '', '', '', 'D3 Akuntansi', 'Menikah', 'rudi.hartono@example.com', '', 0, '', '', '', 5, 'Level 4', 7000000.00, 'default.jpg', 'M', 0, NULL),
(16, 'AF292EA2', '100010', '$2y$10$O2Tba35mbtegduiW88Q7Tew8XVSm9e8poYmMHU7GG2bn/eyoq477i', 'Hizkia Fareza', 'TK', 'Guru Membaca', 'Tetap', '2025-03-24', 0, 0, 0.00, 'Mengajar membaca anak TK', 'L', '2025-03-24', 23, 'Katolik', '2A Jl. Empu Sendok Raya', '2A Jl. Empu Sendok Raya', '144345343', '082227863969', 'D3 Akuntansi', 'Belum Menikah', 'hizkia@gmail.com', '-', 0, '-', '-', '-', 1, 'Level 0', 3000000.00, 'default.jpg', 'P', 0, NULL),
(17, 'CC95288B', '100011', '$2y$10$C8eyrt1VfAV2j.IkvexqP.BxqtNqWV4w1U7nOhaWg0ezI4iQhac4S', 'Hendra Kurniawan', 'TK', 'Guru Balok', 'Tetap', '2025-03-24', 0, 0, 0.00, 'Mengajar kreativitas anak', 'L', '2001-05-06', 23, 'Katolik', 'Jalan Tuah', 'Jalan Tuah', '143453453', '082226544333', 'D3 Teknologi Informasi', 'Belum Menikah', 'hendra@gmail.com', '-', 0, '-', '-', '-', 1, 'Level 0', 3000000.00, 'default.jpg', 'P', 0, NULL),
(18, 'ABB41A60', '200010', '$2y$10$88G2FLci6/xMJbfvDwhy.uLvAOAMw51oAXM/8ZMew106Lx5Qi4CPa', 'Apin Upin', 'SD', 'Teknisi Kontrol Sistem', 'Tetap', '2025-03-24', 0, 0, 0.00, 'Mengatasi Error Sistem', 'L', '1990-01-24', 30, 'Hindu', 'Jalan Kedung', 'Jalan Kedung', '1454654564', '081234567890', '', 'Belum Menikah', '', '-', 0, '-', '-', '-', 1, 'Level 0', 3000000.00, 'default.jpg', 'TK', 1, '2025-03-25 21:44:45'),
(19, '339AAE5F', '100012', '$2y$10$Fpx3nYVDsj97bWXkp5g5duTy//TbppRdY9v20wXke9OU8zYykMCP.', 'Catherine Wong S', 'SMA', 'Guru Sejarah', 'Tetap', '2025-03-25', 0, 0, 0.00, 'Mengajar Sejarah Indonesia', 'P', '2005-06-29', 19, 'Katolik', 'Klipang Raya', 'Klipang Raya', '512443563', '08182344848', 'S1 Sejarah', 'Belum Menikah', 'cathiew@gmail.com', '-', 0, '-', '-', '-', 1, 'Level 0', 3000000.00, 'default.jpg', 'P', 0, NULL),
(20, 'M-005', '300005', 'e10adc3949ba59abbe56e057f20f883e', 'Diana Puspitasari', 'TK', 'Kepala Sekolah TK', 'Tetap', '2015-03-01', 10, 0, 10.00, 'Spesialis pendidikan anak usia dini', 'P', '1978-08-19', 46, 'Islam', 'Jl. Anggrek No. 12', 'Jl. Anggrek No. 12', '1122334455', '081112223344', 'S2 Pendidikan Anak', 'Menikah', 'diana.puspita@example.com', 'Bambang Puspito', 2, 'Rara', 'Dimas', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P', 0, NULL),
(21, 'M-006', '300006', 'e10adc3949ba59abbe56e057f20f883e', 'Hendra Kurniawan', 'SD', 'Kepala Sekolah SD', 'Tetap', '2010-06-15', 14, 9, 14.00, 'Penggagas program literasi sekolah', 'L', '1975-11-05', 49, 'Kristen', 'Jl. Pendidikan No. 45', 'Jl. Pendidikan No. 45', '5544332211', '081334445566', 'S2 Manajemen Pendidikan', 'Menikah', 'hendra.kurnia@example.com', 'Linda Wijaya', 3, 'Kevin', 'Salsa', 'Rafi', 4, 'Level 3', 6000000.00, 'default.jpg', 'P', 0, NULL),
(22, 'M-007', '300007', 'e10adc3949ba59abbe56e057f20f883e', 'Sri Wahyuni', 'SMP', 'Kepala Sekolah SMP', 'Tetap', '2013-02-20', 12, 1, 12.00, 'Penerapan kurikulum merdeka', 'P', '1980-04-30', 44, 'Islam', 'Jl. Cendrawasih No. 8', 'Jl. Cendrawasih No. 8', '6677889900', '081556677889', 'S2 Pendidikan Matematika', 'Menikah', 'sri.wahyuni@example.com', 'Ahmad Fauzi', 1, 'Budi', '', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'P', 0, NULL),
(23, 'M-008', '300008', 'e10adc3949ba59abbe56e057f20f883e', 'Rudi Hermawan', 'SMK 1', 'Kepala Sekolah SMK 1', 'Tetap', '2009-09-01', 15, 6, 15.00, 'Fokus pada link and match industri', 'L', '1972-12-12', 52, 'Katolik', 'Jl. Industri No. 22', 'Jl. Industri No. 22', '9988776655', '081778889900', 'S3 Teknik Mesin', 'Menikah', 'rudi.hermawan@example.com', 'Dewi Anggraeni', 2, 'Dika', 'Nina', '', 5, 'Level 4', 7000000.00, 'default.jpg', 'P', 0, NULL),
(24, 'M-009', '300009', 'e10adc3949ba59abbe56e057f20f883e', 'Lina Marlina', 'SMK 2', 'Kepala Sekolah SMK 2', 'Tetap', '2017-04-10', 7, 11, 7.00, 'Pengembang teaching factory', 'P', '1985-03-25', 39, 'Islam', 'Jl. Teknologi No. 15', 'Jl. Teknologi No. 15', '1234098765', '081990001122', 'S2 Elektro', 'Menikah', 'lina.marlina@example.com', 'Eko Prasetyo', 1, 'Luna', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P', 0, NULL),
(25, 'M-010', '300010', 'e10adc3949ba59abbe56e057f20f883e', 'Prof. Dr. Bambang Sutejo, M.Sc.', 'Universitas Stivera', 'Kepala Sekolah Universitas Stivera', 'Tetap', '2005-01-01', 20, 2, 20.00, 'Rektor berprestasi tingkat nasional', 'L', '1968-07-17', 56, 'Buddha', 'Jl. Kampus No. 1', 'Jl. Kampus No. 1', '1357924680', '081112223344', 'S3 Manajemen Pendidikan', 'Menikah', 'bambang.sutejo@stivera.ac.id', 'Diana Sutejo', 2, 'Adi', 'Rini', '', 5, 'Level 4', 7000000.00, 'default.jpg', 'P', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int UNSIGNED NOT NULL,
  `nip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_payheads`
--

CREATE TABLE `employee_payheads` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `id_payhead` int NOT NULL,
  `jenis` enum('earnings','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','revisi','final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `support_doc_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `upload_file_blob` mediumblob,
  `is_rapel` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_payheads`
--

INSERT INTO `employee_payheads` (`id`, `id_anggota`, `id_payhead`, `jenis`, `amount`, `status`, `remarks`, `support_doc_path`, `upload_file_blob`, `is_rapel`) VALUES
(1, 25, 3, NULL, 100000.00, 'draft', NULL, NULL, NULL, 0),
(2, 25, 5, NULL, 150000.00, 'draft', NULL, NULL, NULL, 0),
(3, 25, 4, NULL, 250000.00, 'draft', NULL, NULL, NULL, 0),
(4, 24, 3, NULL, 100000.00, 'draft', NULL, NULL, NULL, 1),
(5, 24, 6, NULL, 150000.00, 'draft', NULL, NULL, NULL, 0),
(6, 24, 4, NULL, 250000.00, 'draft', NULL, NULL, NULL, 0),
(7, 24, 1, NULL, 150000.00, 'draft', NULL, NULL, NULL, 0),
(8, 23, 3, 'earnings', 100000.00, 'draft', NULL, NULL, NULL, 1),
(9, 23, 5, 'deductions', 150000.00, 'draft', NULL, NULL, NULL, 0),
(10, 23, 6, 'earnings', 150000.00, 'draft', NULL, NULL, NULL, 0),
(11, 23, 1, 'earnings', 150000.00, 'draft', NULL, NULL, NULL, 0),
(12, 22, 3, 'earnings', 100000.00, 'draft', NULL, NULL, NULL, 0),
(13, 22, 6, 'earnings', 150000.00, 'draft', NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `gaji_pokok_roles`
--

CREATE TABLE `gaji_pokok_roles` (
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT '0.00',
  `pendidikan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gaji_pokok_roles`
--

INSERT INTO `gaji_pokok_roles` (`role`, `gaji_pokok`, `pendidikan`) VALUES
('guru', 5000000.00, ''),
('karyawan', 4000000.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `gaji_pokok_strata_guru`
--

CREATE TABLE `gaji_pokok_strata_guru` (
  `jenjang` varchar(10) NOT NULL,
  `strata` varchar(10) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gaji_pokok_strata_guru`
--

INSERT INTO `gaji_pokok_strata_guru` (`jenjang`, `strata`, `gaji_pokok`) VALUES
('SD', 'S1', 4500000.00),
('SD', 'S2', 5000000.00),
('SMA/SMK', 'S1', 5500000.00),
('SMA/SMK', 'S2', 6000000.00),
('SMA/SMK', 'S3', 7000000.00),
('SMP', 'S1', 5000000.00),
('SMP', 'S2', 5500000.00),
('TK', 'D3', 2500000.00),
('TK', 'S1', 4000000.00),
('TK', 'S2', 4500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `gaji_pokok_strata_karyawan`
--

CREATE TABLE `gaji_pokok_strata_karyawan` (
  `jenjang` varchar(10) NOT NULL,
  `strata` varchar(10) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gaji_pokok_strata_karyawan`
--

INSERT INTO `gaji_pokok_strata_karyawan` (`jenjang`, `strata`, `gaji_pokok`) VALUES
('SD', 'S1', 3700000.00),
('SMP', 'S2', 4700000.00),
('TK', 'D3', 3000000.00);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int NOT NULL,
  `holiday_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `holiday_desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('wajib','opsional') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`holiday_id`, `holiday_title`, `holiday_desc`, `holiday_date`, `holiday_type`) VALUES
(1, 'Natal 2025', 'Libur Natal', '2025-12-24', 'wajib'),
(2, 'Tahun Baru 2025', 'Libur Tahun Baru', '2025-01-01', 'opsional');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_piket`
--

CREATE TABLE `jadwal_piket` (
  `id_jadwal` int NOT NULL,
  `nip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_guru` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `waktu_piket` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tahun` int NOT NULL,
  `status` enum('pending','hadir','tidak hadir') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_piket`
--

INSERT INTO `jadwal_piket` (`id_jadwal`, `nip`, `nama_guru`, `waktu_piket`, `tanggal`, `bulan`, `tahun`, `status`) VALUES
(100, '100001', 'Ahmad Fauzi', '08:00:00', '2025-03-14', 'Maret', 2025, 'pending'),
(101, '100001', 'Ahmad Fauzi', '08:00:00', '2025-03-14', 'Maret', 2025, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `laporan_surat`
--

CREATE TABLE `laporan_surat` (
  `id` int NOT NULL,
  `id_pengirim` int NOT NULL,
  `id_penerima` int NOT NULL,
  `jenis_surat` enum('peringatan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'peringatan',
  `judul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `isi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_keluar` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('terkirim','dibaca') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'terkirim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laporan_surat`
--

INSERT INTO `laporan_surat` (`id`, `id_pengirim`, `id_penerima`, `jenis_surat`, `judul`, `isi`, `tanggal_keluar`, `status`) VALUES
(1, 13, 1, 'peringatan', 'Surat Peringatan 1', 'SP 1', '2025-02-27 11:32:52', 'dibaca'),
(2, 13, 1, 'peringatan', 'Laporan Kinerja', 'Selama semester ini, kinerja guru menunjukkan penurunan yang signifikan, ditandai dengan persiapan pembelajaran yang tidak maksimal, metode pengajaran yang monoton, serta kurangnya interaksi efektif dengan siswa yang mengakibatkan rendahnya partisipasi kelas dan pencapaian akademik yang jauh dari target; minimnya umpan balik konstruktif dan ketidakmampuan mengadaptasi materi ajar sesuai kebutuhan siswa semakin memperparah situasi, sehingga menuntut peningkatan profesionalisme dan komitmen untuk segera memperbaiki proses pembelajaran.', '2025-02-27 11:47:53', 'dibaca'),
(3, 13, 1, 'peringatan', 'Surat Peringatan 1', 'SP 1 Karena tidakan buruk', '2025-02-27 11:52:02', 'dibaca');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `role_target` enum('keuangan','superadmin','sdm','all') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notification_type` enum('info','warning','success','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'info',
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `priority` int DEFAULT '5' COMMENT 'Nilai prioritas; semakin kecil nilainya semakin tinggi prioritasnya',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'system' COMMENT 'Pengirim atau pembuat notifikasi, misalnya sistem atau admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payheads`
--

CREATE TABLE `payheads` (
  `id` int NOT NULL,
  `nama_payhead` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenis` enum('earnings','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `nominal` decimal(15,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payheads`
--

INSERT INTO `payheads` (`id`, `nama_payhead`, `jenis`, `deskripsi`, `nominal`) VALUES
(1, 'Tunjangan Tetap', 'earnings', 'Tunjangan dasar untuk guru/karyawan', 150000.00),
(2, 'Potongan Pajak', 'deductions', 'Potongan pajak penghasilan', 125000.00),
(3, 'Bonus Kinerja', 'earnings', 'Bonus berdasarkan kinerja', 100000.00),
(4, 'Potongan BPJS', 'deductions', 'Potongan BPJS Kesehatan', 250000.00),
(5, 'Koperasi', 'deductions', 'Iuran koperasi', 150000.00),
(6, 'Tunjangan Hari Raya', 'earnings', 'THR', 150000.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `id_rekap_absensi` int DEFAULT NULL,
  `bulan` int NOT NULL,
  `tahun` int NOT NULL,
  `gaji_pokok` decimal(15,2) DEFAULT NULL,
  `total_pendapatan` decimal(15,2) DEFAULT NULL,
  `total_potongan` decimal(15,2) DEFAULT NULL,
  `potongan_koperasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tgl_payroll` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `no_rekening` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` enum('draft','revisi','final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `potongan_koperasi`, `gaji_bersih`, `created_at`, `tgl_payroll`, `no_rekening`, `catatan`, `status`) VALUES
(1, 25, NULL, 3, 2025, 14000000.00, 0.00, 500000.00, 0.00, 13500000.00, '2025-04-05 16:14:44', '2025-04-05 16:14:00', '1357924680', '', 'draft'),
(2, 24, NULL, 3, 2025, 10000000.00, 0.00, 550000.00, 0.00, 9450000.00, '2025-04-05 17:12:59', '2025-04-05 17:12:00', '1234098765', '', 'draft'),
(3, 23, NULL, 3, 2025, 14000000.00, 300000.00, 150000.00, 0.00, 14150000.00, '2025-04-05 17:18:39', '2025-04-05 17:18:00', '9988776655', '', 'draft'),
(4, 22, NULL, 3, 2025, 12000000.00, 250000.00, 0.00, 0.00, 12250000.00, '2025-04-05 17:45:41', '2025-04-05 17:45:00', '6677889900', '', 'draft'),
(5, 22, 5, 3, 2025, 12000000.00, 250000.00, 0.00, 0.00, 12250000.00, '2025-04-05 17:56:29', '2025-04-06 00:56:00', '6677889900', '', 'final');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_detail`
--

CREATE TABLE `payroll_detail` (
  `id` int NOT NULL,
  `id_payroll` int NOT NULL,
  `id_anggota` int NOT NULL,
  `id_payhead` int NOT NULL,
  `jenis` enum('earnings','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','revisi','final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_detail`
--

INSERT INTO `payroll_detail` (`id`, `id_payroll`, `id_anggota`, `id_payhead`, `jenis`, `amount`, `status`) VALUES
(1, 4, 22, 3, 'earnings', 100000.00, 'draft'),
(2, 4, 22, 6, 'earnings', 150000.00, 'draft'),
(3, 5, 22, 3, 'earnings', 100000.00, 'final'),
(4, 5, 22, 6, 'earnings', 150000.00, 'final');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_detail_final`
--

CREATE TABLE `payroll_detail_final` (
  `id` int NOT NULL,
  `id_payroll_final` int NOT NULL,
  `id_payhead` int NOT NULL,
  `nama_payhead` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenis` enum('earnings','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_rapel` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_detail_final`
--

INSERT INTO `payroll_detail_final` (`id`, `id_payroll_final`, `id_payhead`, `nama_payhead`, `jenis`, `amount`, `is_rapel`) VALUES
(1, 1, 3, 'Bonus Kinerja', 'earnings', 100000.00, 0),
(2, 1, 6, 'Tunjangan Hari Raya', 'earnings', 150000.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_final`
--

CREATE TABLE `payroll_final` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `id_rekap_absensi` int DEFAULT NULL,
  `bulan` int NOT NULL,
  `tahun` int NOT NULL,
  `gaji_pokok` decimal(15,2) DEFAULT NULL,
  `total_pendapatan` decimal(15,2) DEFAULT NULL,
  `total_potongan` decimal(15,2) DEFAULT NULL,
  `potongan_koperasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `tgl_payroll` datetime NOT NULL,
  `no_rekening` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `finalized_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_payroll_asal` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_final`
--

INSERT INTO `payroll_final` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `potongan_koperasi`, `gaji_bersih`, `tgl_payroll`, `no_rekening`, `catatan`, `finalized_at`, `id_payroll_asal`) VALUES
(1, 22, 5, 3, 2025, 12000000.00, 250000.00, 0.00, 0.00, 12250000.00, '2025-04-06 00:56:00', '6677889900', '', '2025-04-05 17:56:29', 5);

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan_ijin`
--

CREATE TABLE `pengajuan_ijin` (
  `id` int NOT NULL,
  `nip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `judul_surat` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pesan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe_ijin` enum('Sakit','Cuti Biasa','Ijin Lainnya') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_kepalasekolah` enum('Diterima','Pending','Ditolak') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `status` enum('Diterima','Pending','Ditolak') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `lampiran` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengajuan_ijin`
--

INSERT INTO `pengajuan_ijin` (`id`, `nip`, `nama`, `judul_surat`, `tanggal`, `pesan`, `tipe_ijin`, `status_kepalasekolah`, `status`, `lampiran`) VALUES
(2, '200002', 'Slamet Wijaya', 'Cuti Tahunan', '2025-02-10', 'Mengajukan cuti selama 5 hari', 'Cuti Biasa', 'Diterima', 'Pending', NULL),
(3, '100004', 'Rina Sari', 'Surat Izin', '2025-03-13', 'Sakit', 'Sakit', 'Diterima', 'Diterima', NULL),
(10, '100001', 'Ahmad Fauzi', 'Izin Sakit', '2025-03-15', 'Saya sakit hari ini', 'Sakit', 'Diterima', 'Diterima', NULL),
(11, '100001', 'Ahmad Fauzi', 'Izin Sakit Lama', '2025-03-01', 'Saya sakit minggu lalu', 'Sakit', 'Diterima', 'Diterima', NULL),
(12, '100002', 'Siti Rahma', 'Izin Cuti', '2025-03-14', 'Mohon izin cuti', 'Cuti Biasa', 'Pending', 'Pending', NULL),
(13, '100001', 'Ahmad Fauzi', 'Surat Izin', '2025-03-20', 'Izin sakit', 'Sakit', 'Pending', 'Pending', NULL),
(14, '01011995', 'Roosalin Chintia Dewi', 'Surat Izin', '2025-03-20', 'Izin karena sakit', 'Sakit', 'Pending', 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permintaan_tukar_jadwal`
--

CREATE TABLE `permintaan_tukar_jadwal` (
  `id` int NOT NULL,
  `id_jadwal_pengaju` int NOT NULL,
  `id_jadwal_tujuan` int DEFAULT NULL,
  `status` enum('Pending','Diterima','Ditolak') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `tanggal_permintaan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nip_tujuan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nip_pengaju` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_pengaju` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_piket` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permintaan_tukar_jadwal`
--

INSERT INTO `permintaan_tukar_jadwal` (`id`, `id_jadwal_pengaju`, `id_jadwal_tujuan`, `status`, `tanggal_permintaan`, `nip_tujuan`, `nip_pengaju`, `nama_pengaju`, `tanggal_piket`) VALUES
(100, 100, NULL, 'Pending', '2025-03-14 01:00:00', '100002', '100001', 'Ahmad Fauzi', '2025-03-15');

-- --------------------------------------------------------

--
-- Table structure for table `rekap_absensi`
--

CREATE TABLE `rekap_absensi` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `bulan` int NOT NULL,
  `tahun` int NOT NULL,
  `total_hadir` int DEFAULT '0',
  `total_izin` int DEFAULT '0',
  `total_cuti` int DEFAULT '0',
  `total_tanpa_keterangan` int DEFAULT '0',
  `total_sakit` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rekap_absensi`
--

INSERT INTO `rekap_absensi` (`id`, `id_anggota`, `bulan`, `tahun`, `total_hadir`, `total_izin`, `total_cuti`, `total_tanpa_keterangan`, `total_sakit`) VALUES
(1, 5, 3, 2025, 5, 1, 0, 1, 0),
(5, 22, 3, 2025, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `rekap_mingguan`
--

CREATE TABLE `rekap_mingguan` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `minggu_ke` int NOT NULL,
  `tahun` int NOT NULL,
  `total_hadir` int DEFAULT '0',
  `total_terlambat` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rekap_mingguan`
--

INSERT INTO `rekap_mingguan` (`id`, `id_anggota`, `minggu_ke`, `tahun`, `total_hadir`, `total_terlambat`) VALUES
(3, 5, 10, 2025, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `salary_indices`
--

CREATE TABLE `salary_indices` (
  `id` int NOT NULL,
  `level` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `min_years` int NOT NULL,
  `max_years` int DEFAULT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_indices`
--

INSERT INTO `salary_indices` (`id`, `level`, `min_years`, `max_years`, `base_salary`, `description`) VALUES
(1, 'Level 0', 0, 2, 3000000.00, 'Gaji untuk 0-2 tahun masa kerja'),
(2, 'Level 1', 3, 5, 4000000.00, 'Gaji untuk 3-5 tahun masa kerja'),
(3, 'Level 2', 6, 10, 5000000.00, 'Gaji untuk 6-10 tahun masa kerja'),
(4, 'Level 3', 11, 14, 6000000.00, 'Gaji untuk di atas 10 tahun masa kerja'),
(5, 'Level 4', 15, NULL, 7000000.00, 'Gaji untuk di atas 15 tahun masa kerja');

-- --------------------------------------------------------

--
-- Table structure for table `template_surat`
--

CREATE TABLE `template_surat` (
  `id` int NOT NULL,
  `jenis_surat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `judul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `isi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `default_penerima` enum('semua','perorangan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'perorangan',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `default_penerima_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `template_surat`
--

INSERT INTO `template_surat` (`id`, `jenis_surat`, `judul`, `isi`, `default_penerima`, `created_by`, `created_at`, `updated_at`, `default_penerima_id`) VALUES
(1, 'Ulang Tahun', 'Ulang Tahun', 'Selamat Ulang Tahun kepada Evan, semoga mimpi-mimpi di tahun ini tercapai dan terealisasikan semua.', 'perorangan', 14, '2025-03-11 10:05:11', '2025-03-20 11:26:30', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_absensi_anggota` (`id_anggota`);

--
-- Indexes for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`),
  ADD UNIQUE KEY `uk_nip` (`nip`),
  ADD KEY `salary_index_id` (`salary_index_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`nip`);

--
-- Indexes for table `employee_payheads`
--
ALTER TABLE `employee_payheads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_payheads_ibfk_1` (`id_anggota`),
  ADD KEY `employee_payheads_ibfk_2` (`id_payhead`);

--
-- Indexes for table `gaji_pokok_roles`
--
ALTER TABLE `gaji_pokok_roles`
  ADD PRIMARY KEY (`role`);

--
-- Indexes for table `gaji_pokok_strata_guru`
--
ALTER TABLE `gaji_pokok_strata_guru`
  ADD PRIMARY KEY (`jenjang`,`strata`);

--
-- Indexes for table `gaji_pokok_strata_karyawan`
--
ALTER TABLE `gaji_pokok_strata_karyawan`
  ADD PRIMARY KEY (`jenjang`,`strata`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`);

--
-- Indexes for table `jadwal_piket`
--
ALTER TABLE `jadwal_piket`
  ADD PRIMARY KEY (`id_jadwal`),
  ADD KEY `fk_jadwal_piket_anggota` (`nip`);

--
-- Indexes for table `laporan_surat`
--
ALTER TABLE `laporan_surat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `payheads`
--
ALTER TABLE `payheads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `id_rekap_absensi` (`id_rekap_absensi`);

--
-- Indexes for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payroll` (`id_payroll`),
  ADD KEY `idx_payhead` (`id_payhead`);

--
-- Indexes for table `payroll_detail_final`
--
ALTER TABLE `payroll_detail_final`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payroll_final` (`id_payroll_final`),
  ADD KEY `idx_payhead` (`id_payhead`);

--
-- Indexes for table `payroll_final`
--
ALTER TABLE `payroll_final`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `bulan` (`bulan`,`tahun`);

--
-- Indexes for table `pengajuan_ijin`
--
ALTER TABLE `pengajuan_ijin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rekap` (`id_anggota`,`bulan`,`tahun`);

--
-- Indexes for table `rekap_mingguan`
--
ALTER TABLE `rekap_mingguan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mingguan` (`id_anggota`,`minggu_ke`,`tahun`);

--
-- Indexes for table `salary_indices`
--
ALTER TABLE `salary_indices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level` (`level`);

--
-- Indexes for table `template_surat`
--
ALTER TABLE `template_surat`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_payheads`
--
ALTER TABLE `employee_payheads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `laporan_surat`
--
ALTER TABLE `laporan_surat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payroll_detail_final`
--
ALTER TABLE `payroll_detail_final`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payroll_final`
--
ALTER TABLE `payroll_final`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengajuan_ijin`
--
ALTER TABLE `pengajuan_ijin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rekap_mingguan`
--
ALTER TABLE `rekap_mingguan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `template_surat`
--
ALTER TABLE `template_surat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_anggota` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD CONSTRAINT `fk_anggota_salary_indices` FOREIGN KEY (`salary_index_id`) REFERENCES `salary_indices` (`id`);

--
-- Constraints for table `employee_payheads`
--
ALTER TABLE `employee_payheads`
  ADD CONSTRAINT `fk_employee_payheads_anggota` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`),
  ADD CONSTRAINT `fk_employee_payheads_payheads` FOREIGN KEY (`id_payhead`) REFERENCES `payheads` (`id`);

--
-- Constraints for table `jadwal_piket`
--
ALTER TABLE `jadwal_piket`
  ADD CONSTRAINT `fk_jadwal_piket_anggota` FOREIGN KEY (`nip`) REFERENCES `anggota_sekolah` (`nip`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_payroll_anggota` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`);

--
-- Constraints for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  ADD CONSTRAINT `fk_payroll_detail_payheads` FOREIGN KEY (`id_payhead`) REFERENCES `payheads` (`id`),
  ADD CONSTRAINT `fk_payroll_detail_payroll` FOREIGN KEY (`id_payroll`) REFERENCES `payroll` (`id`);

--
-- Constraints for table `payroll_final`
--
ALTER TABLE `payroll_final`
  ADD CONSTRAINT `fk_payroll_final_anggota` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
