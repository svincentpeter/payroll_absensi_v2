-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 18, 2025 at 03:54 AM
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
(8, '2025-01-10', 'Senin-Kamis Guru', '06:00-15:00', 1, '300002', '300002', 'Joko Widodo', 'SMA', 0, '06:05:00', '2025-01-10 06:06:00', 0, NULL, NULL, '15:00:00', '2025-01-10 15:02:00', '-', 'hadir', 8);

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
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `foto_profil` varchar(255) DEFAULT 'default.jpg',
  `role` enum('P','TK','M') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_sekolah`
--

INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `job_title`, `status_kerja`, `join_start`, `masa_kerja_tahun`, `masa_kerja_bulan`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_pasangan`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `gaji_pokok`, `foto_profil`, `role`) VALUES
(1, 'G-001', '100001', 'e10adc3949ba59abbe56e057f20f883e', 'Ahmad Fauzi', 'SD', 'Guru Matematika', 'Tetap', '2017-09-01', 8, 2, 'Berpengalaman mengajar matematika', 'L', '1980-01-15', 45, 'Islam', 'Jl. Melati No. 1', 'Jl. Melati No. 1', '1234567890', '081234567890', '', 'Menikah', 'ahmad.fauzi@example.com', 'Santi', 2, 'Buday', 'Siti', '', 1, 4500000.00, 'default.jpg', 'P'),
(2, 'G-002', '100002', 'e10adc3949ba59abbe56e057f20f883e', 'Siti Rahma', 'SMP', 'Guru Fisika', 'Tetap', '2015-07-01', 10, 0, 'Menyukai eksperimen fisika', 'P', '1985-05-10', 40, 'Islam', 'Jl. Kenanga No. 2', 'Jl. Kenanga No. 2', '098765', '081298765432', 'S1 Pendidikan', 'Menikah', 'siti.rahma@example.com', 'Andi Rahma', 1, 'Ayu', '', '', 1, 4800000.00, 'default.jpg', 'P'),
(3, 'G-003', '100003', 'e10adc3949ba59abbe56e057f20f883e', 'Budi Santoso', 'SMA', 'Guru Sejarah', 'Tetap', '2010-01-10', 15, 3, 'Ahli sejarah Indonesia', 'L', '1975-12-25', 50, 'Kristen', 'Jl. Mawar No. 3', 'Jl. Mawar No. 3', '112233', '081345678901', 'S2 Pendidikan', 'Menikah', 'budi.santoso@example.com', '', 3, 'Tono', 'Rina', 'Dewi', 2, 5200000.00, 'default.jpg', 'P'),
(4, 'G-004', '100004', 'e10adc3949ba59abbe56e057f20f883e', 'Rina Sari', 'SMK', 'Guru Bahasa', 'Tetap', '2012-03-15', 13, 0, 'Mengajar dengan metode kreatif', 'P', '1982-07-20', 43, 'Islam', 'Jl. Melati No. 5', 'Jl. Melati No. 5', '445566', '081234000111', 'S1 Sastra', 'Menikah', 'rina.sari@example.com', 'Agus Sari', 1, 'Dewi', '', '', 2, 4700000.00, 'default.jpg', 'P'),
(5, 'G-005', '100005', 'e10adc3949ba59abbe56e057f20f883e', 'Dedi Prasetyo', 'SD', 'Wali Kelas 6B', 'Tetap', '2016-08-01', 9, 1, 'Wali kelas yang disiplin', 'L', '1983-11-30', 41, 'Islam', 'Jl. Pelita No. 3', 'Jl. Pelita No. 3', '667788', '081234112233', 'S1 Pendidikan', 'Menikah', 'dedi.prasetyo@example.com', '', 3, 'Sari', 'Agus', '', 1, 4400000.00, 'default.jpg', 'P'),
(6, 'G-006', '100006', 'e10adc3949ba59abbe56e057f20f883e', 'Maya Putri', 'SMP', 'Wali Kelas 2A', 'Tetap', '2018-01-15', 7, 0, 'Wali kelas kreatif', 'P', '1990-04-10', 35, 'Islam', 'Jl. Merdeka No. 4', 'Jl. Merdeka No. 4', '223344', '081234223344', 'S1 Pendidikan', 'Menikah', 'maya.putri@example.com', 'Budi Putri', 1, 'Dewi', '', '', 1, 4600000.00, 'default.jpg', 'P'),
(7, 'G-007', '100007', 'e10adc3949ba59abbe56e057f20f883e', 'Fitriani', 'SMA', 'Wali Kelas 4 SMP Kelas 1', 'Tetap', '2014-05-01', 11, 2, 'Wali kelas yang teliti', 'P', '1987-09-15', 38, 'Islam', 'Jl. Sejahtera No. 7', 'Jl. Sejahtera No. 7', '334455', '081234334455', 'S1 Pendidikan', 'Menikah', 'fitriani@example.com', '', 2, 'Agus', 'Siti', '', 2, 4800000.00, 'default.jpg', 'P'),
(8, 'K-001', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Lestari', 'SMA', 'Tenaga Kependidikan Administrasi', 'Kontrak', '2021-02-01', 4, 1, 'Staff administrasi yang efisien', 'P', '1993-08-15', 32, 'Islam', 'Jl. Pertiwi No. 4', 'Jl. Pertiwi No. 4', '556677', '081234556677', 'S1 Administrasi', 'Belum Menikah', 'dewi.lestari@example.com', '', 0, '', '', '', 2, 4000000.00, 'default.jpg', 'TK'),
(9, 'K-002', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Slamet Wijaya', 'SMK', 'Tenaga Kependidikan Operasional', 'Tetap', '2018-06-15', 7, 0, 'Bertugas di operasional', 'L', '1988-03-05', 37, 'Islam', 'Jl. Industri No. 7', 'Jl. Industri No. 7', '778899', '081298778899', 'S1 Manajemen', 'Menikah', 'slamet.wijaya@example.com', 'Siti Wijaya', 1, 'Dewi', '', '', 1, 4200000.00, 'default.jpg', 'TK'),
(10, 'K-003', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Rizki Pratama', 'SMP', 'Tenaga Kependidikan Umum', 'Kontrak', '2023-01-20', 2, 0, 'Staff pendukung operasional', 'L', '1998-11-12', 27, 'Islam', 'Jl. Sudirman No. 8', 'Jl. Sudirman No. 8', '889900', '081237889900', 'D3 Manajemen', 'Belum Menikah', 'rizki.pratama@example.com', '', 0, '', '', '', 1, 3800000.00, 'default.jpg', 'TK'),
(11, 'M-001', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Andini Permata', 'SMA', 'Kepala Sekolah', 'Tetap', '2010-05-10', 15, 6, 'Memimpin sekolah dengan visi', 'P', '1978-04-22', 47, 'Islam', 'Jl. Merdeka No. 10', 'Jl. Merdeka No. 10', '990011', '081290990011', 'S2 Manajemen', 'Menikah', 'andini.permata@example.com', 'Budi Permata', 2, 'Tina', 'Rina', '', 3, 7500000.00, 'default.jpg', 'M'),
(12, 'M-002', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Joko Widodo', 'SMA', 'Keuangan', 'Tetap', '2008-07-01', 17, 0, 'Mengelola keuangan dengan transparansi', 'L', '1965-06-21', 60, 'Islam', 'Jl. Pendidikan No. 9', 'Jl. Pendidikan No. 9', '112233', '081298112233', 'S2 Administrasi', 'Menikah', 'joko.widodo@example.com', 'Iriana Widodo', 3, 'Gibran', 'Khalifah', 'Puan', 4, 8000000.00, 'default.jpg', 'M'),
(13, 'M-003', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Sari Utami', 'SMK', 'SDM', 'Tetap', '2012-11-11', 12, 3, 'Mengelola SDM dengan profesionalisme', 'P', '1982-02-28', 43, 'Kristen', 'Jl. Simpang Lima No. 5', 'Jl. Simpang Lima No. 5', '445577', '081298445577', 'S1 Akuntansi', 'Menikah', 'sari.utami@example.com', 'Agus Utomo', 2, 'Dina', 'Rini', '', 3, 7300000.00, 'default.jpg', 'M'),
(14, 'M-004', '300004', 'e10adc3949ba59abbe56e057f20f883e', 'Rudi Hartono', 'SMA', 'Superadmin', 'Tetap', '2010-01-01', 15, 0, 'Administrator sistem IT sekolah', 'L', '1970-12-12', 54, 'Islam', 'Jl. Veteran No. 3', 'Jl. Veteran No. 3', '556644', '081298556644', '', 'Menikah', 'rudi.hartono@example.com', '', 0, '', '', '', 4, 8500000.00, 'default.jpg', 'M');

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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `nip`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(50, '1', 'ApplyFilter', 'Pengguna menerapkan filter pada Hari Libur.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:01:45'),
(51, '1', 'ApplyFilter', 'Pengguna menerapkan filter pada Hari Libur.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:01:47'),
(52, '1', 'ApplyFilter', 'Pengguna menerapkan filter pada Hari Libur.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:01:54'),
(53, '1', 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:03:01'),
(54, '1', 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:03:02'),
(55, '1', 'LoadingRekapPayroll', 'Pengguna dengan ID 1 dan peran \'superadmin\' memuat data rekap payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:03:02'),
(56, '1', 'Logout', 'Pengguna \'superadmin\' dengan role \'superadmin\' berhasil logout.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 06:16:27'),
(57, '12', 'Login', 'Pengguna dengan NIP \'300002\' berhasil login sebagai keuangan.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 07:06:11'),
(58, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 07:06:55'),
(59, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 07:55:55'),
(60, '13', 'Login', 'Pengguna dengan NIP \'300003\' berhasil login sebagai sdm.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 07:56:31'),
(61, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 08:00:20'),
(62, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 08:04:06'),
(63, '300004', 'ViewDashboardKeuangan', 'Dashboard Keuangan diakses oleh user dengan NIP 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 09:18:50'),
(64, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 6.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 09:44:34'),
(65, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota dengan NIP 300004, ID anggota: 6.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 09:44:59'),
(66, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:24'),
(67, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:28'),
(68, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:29'),
(69, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:30'),
(70, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:35'),
(71, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:35'),
(72, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:36'),
(73, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:36'),
(74, '300004', 'ViewDashboardKeuangan', 'Dashboard Keuangan diakses oleh user dengan NIP 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:37'),
(75, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:37'),
(76, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:21:46'),
(77, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:23:53'),
(78, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:23:55'),
(79, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:24:09'),
(80, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:27:00'),
(81, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:27:04'),
(82, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:27:05'),
(83, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:31:55'),
(84, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:33:09'),
(85, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 10:33:23'),
(86, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 13:40:48'),
(87, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-14 13:56:17'),
(88, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:26:37'),
(89, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:26:44'),
(90, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:26:57'),
(91, '300004', 'ViewDashboardKeuangan', 'Dashboard Keuangan diakses oleh user dengan NIP 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:47:31'),
(92, '300004', 'ViewDashboardKeuangan', 'Dashboard Keuangan diakses oleh user dengan NIP 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:47:33'),
(93, '300004', 'ViewDashboardKeuangan', 'Dashboard Keuangan diakses oleh user dengan NIP 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:47:38'),
(94, '300004', 'ViewDashboardKeuangan', 'Dashboard Keuangan diakses oleh user dengan NIP 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:47:40'),
(95, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:55:14'),
(96, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 1/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:55:18'),
(97, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 1/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:55:18'),
(98, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:55:21'),
(99, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 01:55:21'),
(100, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 02:01:42'),
(101, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 02:20:02'),
(102, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 02:26:47'),
(103, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 02:27:22'),
(104, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 13.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 02:39:12'),
(105, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 02:57:11'),
(106, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:07:49'),
(107, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:11:57'),
(108, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 13.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:15:04'),
(109, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:15:31'),
(110, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 13.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:16:34'),
(111, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 10.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:17:11'),
(112, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:17:49'),
(113, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:25:39'),
(114, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:25:44'),
(115, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:22'),
(116, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:24'),
(117, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 8 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:26'),
(118, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:38'),
(119, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 8 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:40'),
(120, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:49'),
(121, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:26:53'),
(122, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:38:44'),
(123, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:43:53'),
(124, '300004', 'UpdatePayhead', 'Memperbarui Payhead ID 3 untuk Karyawan ID 7 dengan jumlah Rp 700.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:44:26'),
(125, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:44:28'),
(126, '300004', 'DeletePayhead', 'Menghapus Payhead ID 3 untuk Karyawan ID 7.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:44:44'),
(127, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:44:45'),
(128, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:31'),
(129, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:34'),
(130, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:37'),
(131, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 1/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:41'),
(132, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 1/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:41'),
(133, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 12/2024', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:45'),
(134, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 12/2024', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:45'),
(135, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:47'),
(136, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:47'),
(137, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 8 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:49:48'),
(138, '300004', 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'Ahmad Fauzi\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:51:00'),
(139, '300004', 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'Ahmad Fauzi\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 03:51:02'),
(140, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-17 07:49:21'),
(141, '14', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:42:27'),
(142, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:43:33'),
(143, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota dengan NIP 300004, ID anggota: 14.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:43:50'),
(144, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:43:54'),
(145, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 14 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:43:57'),
(146, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:44:09'),
(147, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:44:26'),
(148, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota dengan NIP 300004, ID: 7.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-18 02:46:00');

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
  `support_doc_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_payheads`
--

INSERT INTO `employee_payheads` (`id`, `id_anggota`, `id_payhead`, `jenis`, `amount`, `status`, `remarks`, `support_doc_path`) VALUES
(1, 1, 1, 'earnings', 500000.00, 'final', 'Tunjangan tetap', ''),
(2, 1, 2, 'deductions', 100000.00, 'final', 'Potongan pajak', ''),
(3, 2, 1, 'earnings', 600000.00, 'final', 'Tunjangan tetap', ''),
(5, 9, 4, 'deductions', 250000.00, 'final', 'Potongan BPJS', ''),
(0, 6, 3, 'earnings', 100000.00, 'final', '', ''),
(0, 6, 5, 'deductions', 150000.00, 'final', '', ''),
(0, 13, 3, 'earnings', 100000.00, 'draft', '', ''),
(0, 13, 5, 'deductions', 150000.00, 'draft', '', ''),
(0, 14, 3, 'earnings', 100000.00, 'final', '', ''),
(0, 14, 2, 'deductions', 125000.00, 'final', '', ''),
(0, 14, 5, 'deductions', 150000.00, 'final', '', '');

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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Gaji bulan Januari telah diproses.', 0, '2025-01-31 12:00:00'),
(2, 11, 'Data absensi telah diperbarui.', 1, '2025-01-20 09:00:00');

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

INSERT INTO `payroll` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `gaji_bersih`, `created_at`, `tgl_payroll`, `no_rekening`, `catatan`, `status`) VALUES
(1, 1, 1, 1, 2025, 4500000.00, 600000.00, 300000.00, 4800000.00, '2025-01-31 10:00:00', '2025-01-31 09:50:00', '1234567890', 'Gaji Januari', 'final'),
(2, 2, 2, 1, 2025, 4800000.00, 700000.00, 350000.00, 5150000.00, '2025-01-31 10:10:00', '2025-01-31 09:55:00', '098765', 'Gaji Januari', 'final'),
(3, 5, 3, 1, 2025, 4200000.00, 500000.00, 250000.00, 4450000.00, '2025-01-31 10:20:00', '2025-01-31 10:00:00', '334455', 'Gaji Januari', 'final'),
(4, 7, NULL, 2, 2025, 7500000.00, 1100000.00, 100000.00, 8500000.00, '2025-02-05 09:00:00', '2025-02-05 09:30:00', '556677', 'Gaji Februari', 'draft'),
(5, 8, NULL, 2, 2025, 8000000.00, 900000.00, 400000.00, 8400000.00, '2025-02-05 09:10:00', '2025-02-05 09:35:00', '667788', 'Gaji Februari', 'draft'),
(6, 9, NULL, 2, 2025, 7300000.00, 800000.00, 350000.00, 7450000.00, '2025-02-05 09:20:00', '2025-02-05 09:40:00', '778899', 'Gaji Februari', 'final'),
(7, 6, NULL, 2, 2025, 7600000.00, 100000.00, 150000.00, 7550000.00, '2025-02-14 09:44:59', '2025-02-14 16:44:59', '223344', '', 'draft'),
(8, 6, 6, 2, 2025, 7600000.00, 100000.00, 150000.00, 7550000.00, '2025-02-14 09:45:21', '2025-02-14 16:45:00', '223344', 'Tercatat', 'final'),
(9, 14, NULL, 2, 2025, 14500000.00, 100000.00, 275000.00, 14325000.00, '2025-02-18 02:43:50', '2025-02-18 09:43:50', '556644', '', 'revisi');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_detail`
--

CREATE TABLE `payroll_detail` (
  `id` int(11) NOT NULL,
  `id_payroll` int(11) NOT NULL,
  `id_payhead` int(11) NOT NULL,
  `jenis` enum('earnings','deductions') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_detail`
--

INSERT INTO `payroll_detail` (`id`, `id_payroll`, `id_payhead`, `jenis`, `amount`) VALUES
(1, 1, 1, 'earnings', 500000.00),
(2, 1, 2, 'deductions', 100000.00),
(3, 1, 3, 'earnings', 600000.00),
(4, 4, 1, 'earnings', 750000.00),
(5, 4, 2, 'deductions', 150000.00),
(6, 4, 3, 'earnings', 800000.00),
(7, 8, 3, 'earnings', 100000.00),
(8, 8, 5, 'deductions', 150000.00);

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
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `tgl_payroll` datetime NOT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `finalized_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_final`
--

INSERT INTO `payroll_final` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `gaji_bersih`, `tgl_payroll`, `no_rekening`, `catatan`, `finalized_at`) VALUES
(1, 1, 1, 1, 2025, 4500000.00, 600000.00, 300000.00, 4800000.00, '2025-01-31 09:50:00', '1234567890', 'Gaji Januari Final', '2025-02-11 14:12:22'),
(2, 2, 2, 1, 2025, 4800000.00, 700000.00, 350000.00, 5150000.00, '2025-01-31 09:55:00', '098765', 'Gaji Januari Final', '2025-02-11 14:12:22'),
(3, 6, 6, 2, 2025, 7600000.00, 100000.00, 150000.00, 7550000.00, '2025-02-14 16:45:00', '223344', '0', '2025-02-14 09:45:21');

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
(2, '200002', 'Slamet Wijaya', 'Cuti Tahunan', '2025-02-10', 'Mengajukan cuti selama 5 hari', 'Cuti Biasa', 'Pending', 'Pending');

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
(5, 8, 2, 2025, 21, 0, 1, 1, 0),
(6, 6, 2, 2025, 0, 0, 0, 0, 0),
(7, 14, 2, 2025, 0, 0, 0, 0, 0);

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
(4, 'Level 3', 11, NULL, 6000000.00, 'Gaji untuk di atas 10 tahun masa kerja'),
(5, 'Level 4', 15, NULL, 7000000.00, 'Gaji untuk di atas 15 tahun masa kerja');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`),
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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payroll_final`
--
ALTER TABLE `payroll_final`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
