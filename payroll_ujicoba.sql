-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2025 at 07:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `payroll_ujicoba`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jadwal` varchar(50) DEFAULT NULL,
  `jam_kerja` varchar(50) DEFAULT NULL,
  `valid` tinyint(1) DEFAULT 0,
  `pin` varchar(50) DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `departemen` varchar(10) NOT NULL,
  `lembur` tinyint(1) DEFAULT 0,
  `jam_masuk` time DEFAULT NULL,
  `scan_masuk` datetime DEFAULT NULL,
  `terlambat` tinyint(1) DEFAULT 0,
  `scan_istirahat_1` datetime DEFAULT NULL,
  `scan_istirahat_2` datetime DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `scan_pulang` datetime DEFAULT NULL,
  `jenis_absensi` varchar(50) DEFAULT '-',
  `status_kehadiran` enum('hadir','sakit','izin','cuti','tanpa_keterangan','libur') DEFAULT 'hadir',
  `id_anggota` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `tanggal`, `jadwal`, `jam_kerja`, `valid`, `pin`, `nip`, `nama`, `departemen`, `lembur`, `jam_masuk`, `scan_masuk`, `terlambat`, `scan_istirahat_1`, `scan_istirahat_2`, `jam_pulang`, `scan_pulang`, `jenis_absensi`, `status_kehadiran`, `id_anggota`) VALUES
(1, '2025-01-10', 'Senin-Kamis Guru', '06:00-15:00', 1, '100001', '100001', 'Ahmad Fauzi', 'SD', 0, '06:05:00', '2025-01-10 06:07:00', 0, NULL, NULL, '15:00:00', '2025-01-10 15:05:00', 'Lembur', 'hadir', 1),
(2, '2025-01-10', 'Senin-Kamis Guru', '06:00-15:00', 1, '100002', '100002', 'Siti Rahma', 'SMP', 0, '06:00:00', '2025-01-10 06:02:00', 0, NULL, NULL, '15:00:00', '2025-01-10 15:02:00', '-', 'hadir', 2),
(3, '2025-01-10', 'Senin-Kamis Guru', '06:00-15:00', 1, '100003', '100003', 'Budi Santoso', 'SMA', 0, '06:10:00', '2025-01-10 06:12:00', 1, NULL, NULL, '15:00:00', '2025-01-10 15:15:00', '-', 'sakit', 3),
(4, '2025-01-10', 'Senin-Kamis Karyawan', '07:00-16:00', 1, '200001', '200001', 'Dewi Lestari', 'SMA', 0, '07:05:00', '2025-01-10 07:06:00', 0, NULL, NULL, '16:00:00', '2025-01-10 16:00:00', '-', 'hadir', 4),
(5, '2025-01-10', 'Senin-Kamis Karyawan', '07:00-16:00', 1, '200002', '200002', 'Slamet Wijaya', 'SMK', 0, '07:00:00', '2025-01-10 07:01:00', 0, NULL, NULL, '16:00:00', '2025-01-10 16:00:00', '-', 'hadir', 5),
(6, '2025-01-10', 'Senin-Kamis Karyawan', '07:00-16:00', 1, '200003', '200003', 'Rizki Pratama', 'SMP', 0, '07:10:00', '2025-01-10 07:12:00', 1, NULL, NULL, '16:00:00', '2025-01-10 16:05:00', '-', 'izin', 6),
(7, '2025-01-10', 'Senin-Kamis Guru', '06:00-15:00', 1, '300001', '300001', 'Andini Permata', 'SMA', 0, '06:00:00', '2025-01-10 06:03:00', 0, NULL, NULL, '15:00:00', '2025-01-10 15:00:00', '-', 'hadir', 7),
(8, '2025-01-10', 'Senin-Kamis Guru', '06:00-15:00', 1, '300002', '300002', 'Joko Widodo', 'SMA', 0, '06:05:00', '2025-01-10 06:06:00', 0, NULL, NULL, '15:00:00', '2025-01-10 15:02:00', '-', 'hadir', 8),
(103, '2025-03-03', 'Senin-Kamis Guru', '06:00-15:00', 1, '100002', '100002', 'Siti Rahma', 'SMP', 0, '06:00:00', '2025-03-03 06:00:00', 0, NULL, NULL, '15:00:00', '2025-03-03 15:00:00', '-', 'tanpa_keterangan', 2),
(104, '2025-03-07', 'Senin-Kamis Guru', '06:00-15:00', 1, '100002', '100002', 'Siti Rahma', 'SMP', 0, '06:00:00', '2025-03-07 06:00:00', 0, NULL, NULL, '15:00:00', '2025-03-07 15:00:00', '-', 'tanpa_keterangan', 2),
(105, '2025-03-11', 'Senin-Kamis Guru', '06:00-15:00', 1, '100002', '100002', 'Siti Rahma', 'SMP', 0, '06:00:00', '2025-03-11 06:00:00', 0, NULL, NULL, '15:00:00', '2025-03-11 15:00:00', '-', 'tanpa_keterangan', 2);

-- --------------------------------------------------------

--
-- Table structure for table `anggota_sekolah`
--

CREATE TABLE `anggota_sekolah` (
  `id` int(11) NOT NULL,
  `uid` varchar(10) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenjang` varchar(10) DEFAULT NULL,
  `job_title` varchar(50) DEFAULT NULL,
  `status_kerja` varchar(20) DEFAULT NULL,
  `join_start` date DEFAULT NULL,
  `masa_kerja_tahun` int(11) DEFAULT NULL,
  `masa_kerja_bulan` int(11) DEFAULT NULL,
  `masa_kerja_efektif` decimal(5,2) DEFAULT 0.00,
  `remark` text DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `usia` int(11) DEFAULT NULL,
  `agama` varchar(20) DEFAULT NULL,
  `alamat_domisili` text DEFAULT NULL,
  `alamat_ktp` text DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `pendidikan` varchar(50) DEFAULT NULL,
  `status_perkawinan` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nama_pasangan` varchar(100) DEFAULT NULL,
  `jumlah_anak` int(11) DEFAULT 0,
  `nama_anak_1` varchar(100) NOT NULL DEFAULT '',
  `nama_anak_2` varchar(100) NOT NULL DEFAULT '',
  `nama_anak_3` varchar(100) NOT NULL DEFAULT '',
  `salary_index_id` int(11) DEFAULT NULL,
  `salary_index_level` varchar(10) DEFAULT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `foto_profil` varchar(255) DEFAULT 'default.jpg',
  `role` enum('P','TK','M') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_sekolah`
--

INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `job_title`, `status_kerja`, `join_start`, `masa_kerja_tahun`, `masa_kerja_bulan`, `masa_kerja_efektif`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_pasangan`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `salary_index_level`, `gaji_pokok`, `foto_profil`, `role`) VALUES
(1, 'G-001', '100001', 'e10adc3949ba59abbe56e057f20f883e', 'Ahmad Fauzi', 'SD', 'Guru Matematika', 'Tetap', '2023-01-27', 2, 1, 1.08, 'Berpengalaman mengajar matematika', 'L', '1980-01-15', 45, 'Islam', 'Jl. Melati No. 1', 'Jl. Melati No. 1', '1234567890', '081234567890', 'S1 Ilmu Komputer', 'Belum Menikah', 'ahmad.fauzi@example.com', 'Santi', 2, 'Buday', 'Siti', '', 1, 'Level 0', 3000000.00, '/payroll_absensi_v2/uploads/profile_pics/ahmad_fauzi_sd_p_1.jpg', 'P'),
(2, 'G-002', '100002', 'e10adc3949ba59abbe56e057f20f883e', 'Siti Rahma', 'SMP', 'Guru Fisika', 'Tetap', '2015-07-01', 10, 0, 9.67, 'Menyukai eksperimen fisika', 'P', '1985-05-10', 40, 'Islam', 'Jl. Kenanga No. 2', 'Jl. Kenanga No. 2', '098765', '081298765432', 'S1 Pendidikan', 'Menikah', 'siti.rahma@example.com', 'Andi Rahma', 1, 'Ayu', '', '', 3, 'Level 2', 5000000.00, '/payroll_absensi_v2/uploads/profile_pics/siti_rahma_smp_p_2.png', 'P'),
(3, 'G-003', '100003', 'e10adc3949ba59abbe56e057f20f883e', 'Budi Santoso', 'SMA', 'Guru Sejarah', 'Tetap', '2010-01-10', 15, 3, 15.17, 'Ahli sejarah Indonesia', 'L', '1975-12-25', 50, 'Kristen', 'Jl. Mawar No. 3', 'Jl. Mawar No. 3', '112233', '081345678901', 'S2 Pendidikan', 'Menikah', 'budi.santoso@example.com', '', 3, 'Tono', 'Rina', 'Dewi', 5, 'Level 4', 7000000.00, 'default.jpg', 'P'),
(4, 'G-004', '100004', 'e10adc3949ba59abbe56e057f20f883e', 'Rina Sari', 'SMK', 'Guru Bahasa', 'Tetap', '2012-03-15', 13, 0, 13.00, 'Mengajar dengan metode kreatif', 'P', '1982-07-20', 43, 'Islam', 'Jl. Melati No. 5', 'Jl. Melati No. 5', '445566', '081234000111', 'S1 Sastra', 'Menikah', 'rina.sari@example.com', 'Agus Sari', 1, 'Dewi', '', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'P'),
(5, 'G-005', '100005', 'e10adc3949ba59abbe56e057f20f883e', 'Dedi Prasetyo', 'SD', 'Wali Kelas 6B', 'Tetap', '2016-08-01', 9, 1, 8.58, 'Wali kelas yang disiplin', 'L', '1983-11-30', 41, 'Islam', 'Jl. Pelita No. 3', 'Jl. Pelita No. 3', '667788', '081234112233', 'S1 Pendidikan', 'Menikah', 'dedi.prasetyo@example.com', '', 3, 'Sari', 'Agus', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P'),
(6, 'G-006', '100006', 'e10adc3949ba59abbe56e057f20f883e', 'Maya Putri', 'SMP', 'Wali Kelas 2A', 'Tetap', '2018-01-15', 7, 0, 7.17, 'Wali kelas kreatif', 'P', '1990-04-10', 35, 'Islam', 'Jl. Merdeka No. 4', 'Jl. Merdeka No. 4', '223344', '081234223344', 'S1 Pendidikan', 'Menikah', 'maya.putri@example.com', 'Budi Putri', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P'),
(7, 'G-007', '100007', 'e10adc3949ba59abbe56e057f20f883e', 'Fitriani', 'SMA', 'Wali Kelas 4 SMP Kelas 1', 'Tetap', '2014-05-01', 11, 2, 10.83, 'Wali kelas yang teliti', 'P', '1987-09-15', 38, 'Islam', 'Jl. Sejahtera No. 7', 'Jl. Sejahtera No. 7', '334455', '081234334455', 'S1 Pendidikan', 'Menikah', 'fitriani@example.com', '', 2, 'Agus', 'Siti', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P'),
(8, 'K-001', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Lestari', 'SMA', 'Tenaga Kependidikan Administrasi', 'Kontrak', '2025-01-01', 0, 1, 0.17, 'Staff administrasi yang efisien', 'P', '1993-08-15', 32, 'Islam', 'Jl. Pertiwi No. 4', 'Jl. Pertiwi No. 4', '556677', '081234556677', 'S1 Administrasi', 'Belum Menikah', 'dewi.lestari@example.com', '', 0, '', '', '', 1, 'Level 0', 3000000.00, '/payroll_absensi_v2/uploads/profile_pics/dewi_lestari_sma_tk_8.jpg', 'TK'),
(9, 'K-002', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Slamet Wijaya', 'SMK', 'Tenaga Kependidikan Operasional', 'Tetap', '2018-06-15', 7, 0, 6.75, 'Bertugas di operasional', 'L', '1988-03-05', 37, 'Islam', 'Jl. Industri No. 7', 'Jl. Industri No. 7', '778899', '081298778899', 'S1 Manajemen', 'Menikah', 'slamet.wijaya@example.com', 'Siti Wijaya', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'TK'),
(10, 'K-003', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Rizki Pratama', 'SMP', 'Tenaga Kependidikan Umum', 'Kontrak', '2022-01-01', 3, 1, 3.17, 'Staff pendukung operasional', 'L', '1998-11-12', 27, 'Islam', 'Jl. Sudirman No. 8', 'Jl. Sudirman No. 8', '889900', '081237889900', '', 'Belum Menikah', 'rizki.pratama@example.com', '', 0, '', '', '', 2, 'Level 1', 4000000.00, 'default.jpg', 'TK'),
(11, 'M-001', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Andini Permata', 'SMA', 'Kepala Sekolah', 'Tetap', '2014-01-27', 11, 1, 11.08, 'Memimpin sekolah dengan visi', 'P', '1978-04-22', 47, 'Islam', 'Jl. Merdeka No. 10', 'Jl. Merdeka No. 10', '990011', '081290990011', 'S2 Kesenian', 'Menikah', 'andini.permata@example.com', 'Budi Permata', 2, 'Tina', 'Rina', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'M'),
(12, 'M-002', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Sie, Vincent Peter S.', 'SMA', 'Keuangan', 'Tetap', '2008-07-01', 16, 7, 16.67, 'Mengelola keuangan dengan transparansi', 'L', '1965-06-21', 60, 'Islam', 'Jl. Pendidikan No. 9', 'Jl. Pendidikan No. 9', '112233', '081298112233', 'S2 Teknologi Informasi', 'Menikah', 'joko.widodo@example.com', 'Iriana Widodo', 3, 'Gibran', 'Khalifah', 'Puan', 5, 'Level 4', 7000000.00, 'default.jpg', 'M'),
(13, 'M-003', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Sari Utami', 'SMK', 'SDM', 'Tetap', '2012-11-11', 12, 3, 12.33, 'Mengelola SDM dengan profesionalisme', 'P', '1982-02-28', 43, 'Kristen', 'Jl. Simpang Lima No. 5', 'Jl. Simpang Lima No. 5', '445577', '081298445577', 'S1 Akuntansi', 'Menikah', 'sari.utami@example.com', 'Agus Utomo', 2, 'Dina', 'Rini', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'M'),
(14, 'M-004', '300004', 'e10adc3949ba59abbe56e057f20f883e', 'Rudi Hartono', 'SMA', 'Superadmin', 'Tetap', '2010-01-01', 15, 0, 15.17, 'Administrator sistem IT sekolah', 'L', '1970-12-12', 54, 'Islam', 'Jl. Veteran No. 3', 'Jl. Veteran No. 3', '556644', '081298556644', '', 'Menikah', 'rudi.hartono@example.com', '', 0, '', '', '', 5, 'Level 3', 7000000.00, 'default.jpg', 'M');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nip` varchar(20) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_payheads`
--

CREATE TABLE `employee_payheads` (
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_payhead` int(11) NOT NULL,
  `jenis` enum('earnings','deductions') DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','revisi','final') NOT NULL DEFAULT 'draft',
  `remarks` text DEFAULT NULL,
  `support_doc_path` varchar(255) DEFAULT NULL,
  `upload_file_blob` mediumblob DEFAULT NULL,
  `is_rapel` tinyint(1) DEFAULT 0,
  `rapel_start_month` int(11) DEFAULT NULL,
  `rapel_start_year` int(11) DEFAULT NULL,
  `rapel_monthly_amount` decimal(15,2) DEFAULT 0.00,
  `rapel_accumulated` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_payheads`
--

INSERT INTO `employee_payheads` (`id`, `id_anggota`, `id_payhead`, `jenis`, `amount`, `status`, `remarks`, `support_doc_path`, `upload_file_blob`, `is_rapel`, `rapel_start_month`, `rapel_start_year`, `rapel_monthly_amount`, `rapel_accumulated`) VALUES
(0, 13, 3, 'earnings', 200000.00, 'final', '', '', NULL, 1, 3, 2025, 200000.00, 0.00),
(0, 13, 5, 'deductions', 150000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 12, 1, 'earnings', 150000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 12, 2, 'deductions', 125000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 12, 4, 'deductions', 250000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 12, 3, 'earnings', 100000.00, 'final', '', '', NULL, 1, 3, 2025, 100000.00, 0.00),
(0, 11, 3, 'earnings', 100000.00, 'final', '', '', NULL, 1, 3, 2025, 100000.00, 0.00),
(0, 14, 5, 'deductions', 300000.00, 'final', '', '', NULL, 1, 3, 2025, 300000.00, 0.00),
(0, 14, 1, 'earnings', 150000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 8, 3, 'earnings', 100000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(100, 1, 3, 'earnings', 200000.00, 'draft', 'Rapel pending', NULL, NULL, 1, 3, 2025, 200000.00, 0.00),
(0, 10, 3, 'earnings', 100000.00, 'final', '', '', NULL, 1, 3, 2025, 100000.00, 0.00),
(0, 10, 1, 'earnings', 150000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 10, 5, 'deductions', 150000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 7, 3, 'earnings', 100000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 7, 1, 'earnings', 150000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 11, 5, 'deductions', 150000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 11, 4, 'deductions', 250000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 11, 2, 'deductions', 125000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 9, 3, 'earnings', 100000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 9, 4, 'deductions', 250000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 9, 1, 'earnings', 150000.00, 'draft', '', '', NULL, 1, 3, 2025, 150000.00, 0.00),
(0, 5, 3, 'earnings', 100000.00, 'draft', '', '', NULL, 1, 3, 2025, 100000.00, 0.00),
(0, 5, 1, 'earnings', 150000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 5, 5, 'deductions', 150000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 5, 4, 'deductions', 250000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 5, 2, 'deductions', 125000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `gaji_pokok_roles`
--

CREATE TABLE `gaji_pokok_roles` (
  `role` varchar(20) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pendidikan` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gaji_pokok_roles`
--

INSERT INTO `gaji_pokok_roles` (`role`, `gaji_pokok`, `pendidikan`) VALUES
('guru', 5000000.00, ''),
('karyawan', 4000000.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_title` varchar(100) NOT NULL,
  `holiday_desc` text DEFAULT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('wajib','opsional') NOT NULL
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
  `id_jadwal` int(11) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `nama_guru` varchar(100) NOT NULL,
  `waktu_piket` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(20) NOT NULL,
  `tahun` int(11) NOT NULL,
  `status` enum('pending','hadir','tidak hadir') NOT NULL DEFAULT 'pending'
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
  `id` int(11) NOT NULL,
  `id_pengirim` int(11) NOT NULL,
  `id_penerima` int(11) NOT NULL,
  `jenis_surat` enum('peringatan') NOT NULL DEFAULT 'peringatan',
  `judul` varchar(255) NOT NULL,
  `isi` text NOT NULL,
  `tanggal_keluar` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('terkirim','dibaca') NOT NULL DEFAULT 'terkirim'
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
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_target` enum('keuangan','superadmin','sdm','all') NOT NULL DEFAULT 'all',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('info','warning','success','error') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `priority` int(11) DEFAULT 5 COMMENT 'Nilai prioritas; semakin kecil nilainya semakin tinggi prioritasnya',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT 'system' COMMENT 'Pengirim atau pembuat notifikasi, misalnya sistem atau admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payheads`
--

CREATE TABLE `payheads` (
  `id` int(11) NOT NULL,
  `nama_payhead` varchar(100) NOT NULL,
  `jenis` enum('earnings','deductions') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `nominal` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payheads`
--

INSERT INTO `payheads` (`id`, `nama_payhead`, `jenis`, `deskripsi`, `nominal`) VALUES
(1, 'Tunjangan Tetap', 'earnings', 'Tunjangan dasar untuk guru/karyawan', 150000.00),
(2, 'Potongan Pajak', 'deductions', 'Potongan pajak penghasilan', 125000.00),
(3, 'Bonus Kinerja', 'earnings', 'Bonus berdasarkan kinerja', 100000.00),
(4, 'Potongan BPJS', 'deductions', 'Potongan BPJS Kesehatan', 250000.00),
(5, 'Koperasi', 'deductions', 'Iuran koperasi', 150000.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_rekap_absensi` int(11) DEFAULT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `gaji_pokok` decimal(15,2) DEFAULT NULL,
  `total_pendapatan` decimal(15,2) DEFAULT NULL,
  `total_potongan` decimal(15,2) DEFAULT NULL,
  `potongan_koperasi` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tgl_payroll` datetime NOT NULL DEFAULT current_timestamp(),
  `no_rekening` varchar(50) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('draft','revisi','final') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `potongan_koperasi`, `gaji_bersih`, `created_at`, `tgl_payroll`, `no_rekening`, `catatan`, `status`) VALUES
(1, 1, NULL, 2, 2025, 4500000.00, 600000.00, 300000.00, 0.00, 4800000.00, '2025-02-28 03:00:00', '2025-02-28 09:50:00', '1234567890', 'Gaji Februari Final', 'final'),
(2, 2, NULL, 2, 2025, 4800000.00, 700000.00, 350000.00, 0.00, 5150000.00, '2025-02-28 03:05:00', '2025-02-28 10:00:00', '0987654321', 'Gaji Februari Draft', 'draft'),
(3, 3, NULL, 2, 2025, 5200000.00, 800000.00, 400000.00, 0.00, 5600000.00, '2025-02-28 03:10:00', '2025-02-28 10:05:00', '1122334455', 'Gaji Februari Revisi', 'revisi'),
(4, 4, NULL, 2, 2025, 4700000.00, 500000.00, 200000.00, 0.00, 5000000.00, '2025-02-28 03:15:00', '2025-02-28 10:10:00', '2233445566', 'Gaji Februari Final', 'final'),
(5, 5, NULL, 2, 2025, 4400000.00, 400000.00, 250000.00, 0.00, 4550000.00, '2025-02-28 03:20:00', '2025-02-28 10:15:00', '3344556677', 'Gaji Februari Draft', 'draft'),
(6, 6, NULL, 2, 2025, 4600000.00, 350000.00, 150000.00, 0.00, 4800000.00, '2025-02-28 03:25:00', '2025-02-28 10:20:00', '4455667788', 'Gaji Februari Final', 'final'),
(7, 7, NULL, 2, 2025, 4800000.00, 600000.00, 300000.00, 0.00, 5100000.00, '2025-02-28 03:30:00', '2025-02-28 10:25:00', '5566778899', 'Gaji Februari Revisi', 'revisi'),
(8, 8, NULL, 2, 2025, 4000000.00, 450000.00, 250000.00, 0.00, 4200000.00, '2025-02-28 03:35:00', '2025-02-28 10:30:00', '6677889900', 'Gaji Februari Draft', 'draft'),
(9, 9, NULL, 2, 2025, 4200000.00, 500000.00, 200000.00, 0.00, 4500000.00, '2025-02-28 03:40:00', '2025-02-28 10:35:00', '7788990011', 'Gaji Februari Final', 'final'),
(10, 10, NULL, 2, 2025, 3800000.00, 300000.00, 150000.00, 0.00, 3950000.00, '2025-02-28 03:45:00', '2025-02-28 10:40:00', '8899001122', 'Gaji Februari Draft', 'draft'),
(11, 11, NULL, 2, 2025, 7500000.00, 900000.00, 400000.00, 0.00, 8000000.00, '2025-02-28 03:50:00', '2025-02-28 10:45:00', '9900112233', 'Gaji Februari Revisi', 'revisi'),
(12, 12, NULL, 2, 2025, 14000000.00, 1200000.00, 600000.00, 0.00, 14600000.00, '2025-02-28 03:55:00', '2025-02-28 10:50:00', '1122334455', 'Gaji Februari Final', 'final'),
(13, 13, NULL, 2, 2025, 12300000.00, 1100000.00, 500000.00, 0.00, 12900000.00, '2025-02-28 04:00:00', '2025-02-28 10:55:00', '4455667788', 'Gaji Februari Draft', 'draft'),
(14, 14, NULL, 2, 2025, 14500000.00, 1500000.00, 700000.00, 0.00, 15300000.00, '2025-02-28 04:05:00', '2025-02-28 11:00:00', '5566778899', 'Gaji Februari Final', 'final'),
(18, 11, NULL, 2, 2025, 12500000.00, 900000.00, 400000.00, 0.00, 13000000.00, '2025-02-23 08:18:13', '2025-02-23 15:18:13', '990011', '', 'revisi'),
(19, 7, NULL, 2, 2025, 8800000.00, 600000.00, 300000.00, 0.00, 9100000.00, '2025-02-23 08:26:44', '2025-02-23 15:26:44', '334455', '', 'draft'),
(20, 11, NULL, 1, 2025, 12500000.00, 1000000.00, 400000.00, 0.00, 13100000.00, '2025-02-23 14:32:17', '2025-02-23 21:32:17', '990011', '', 'draft'),
(21, 12, NULL, 5, 2025, 14000000.00, 400000.00, 375000.00, 0.00, 14025000.00, '2025-03-08 05:41:13', '2025-03-08 12:41:13', '112233', '', 'draft'),
(23, 12, 12, 5, 2025, 14000000.00, 100000.00, 375000.00, 0.00, 13725000.00, '2025-03-08 05:45:45', '2025-03-08 12:41:00', '112233', '', 'final'),
(24, 12, NULL, 5, 2025, 14000000.00, 400000.00, 375000.00, 0.00, 14025000.00, '2025-03-08 06:36:03', '2025-03-08 13:36:03', '112233', '', 'draft'),
(25, 11, NULL, 5, 2025, 12000000.00, 200000.00, 0.00, 0.00, 12200000.00, '2025-03-08 06:36:32', '2025-03-08 13:36:32', '990011', '', 'draft'),
(27, 11, 13, 5, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-08 06:37:54', '2025-03-08 13:36:00', '990011', '', 'final'),
(28, 14, NULL, 5, 2025, 14000000.00, 150000.00, 300000.00, 0.00, 13850000.00, '2025-03-08 06:46:45', '2025-03-08 13:46:45', '556644', '', 'revisi'),
(29, 14, NULL, 5, 2025, 14000000.00, 150000.00, 300000.00, 0.00, 13850000.00, '2025-03-08 06:47:08', '2025-03-08 13:47:08', '556644', '', 'draft'),
(30, 14, 14, 5, 2025, 14000000.00, 150000.00, 0.00, 0.00, 14150000.00, '2025-03-08 06:47:19', '2025-03-08 13:47:00', '556644', '', 'final'),
(31, 13, NULL, 5, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-08 08:19:44', '2025-03-08 15:19:44', '445577', '', 'revisi'),
(32, 13, NULL, 5, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-08 08:20:01', '2025-03-08 15:20:01', '445577', '', 'draft'),
(33, 13, 15, 5, 2025, 12000000.00, 0.00, 150000.00, 0.00, 11850000.00, '2025-03-08 08:20:11', '2025-03-08 15:20:00', '445577', '', 'final'),
(34, 10, NULL, 5, 2025, 8000000.00, 0.00, 550000.00, 0.00, 7450000.00, '2025-03-08 08:24:00', '2025-03-08 15:24:00', '889900', '', 'final'),
(35, 9, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:27:34', '2025-03-08 15:27:34', '778899', '', 'draft'),
(36, 9, 16, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:27:46', '0000-00-00 00:00:00', '', '', 'final'),
(37, 8, NULL, 5, 2025, 6000000.00, 0.00, 0.00, 0.00, 6000000.00, '2025-03-08 08:43:22', '2025-03-08 15:43:22', '556677', '', 'draft'),
(38, 8, 17, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:43:38', '0000-00-00 00:00:00', '', '', 'final'),
(39, 7, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:44:42', '2025-03-08 15:44:42', '334455', '', 'draft'),
(40, 7, 18, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:44:51', '0000-00-00 00:00:00', '', '', 'final'),
(41, 6, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:47:44', '2025-03-08 15:47:44', '223344', '', 'draft'),
(42, 6, 19, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:47:56', '0000-00-00 00:00:00', '', '', 'final'),
(43, 5, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:57:39', '2025-03-08 15:57:39', '667788', '', 'draft'),
(44, 14, NULL, 3, 2025, 14000000.00, 0.00, 0.00, 0.00, 14000000.00, '2025-03-08 09:15:33', '2025-03-08 16:15:33', '556644', '', 'draft'),
(45, 14, 20, 3, 2025, 14000000.00, 150000.00, 0.00, 0.00, 14150000.00, '2025-03-08 09:19:08', '2025-03-08 16:19:00', '556644', '', 'final'),
(46, 13, NULL, 3, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-09 04:22:15', '2025-03-09 11:22:15', '445577', '', 'draft'),
(47, 13, 21, 3, 2025, 12000000.00, 0.00, 150000.00, 0.00, 11850000.00, '2025-03-09 04:32:39', '2025-03-09 11:32:00', '445577', '', 'final'),
(48, 13, NULL, 2, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-10 03:28:41', '2025-03-10 10:28:41', '445577', '', 'draft'),
(49, 13, NULL, 2, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-14 06:52:55', '2025-03-14 13:52:55', '445577', '', 'draft'),
(50, 13, 8, 2, 2025, 12000000.00, 0.00, 150000.00, 0.00, 11850000.00, '2025-03-14 06:53:13', '2025-03-14 13:53:00', '445577', '', 'final'),
(100, 2, NULL, 3, 2025, 4000000.00, 1000000.00, 200000.00, 0.00, 700000.00, '2025-03-14 00:00:00', '2025-03-14 08:00:00', '0987654321', 'Payroll error test', 'final'),
(101, 2, NULL, 3, 2025, 4000000.00, 800000.00, 300000.00, 0.00, 4500000.00, '2025-03-14 00:30:00', '2025-03-14 08:30:00', '0987654321', 'Payroll draft test', 'draft'),
(102, 10, 22, 2, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 15:10:43', '0000-00-00 00:00:00', '', '', 'final'),
(103, 8, 5, 2, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 15:13:40', '0000-00-00 00:00:00', '', '', 'final'),
(104, 5, 11, 2, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 15:23:42', '0000-00-00 00:00:00', '', '', 'final'),
(105, 12, NULL, 3, 2025, 14000000.00, 0.00, 0.00, 0.00, 14000000.00, '2025-03-18 15:41:51', '2025-03-18 22:41:51', '112233', '', 'draft'),
(106, 10, NULL, 3, 2025, 8000000.00, 150000.00, 150000.00, 0.00, 8000000.00, '2025-03-18 16:01:43', '2025-03-18 23:01:43', '889900', '', 'draft'),
(107, 10, 25, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 16:02:29', '0000-00-00 00:00:00', '', '', 'final'),
(108, 12, 24, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 17:06:31', '0000-00-00 00:00:00', '', '', 'final'),
(109, 11, NULL, 3, 2025, 12000000.00, 0.00, 525000.00, 0.00, 11475000.00, '2025-03-18 17:07:38', '2025-03-19 00:07:38', '990011', '', 'draft'),
(110, 11, 26, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 17:07:59', '0000-00-00 00:00:00', '', '', 'final'),
(111, 11, NULL, 3, 2025, 12000000.00, 0.00, 525000.00, 0.00, 11475000.00, '2025-03-18 17:10:35', '2025-03-19 00:10:35', '990011', '', 'draft'),
(112, 9, NULL, 3, 2025, 10000000.00, 100000.00, 250000.00, 0.00, 9850000.00, '2025-03-18 17:11:15', '2025-03-19 00:11:15', '778899', '', 'draft'),
(113, 9, 27, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 17:11:35', '0000-00-00 00:00:00', '', '', 'final'),
(114, 5, NULL, 3, 2025, 10000000.00, 150000.00, 525000.00, 0.00, 9625000.00, '2025-03-18 17:25:50', '2025-03-19 00:25:50', '667788', '', 'draft'),
(117, 5, 28, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-18 17:33:14', '0000-00-00 00:00:00', '', '', 'final'),
(118, 11, NULL, 2, 2025, 12000000.00, 0.00, 525000.00, 0.00, 11475000.00, '2025-03-18 17:52:37', '2025-03-19 00:52:37', '990011', '', 'revisi'),
(119, 11, NULL, 2, 2025, 12000000.00, 0.00, 525000.00, 0.00, 11475000.00, '2025-03-18 18:17:11', '2025-03-19 01:17:11', '990011', '', 'draft'),
(120, 11, 23, 2, 2025, 12.00, 0.00, 0.00, 0.00, 12.00, '2025-03-18 18:20:09', '2025-03-19 01:20:00', '990011', '', 'final');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_detail`
--

CREATE TABLE `payroll_detail` (
  `id` int(11) NOT NULL,
  `id_payroll` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_payhead` int(11) NOT NULL,
  `jenis` enum('earnings','deductions') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','revisi','final') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_detail`
--

INSERT INTO `payroll_detail` (`id`, `id_payroll`, `id_anggota`, `id_payhead`, `jenis`, `amount`, `status`) VALUES
(1, 1, 0, 1, 'earnings', 500000.00, 'draft'),
(2, 1, 0, 3, 'earnings', 100000.00, 'draft'),
(3, 1, 0, 2, 'deductions', 300000.00, 'draft'),
(4, 2, 0, 1, 'earnings', 700000.00, 'draft'),
(5, 2, 0, 2, 'deductions', 350000.00, 'draft'),
(6, 3, 0, 1, 'earnings', 500000.00, 'draft'),
(7, 3, 0, 1, 'earnings', 300000.00, 'draft'),
(8, 3, 0, 2, 'deductions', 400000.00, 'draft'),
(9, 4, 0, 1, 'earnings', 500000.00, 'draft'),
(10, 4, 0, 2, 'deductions', 200000.00, 'draft'),
(11, 5, 0, 1, 'earnings', 400000.00, 'final'),
(12, 5, 0, 2, 'deductions', 250000.00, 'final'),
(13, 6, 0, 1, 'earnings', 350000.00, 'draft'),
(14, 6, 0, 2, 'deductions', 150000.00, 'draft'),
(15, 7, 0, 1, 'earnings', 600000.00, 'draft'),
(16, 7, 0, 2, 'deductions', 300000.00, 'draft'),
(17, 8, 0, 1, 'earnings', 450000.00, 'draft'),
(18, 8, 0, 2, 'deductions', 250000.00, 'draft'),
(19, 9, 0, 1, 'earnings', 500000.00, 'final'),
(20, 9, 0, 2, 'deductions', 200000.00, 'final'),
(21, 10, 0, 1, 'earnings', 300000.00, 'draft'),
(22, 10, 0, 2, 'deductions', 150000.00, 'draft'),
(23, 11, 0, 1, 'earnings', 900000.00, 'final'),
(24, 11, 0, 2, 'deductions', 400000.00, 'final'),
(25, 12, 0, 1, 'earnings', 1200000.00, 'final'),
(26, 12, 0, 2, 'deductions', 600000.00, 'final'),
(27, 13, 0, 1, 'earnings', 1100000.00, 'draft'),
(28, 13, 0, 2, 'deductions', 500000.00, 'draft'),
(29, 14, 0, 1, 'earnings', 1500000.00, 'draft'),
(30, 14, 0, 2, 'deductions', 700000.00, 'draft'),
(35, 23, 0, 1, 'earnings', 300000.00, 'final'),
(36, 23, 0, 2, 'deductions', 125000.00, 'final'),
(37, 23, 0, 4, 'deductions', 250000.00, 'final'),
(38, 23, 0, 3, 'earnings', 100000.00, 'final'),
(40, 27, 0, 3, 'earnings', 200000.00, 'final'),
(41, 30, 0, 5, 'deductions', 300000.00, 'draft'),
(42, 30, 0, 1, 'earnings', 150000.00, 'draft'),
(43, 33, 0, 3, 'earnings', 200000.00, 'draft'),
(44, 33, 0, 5, 'deductions', 150000.00, 'draft'),
(45, 45, 0, 5, 'deductions', 300000.00, 'draft'),
(46, 45, 0, 1, 'earnings', 150000.00, 'draft'),
(47, 47, 0, 3, 'earnings', 200000.00, 'draft'),
(48, 47, 0, 5, 'deductions', 150000.00, 'draft'),
(49, 50, 0, 3, 'earnings', 200000.00, 'draft'),
(50, 50, 0, 5, 'deductions', 150000.00, 'draft'),
(51, 102, 0, 4, 'deductions', 250000.00, 'draft'),
(52, 102, 0, 5, 'deductions', 300000.00, 'draft'),
(53, 103, 0, 3, 'earnings', 100000.00, 'draft'),
(54, 107, 0, 3, 'earnings', 100000.00, 'draft'),
(55, 107, 0, 1, 'earnings', 150000.00, 'draft'),
(56, 107, 0, 5, 'deductions', 150000.00, 'draft'),
(57, 109, 0, 5, 'deductions', 150000.00, 'final'),
(58, 109, 0, 4, 'deductions', 250000.00, 'final'),
(59, 109, 0, 2, 'deductions', 125000.00, 'final'),
(60, 110, 0, 2, 'deductions', 125000.00, 'final'),
(61, 110, 0, 4, 'deductions', 250000.00, 'final'),
(62, 110, 0, 5, 'deductions', 150000.00, 'final'),
(63, 111, 0, 5, 'deductions', 150000.00, 'final'),
(64, 111, 0, 4, 'deductions', 250000.00, 'final'),
(65, 111, 0, 2, 'deductions', 125000.00, 'final'),
(66, 112, 0, 3, 'earnings', 100000.00, 'final'),
(67, 112, 0, 4, 'deductions', 250000.00, 'final'),
(68, 113, 0, 3, 'earnings', 100000.00, 'final'),
(69, 113, 0, 4, 'deductions', 250000.00, 'final'),
(70, 114, 0, 1, 'earnings', 150000.00, 'final'),
(71, 114, 0, 5, 'deductions', 150000.00, 'final'),
(72, 114, 0, 4, 'deductions', 250000.00, 'final'),
(73, 114, 0, 2, 'deductions', 125000.00, 'final'),
(76, 117, 0, 1, 'earnings', 150000.00, 'final'),
(77, 117, 0, 2, 'deductions', 125000.00, 'final'),
(78, 117, 0, 3, 'earnings', 0.00, 'final'),
(79, 117, 0, 4, 'deductions', 250000.00, 'final'),
(80, 117, 0, 5, 'deductions', 150000.00, 'final'),
(81, 118, 0, 5, 'deductions', 150000.00, 'final'),
(82, 118, 0, 4, 'deductions', 250000.00, 'final'),
(83, 118, 0, 2, 'deductions', 125000.00, 'final'),
(84, 119, 0, 5, 'deductions', 150000.00, 'final'),
(85, 119, 0, 4, 'deductions', 250000.00, 'final'),
(86, 119, 0, 2, 'deductions', 125000.00, 'final'),
(87, 120, 0, 2, 'deductions', 400000.00, 'final'),
(88, 120, 0, 3, 'earnings', 0.00, 'final'),
(89, 120, 0, 4, 'deductions', 0.00, 'final'),
(90, 120, 0, 5, 'deductions', 0.00, 'final');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_detail_final`
--

CREATE TABLE `payroll_detail_final` (
  `id` int(11) NOT NULL,
  `id_payroll_final` int(11) NOT NULL,
  `id_payhead` int(11) NOT NULL,
  `nama_payhead` varchar(200) NOT NULL,
  `jenis` enum('earnings','deductions') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_rapel` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_detail_final`
--

INSERT INTO `payroll_detail_final` (`id`, `id_payroll_final`, `id_payhead`, `nama_payhead`, `jenis`, `amount`, `is_rapel`) VALUES
(1, 7, 1, 'Tunjangan Tetap', 'earnings', 300000.00, 0),
(2, 7, 2, 'Potongan Pajak', 'deductions', 125000.00, 0),
(3, 7, 4, 'Potongan BPJS', 'deductions', 250000.00, 0),
(4, 7, 3, 'Bonus Kinerja', 'earnings', 100000.00, 0),
(8, 10, 1, 'Tunjangan Tetap', 'earnings', 150000.00, 0),
(9, 11, 5, 'Koperasi', 'deductions', 150000.00, 0),
(10, 16, 1, 'Tunjangan Tetap', 'earnings', 150000.00, 0),
(11, 17, 5, 'Koperasi', 'deductions', 150000.00, 0),
(12, 18, 5, 'Koperasi', 'deductions', 150000.00, 0),
(13, 101, 4, 'Potongan BPJS', 'deductions', 250000.00, 0),
(14, 102, 3, 'Bonus Kinerja', 'earnings', 100000.00, 0),
(15, 104, 1, 'Tunjangan Tetap', 'earnings', 150000.00, 0),
(16, 104, 5, 'Koperasi', 'deductions', 150000.00, 0),
(18, 106, 2, 'Potongan Pajak', 'deductions', 125000.00, 0),
(19, 106, 4, 'Potongan BPJS', 'deductions', 250000.00, 0),
(20, 106, 5, 'Koperasi', 'deductions', 150000.00, 0),
(21, 107, 3, 'Bonus Kinerja', 'earnings', 100000.00, 0),
(22, 107, 4, 'Potongan BPJS', 'deductions', 250000.00, 0),
(24, 108, 1, 'Tunjangan Tetap', 'earnings', 150000.00, 0),
(25, 108, 2, 'Potongan Pajak', 'deductions', 125000.00, 0),
(26, 108, 4, 'Potongan BPJS', 'deductions', 250000.00, 0),
(27, 108, 5, 'Koperasi', 'deductions', 150000.00, 0),
(31, 109, 2, 'Potongan Pajak', 'deductions', 400000.00, 0),
(32, 109, 4, 'Potongan BPJS', 'deductions', 0.00, 0),
(33, 109, 5, 'Koperasi', 'deductions', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_final`
--

CREATE TABLE `payroll_final` (
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_rekap_absensi` int(11) DEFAULT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `gaji_pokok` decimal(15,2) DEFAULT NULL,
  `total_pendapatan` decimal(15,2) DEFAULT NULL,
  `total_potongan` decimal(15,2) DEFAULT NULL,
  `potongan_koperasi` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `tgl_payroll` datetime NOT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `finalized_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_payroll_asal` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_final`
--

INSERT INTO `payroll_final` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `potongan_koperasi`, `gaji_bersih`, `tgl_payroll`, `no_rekening`, `catatan`, `finalized_at`, `id_payroll_asal`) VALUES
(1, 1, NULL, 2, 2025, 4500000.00, 600000.00, 300000.00, 0.00, 4800000.00, '2025-02-28 09:50:00', '1234567890', 'Gaji Februari Final', '2025-02-28 05:00:00', NULL),
(2, 4, NULL, 2, 2025, 4700000.00, 500000.00, 200000.00, 0.00, 5000000.00, '2025-02-28 10:10:00', '2233445566', 'Gaji Februari Final', '2025-02-28 05:05:00', NULL),
(3, 6, NULL, 2, 2025, 4600000.00, 350000.00, 150000.00, 0.00, 4800000.00, '2025-02-28 10:20:00', '4455667788', 'Gaji Februari Final', '2025-02-28 05:10:00', NULL),
(4, 9, NULL, 2, 2025, 4200000.00, 500000.00, 200000.00, 0.00, 4500000.00, '2025-02-28 10:35:00', '7788990011', 'Gaji Februari Final', '2025-02-28 05:15:00', NULL),
(5, 12, NULL, 2, 2025, 14000000.00, 1200000.00, 600000.00, 0.00, 14600000.00, '2025-02-28 10:50:00', '1122334455', 'Gaji Februari Final', '2025-02-28 05:20:00', NULL),
(6, 14, NULL, 2, 2025, 14500000.00, 1500000.00, 700000.00, 0.00, 15300000.00, '2025-02-28 11:00:00', '5566778899', 'Gaji Februari Final', '2025-02-28 05:25:00', NULL),
(7, 12, 12, 5, 2025, 14000000.00, 100000.00, 375000.00, 0.00, 13725000.00, '2025-03-08 12:41:00', '112233', '', '2025-03-08 05:45:45', 23),
(9, 11, 13, 5, 2025, 12000000.00, 0.00, 0.00, 0.00, 12000000.00, '2025-03-08 13:36:00', '990011', '', '2025-03-08 06:37:54', 27),
(10, 14, 14, 5, 2025, 14000000.00, 150000.00, 0.00, 0.00, 14150000.00, '2025-03-08 13:47:00', '556644', '', '2025-03-08 06:47:19', 30),
(11, 13, 15, 5, 2025, 12000000.00, 0.00, 150000.00, 0.00, 11850000.00, '2025-03-08 15:20:00', '445577', '', '2025-03-08 08:20:11', 33),
(12, 9, 16, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:27:46', 36),
(13, 8, 17, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:43:38', 38),
(14, 7, 18, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:44:51', 40),
(15, 6, 19, 5, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:47:56', 42),
(16, 14, 20, 3, 2025, 14000000.00, 150000.00, 0.00, 0.00, 14150000.00, '2025-03-08 16:19:00', '556644', '', '2025-03-08 09:19:08', 45),
(17, 13, 21, 3, 2025, 12000000.00, 0.00, 150000.00, 0.00, 11850000.00, '2025-03-09 11:32:00', '445577', '', '2025-03-09 04:32:39', 47),
(18, 13, 8, 2, 2025, 12000000.00, 0.00, 150000.00, 0.00, 11850000.00, '2025-03-14 13:53:00', '445577', '', '2025-03-14 06:53:13', 50),
(100, 1, NULL, 2, 2025, 3000000.00, 500000.00, 200000.00, 0.00, 3300000.00, '2025-03-01 10:00:00', '1234567890', 'Final Slip', '2025-03-01 03:05:00', NULL),
(101, 10, 22, 2, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 15:10:43', 102),
(102, 8, 5, 2, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 15:13:40', 103),
(103, 5, 11, 2, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 15:23:42', 104),
(104, 10, 25, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 16:02:29', 107),
(105, 12, 24, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 17:06:31', 108),
(106, 11, 26, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 17:07:59', 110),
(107, 9, 27, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 17:11:35', 113),
(108, 5, 28, 3, 2025, 0.00, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-18 17:33:14', 117),
(109, 11, 23, 2, 2025, 12.00, 0.00, 0.00, 0.00, 12.00, '2025-03-19 01:20:00', '990011', '', '2025-03-18 18:20:09', 120);

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan_ijin`
--

CREATE TABLE `pengajuan_ijin` (
  `id` int(11) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `judul_surat` varchar(255) NOT NULL,
  `tanggal` text NOT NULL,
  `pesan` text NOT NULL,
  `tipe_ijin` enum('Sakit','Cuti Biasa','Ijin Lainnya') NOT NULL,
  `status_kepalasekolah` enum('Diterima','Pending','Ditolak') NOT NULL DEFAULT 'Pending',
  `status` enum('Diterima','Pending','Ditolak') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengajuan_ijin`
--

INSERT INTO `pengajuan_ijin` (`id`, `nip`, `nama`, `judul_surat`, `tanggal`, `pesan`, `tipe_ijin`, `status_kepalasekolah`, `status`) VALUES
(2, '200002', 'Slamet Wijaya', 'Cuti Tahunan', '2025-02-10', 'Mengajukan cuti selama 5 hari', 'Cuti Biasa', 'Diterima', 'Pending'),
(0, '100004', 'Rina Sari', 'Surat Izin', '2025-03-13', 'Sakit', 'Sakit', 'Pending', 'Pending'),
(10, '100001', 'Ahmad Fauzi', 'Izin Sakit', '2025-03-15', 'Saya sakit hari ini', 'Sakit', 'Diterima', 'Diterima'),
(11, '100001', 'Ahmad Fauzi', 'Izin Sakit Lama', '2025-03-01', 'Saya sakit minggu lalu', 'Sakit', 'Diterima', 'Diterima'),
(12, '100002', 'Siti Rahma', 'Izin Cuti', '2025-03-14', 'Mohon izin cuti', 'Cuti Biasa', 'Pending', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `permintaan_tukar_jadwal`
--

CREATE TABLE `permintaan_tukar_jadwal` (
  `id` int(11) NOT NULL,
  `id_jadwal_pengaju` int(11) NOT NULL,
  `id_jadwal_tujuan` int(11) DEFAULT NULL,
  `status` enum('Pending','Diterima','Ditolak') DEFAULT 'Pending',
  `tanggal_permintaan` timestamp NOT NULL DEFAULT current_timestamp(),
  `nip_tujuan` varchar(20) DEFAULT NULL,
  `nip_pengaju` varchar(20) NOT NULL,
  `nama_pengaju` varchar(100) NOT NULL,
  `tanggal_piket` varchar(20) DEFAULT NULL
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
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `total_hadir` int(11) DEFAULT 0,
  `total_izin` int(11) DEFAULT 0,
  `total_cuti` int(11) DEFAULT 0,
  `total_tanpa_keterangan` int(11) DEFAULT 0,
  `total_sakit` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rekap_absensi`
--

INSERT INTO `rekap_absensi` (`id`, `id_anggota`, `bulan`, `tahun`, `total_hadir`, `total_izin`, `total_cuti`, `total_tanpa_keterangan`, `total_sakit`) VALUES
(1, 1, 1, 2025, 20, 2, 1, 0, 1),
(2, 2, 1, 2025, 18, 1, 2, 0, 0),
(3, 5, 1, 2025, 19, 0, 1, 0, 0),
(4, 7, 2, 2025, 22, 1, 0, 0, 0),
(5, 8, 2, 2025, 21, 0, 1, 2, 0),
(6, 6, 2, 2025, 0, 0, 0, 0, 0),
(7, 14, 2, 2025, 15, 0, 0, 0, 0),
(8, 13, 2, 2025, 0, 1, 2, 0, 0),
(9, 12, 2, 2025, 0, 0, 0, 0, 0),
(10, 12, 1, 2025, 21, 5, 0, 0, 0),
(11, 5, 2, 2025, 20, 0, 0, 0, 0),
(12, 12, 5, 2025, 0, 0, 0, 0, 0),
(13, 11, 5, 2025, 0, 0, 0, 0, 0),
(14, 14, 5, 2025, 0, 0, 0, 0, 0),
(15, 13, 5, 2025, 0, 0, 0, 0, 0),
(16, 9, 5, 2025, 0, 0, 0, 0, 0),
(17, 8, 5, 2025, 0, 0, 0, 0, 0),
(18, 7, 5, 2025, 0, 0, 0, 0, 0),
(19, 6, 5, 2025, 0, 0, 0, 0, 0),
(20, 14, 3, 2025, 0, 0, 0, 0, 0),
(21, 13, 3, 2025, 0, 0, 0, 0, 0),
(22, 10, 2, 2025, 0, 0, 0, 0, 0),
(23, 11, 2, 2025, 0, 0, 0, 0, 0),
(24, 12, 3, 2025, 0, 0, 0, 0, 0),
(25, 10, 3, 2025, 0, 0, 0, 0, 0),
(26, 11, 3, 2025, 0, 0, 0, 0, 0),
(27, 9, 3, 2025, 0, 0, 0, 0, 0),
(28, 5, 3, 2025, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `salary_indices`
--

CREATE TABLE `salary_indices` (
  `id` int(11) NOT NULL,
  `level` varchar(10) NOT NULL,
  `min_years` int(11) NOT NULL,
  `max_years` int(11) DEFAULT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL
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
  `id` int(11) NOT NULL,
  `jenis_surat` varchar(100) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `isi` text NOT NULL,
  `default_penerima` enum('semua','perorangan') NOT NULL DEFAULT 'perorangan',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `default_penerima_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `template_surat`
--

INSERT INTO `template_surat` (`id`, `jenis_surat`, `judul`, `isi`, `default_penerima`, `created_by`, `created_at`, `updated_at`, `default_penerima_id`) VALUES
(1, 'Ulang Tahun', 'Ulang Tahun', 'Selamat Ulang Tahun, semoga mimpi-mimpi di tahun ini tercapai dan terealisasikan semua.', 'perorangan', 14, '2025-03-11 10:05:11', NULL, NULL);

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
  ADD KEY `employee_payheads_ibfk_1` (`id_anggota`),
  ADD KEY `employee_payheads_ibfk_2` (`id_payhead`);

--
-- Indexes for table `gaji_pokok_roles`
--
ALTER TABLE `gaji_pokok_roles`
  ADD PRIMARY KEY (`role`);

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
-- Indexes for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `laporan_surat`
--
ALTER TABLE `laporan_surat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `payroll_detail_final`
--
ALTER TABLE `payroll_detail_final`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `payroll_final`
--
ALTER TABLE `payroll_final`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `template_surat`
--
ALTER TABLE `template_surat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
