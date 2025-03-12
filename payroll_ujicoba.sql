-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 10, 2025 at 05:55 AM
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
(4, 'G-004', '100004', 'e10adc3949ba59abbe56e057f20f883e', 'Rina Sari', 'SMK', 'Guru Bahasa', 'Tetap', '2012-03-15', 13, 0, 12.92, 'Mengajar dengan metode kreatif', 'P', '1982-07-20', 43, 'Islam', 'Jl. Melati No. 5', 'Jl. Melati No. 5', '445566', '081234000111', 'S1 Sastra', 'Menikah', 'rina.sari@example.com', 'Agus Sari', 1, 'Dewi', '', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'P'),
(5, 'G-005', '100005', 'e10adc3949ba59abbe56e057f20f883e', 'Dedi Prasetyo', 'SD', 'Wali Kelas 6B', 'Tetap', '2016-08-01', 9, 1, 8.58, 'Wali kelas yang disiplin', 'L', '1983-11-30', 41, 'Islam', 'Jl. Pelita No. 3', 'Jl. Pelita No. 3', '667788', '081234112233', 'S1 Pendidikan', 'Menikah', 'dedi.prasetyo@example.com', '', 3, 'Sari', 'Agus', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P'),
(6, 'G-006', '100006', 'e10adc3949ba59abbe56e057f20f883e', 'Maya Putri', 'SMP', 'Wali Kelas 2A', 'Tetap', '2018-01-15', 7, 0, 7.08, 'Wali kelas kreatif', 'P', '1990-04-10', 35, 'Islam', 'Jl. Merdeka No. 4', 'Jl. Merdeka No. 4', '223344', '081234223344', 'S1 Pendidikan', 'Menikah', 'maya.putri@example.com', 'Budi Putri', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P'),
(7, 'G-007', '100007', 'e10adc3949ba59abbe56e057f20f883e', 'Fitriani', 'SMA', 'Wali Kelas 4 SMP Kelas 1', 'Tetap', '2014-05-01', 11, 2, 10.83, 'Wali kelas yang teliti', 'P', '1987-09-15', 38, 'Islam', 'Jl. Sejahtera No. 7', 'Jl. Sejahtera No. 7', '334455', '081234334455', 'S1 Pendidikan', 'Menikah', 'fitriani@example.com', '', 2, 'Agus', 'Siti', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'P'),
(8, 'K-001', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Lestari', 'SMA', 'Tenaga Kependidikan Administrasi', 'Kontrak', '2025-01-01', 0, 1, 0.17, 'Staff administrasi yang efisien', 'P', '1993-08-15', 32, 'Islam', 'Jl. Pertiwi No. 4', 'Jl. Pertiwi No. 4', '556677', '081234556677', 'S1 Administrasi', 'Belum Menikah', 'dewi.lestari@example.com', '', 0, '', '', '', 1, 'Level 0', 3000000.00, '/payroll_absensi_v2/uploads/profile_pics/dewi_lestari_sma_tk_8.jpg', 'TK'),
(9, 'K-002', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Slamet Wijaya', 'SMK', 'Tenaga Kependidikan Operasional', 'Tetap', '2018-06-15', 7, 0, 6.67, 'Bertugas di operasional', 'L', '1988-03-05', 37, 'Islam', 'Jl. Industri No. 7', 'Jl. Industri No. 7', '778899', '081298778899', 'S1 Manajemen', 'Menikah', 'slamet.wijaya@example.com', 'Siti Wijaya', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'TK'),
(10, 'K-003', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Rizki Pratama', 'SMP', 'Tenaga Kependidikan Umum', 'Kontrak', '2022-01-01', 3, 1, 3.17, 'Staff pendukung operasional', 'L', '1998-11-12', 27, 'Islam', 'Jl. Sudirman No. 8', 'Jl. Sudirman No. 8', '889900', '081237889900', '', 'Belum Menikah', 'rizki.pratama@example.com', '', 0, '', '', '', 2, 'Level 1', 4000000.00, 'default.jpg', 'TK'),
(11, 'M-001', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Andini Permata', 'SMA', 'Kepala Sekolah', 'Tetap', '2014-01-27', 11, 1, 11.08, 'Memimpin sekolah dengan visi', 'P', '1978-04-22', 47, 'Islam', 'Jl. Merdeka No. 10', 'Jl. Merdeka No. 10', '990011', '081290990011', 'S2 Kesenian', 'Menikah', 'andini.permata@example.com', 'Budi Permata', 2, 'Tina', 'Rina', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'M'),
(12, 'M-002', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Sie, Vincent Peter S.', 'SMA', 'Keuangan', 'Tetap', '2008-07-01', 16, 7, 16.67, 'Mengelola keuangan dengan transparansi', 'L', '1965-06-21', 60, 'Islam', 'Jl. Pendidikan No. 9', 'Jl. Pendidikan No. 9', '112233', '081298112233', 'S2 Teknologi Informasi', 'Menikah', 'joko.widodo@example.com', 'Iriana Widodo', 3, 'Gibran', 'Khalifah', 'Puan', 5, 'Level 4', 7000000.00, 'default.jpg', 'M'),
(13, 'M-003', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Sari Utami', 'SMK', 'SDM', 'Tetap', '2012-11-11', 12, 3, 12.25, 'Mengelola SDM dengan profesionalisme', 'P', '1982-02-28', 43, 'Kristen', 'Jl. Simpang Lima No. 5', 'Jl. Simpang Lima No. 5', '445577', '081298445577', 'S1 Akuntansi', 'Menikah', 'sari.utami@example.com', 'Agus Utomo', 2, 'Dina', 'Rini', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'M'),
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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `nip`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(501, '300004', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:26:56'),
(502, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:27:06'),
(503, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:27:08'),
(504, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:27:13'),
(505, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 4/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:27:17'),
(506, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 4/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:27:17'),
(507, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 04:27:22'),
(508, '300004', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:03:15'),
(509, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:00'),
(510, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:05'),
(511, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:13'),
(512, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:16'),
(513, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:20'),
(514, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:20'),
(515, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 12 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:41:21'),
(516, '300004', 'InsertPayroll', 'Membuat Payroll ID 23 (final) untuk Karyawan ID 12 \r\n                     periode 5-2025. \r\n                     Pendapatan: Rp 100.000,00, \r\n                     Potongan: Rp 375.000,00, \r\n                     Gaji Bersih: Rp 13.725.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:45:45'),
(517, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 05:47:09'),
(518, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:35:56'),
(519, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:03'),
(520, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:07'),
(521, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:12'),
(522, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:12'),
(523, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 4/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:15'),
(524, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 4/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:15'),
(525, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 11 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:27'),
(526, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 11 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:32'),
(527, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:36'),
(528, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:39'),
(529, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:39'),
(530, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 11 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:36:40'),
(531, '300004', 'InsertPayroll', 'Membuat Payroll ID 27 (final) untuk Karyawan ID 11 periode 5-2025. Pendapatan: Rp 0,00, Potongan: Rp 0,00, Gaji Bersih: Rp 12.000.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:37:54'),
(532, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 11 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:31'),
(533, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 14 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:38'),
(534, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 14 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:45'),
(535, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:48'),
(536, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:51'),
(537, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:51'),
(538, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 14 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:53'),
(539, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:46:56'),
(540, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 14 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:05'),
(541, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 14 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:08'),
(542, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:14'),
(543, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:17'),
(544, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:17'),
(545, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 14 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:18'),
(546, '300004', 'InsertPayroll', 'Membuat Payroll ID 30 (final) untuk Karyawan ID 14 periode 5-2025. Pendapatan: Rp 150.000,00, Potongan: Rp 0,00, Gaji Bersih: Rp 14.150.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 06:47:19'),
(547, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:34'),
(548, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:40'),
(549, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:44'),
(550, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:47'),
(551, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:51'),
(552, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:51'),
(553, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 13 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:52'),
(554, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:55'),
(555, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:19:59'),
(556, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:20:01'),
(557, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:20:04'),
(558, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:20:08'),
(559, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:20:08'),
(560, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 13 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:20:09'),
(561, '300004', 'InsertPayroll', 'Membuat Payroll ID 33 (final) untuk Karyawan ID 13 periode 5-2025. Pendapatan: Rp 0,00, Potongan: Rp 150.000,00, Gaji Bersih: Rp 11.850.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:20:11'),
(562, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 10 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:23:53'),
(563, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 10 (oleh 300004). Status: final.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:24:00'),
(564, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:24:11'),
(565, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:24:14'),
(566, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:24:14'),
(567, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 9 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:31'),
(568, '300004', 'ProcessPayroll', 'Memproses payroll (status draft) untuk anggota ID = 9 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:34'),
(569, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:38'),
(570, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:42'),
(571, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:42'),
(572, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 9 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:43'),
(573, '300004', 'InsertPayroll', 'Membuat Payroll ID 36 (final) untuk Karyawan ID 9 periode 5-2025. Pendapatan: Rp 0,00, Potongan: Rp 0,00, Gaji Bersih: Rp 0,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:27:46'),
(574, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:19'),
(575, '300004', 'ProcessPayroll', 'SDM memproses payroll => draft, anggota ID = 8, oleh 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:22'),
(576, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:26'),
(577, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:35'),
(578, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:35'),
(579, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 8 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:36'),
(580, '300004', 'InsertPayroll', 'Membuat Payroll ID 38 (final) untuk Karyawan ID 8 periode 5-2025. Pendapatan: Rp 0,00, Potongan: Rp 0,00, Gaji Bersih: Rp 0,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:43:38'),
(581, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:35'),
(582, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:38'),
(583, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 7 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:40'),
(584, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 7 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:42'),
(585, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:45'),
(586, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:48'),
(587, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:48'),
(588, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:49'),
(589, '300004', 'InsertPayroll', 'Membuat Payroll ID 40 (final) untuk Karyawan ID 7 periode 5-2025. Pendapatan: Rp 0,00, Potongan: Rp 0,00, Gaji Bersih: Rp 0,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:44:51'),
(590, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 6 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:41'),
(591, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 6 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:44'),
(592, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:49'),
(593, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:52'),
(594, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 5/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:52'),
(595, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 6 pada bulan 5 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:53'),
(596, '300004', 'InsertPayroll', 'Membuat Payroll ID 42 (final) untuk Karyawan ID 6 periode 5-2025. Pendapatan: Rp 0,00, Potongan: Rp 0,00, Gaji Bersih: Rp 0,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:47:56'),
(597, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 5 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:57:30'),
(598, '300004', 'ProcessPayroll', 'Memproses payroll untuk anggota ID = 5 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 08:57:39'),
(599, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 14 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 09:15:30'),
(600, '300004', 'ProcessPayroll', 'SDM memproses payroll => draft, anggota ID = 14, oleh 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 09:15:33'),
(601, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 09:15:38'),
(602, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Anggota ID 14 pada bulan 3 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 09:15:40'),
(603, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 14 pada bulan 3 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 09:19:03'),
(604, '300004', 'InsertPayroll', 'Finalisasi Payroll untuk Anggota ID 14 periode 3-2025. Pendapatan: Rp 150.000,00, Potongan: Rp 0,00, Gaji Bersih: Rp 14.150.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-08 09:19:08'),
(605, '300004', 'Login', 'Pengguna dengan NIP \'300004\' berhasil login sebagai superadmin.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 03:25:18'),
(606, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 03:45:29'),
(607, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:06:08'),
(608, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:22:09'),
(609, '300004', 'ProcessPayroll', 'SDM memproses payroll => draft, anggota ID = 13, oleh 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:22:15'),
(610, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:22:23'),
(611, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Anggota ID 13 pada bulan 3 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:22:25'),
(612, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Anggota ID 13 pada bulan 3 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:26:19'),
(613, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Anggota ID 13 pada bulan 3 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:32:26'),
(614, '300004', 'InsertPayroll', 'Finalisasi Payroll untuk Anggota 13 periode 3-2025. Pendapatan: Rp 0,00, Potongan: Rp 150.000,00, Gaji Bersih: Rp 11.850.000,00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:32:39'),
(615, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:35:58'),
(616, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 04:44:53'),
(617, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 12 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 07:12:19'),
(618, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-03-09 07:12:29'),
(619, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:42:20'),
(620, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:42:36'),
(621, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:42:39'),
(622, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:42:52'),
(623, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:43:12'),
(624, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:50:02'),
(625, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:54:10'),
(626, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:54:16'),
(627, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:54:29'),
(628, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 8 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:54:35'),
(629, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:54:46'),
(630, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 01:56:02'),
(631, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:00:07'),
(632, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:00:14'),
(633, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:02:29'),
(634, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:06:28'),
(635, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:06:32'),
(636, '100004', 'Login', 'Pengguna dengan NIP \'100004\' berhasil login sebagai guru.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:15:40'),
(637, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:15:44'),
(638, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:16:18'),
(639, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:22:46'),
(640, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:29:26'),
(641, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:33:25'),
(642, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 02:38:31'),
(643, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:17:55'),
(644, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:28:21'),
(645, '300004', 'ViewEmployeeDetail', 'Melihat detail anggota ID 13 (oleh 300004).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:28:36'),
(646, '300004', 'ProcessPayroll', 'SDM memproses payroll => draft, anggota ID = 13, oleh 300004.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:28:41'),
(647, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:28:45'),
(648, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:28:49'),
(649, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:28:50'),
(650, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 3/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:29:02'),
(651, '300004', 'SelectPayrollMonth', 'User dengan NIP 300004 memilih bulan payroll: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:29:06'),
(652, '300004', 'ViewPayrollOverview', 'User dengan NIP 300004 melihat overview payroll untuk periode: 2/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:29:06'),
(653, '300004', 'ViewPayroll', 'Mengakses Review Payroll untuk Anggota ID 13 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:29:14'),
(654, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:37:19'),
(655, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:45:21'),
(656, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:45:52'),
(657, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:46:02'),
(658, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:46:07'),
(659, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:52:07'),
(660, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:55:20'),
(661, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:55:31'),
(662, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 03:55:33'),
(663, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 04:05:17'),
(664, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 04:05:28'),
(665, '100004', 'AccessDashboard', 'Mengakses dashboard.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', '2025-03-10 04:05:54');

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
(0, 12, 1, 'earnings', 300000.00, 'final', '', '', NULL, 1, 3, 2025, 300000.00, 0.00),
(0, 12, 2, 'deductions', 125000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 12, 4, 'deductions', 250000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 12, 3, 'earnings', 100000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 11, 3, 'earnings', 200000.00, 'final', '', '', NULL, 1, 3, 2025, 100000.00, 0.00),
(0, 14, 5, 'deductions', 300000.00, 'final', '', '', NULL, 1, 3, 2025, 300000.00, 0.00),
(0, 14, 1, 'earnings', 150000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 10, 4, 'deductions', 250000.00, 'final', '', '', NULL, 0, NULL, NULL, 0.00, 0.00),
(0, 10, 5, 'deductions', 300000.00, 'final', '', '', NULL, 1, 3, 2025, 150000.00, 0.00),
(0, 8, 3, 'earnings', 100000.00, 'draft', '', '', NULL, 0, NULL, NULL, 0.00, 0.00);

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
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `role_target`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'sdm', 'Gaji bulan Januari telah diproses.', 0, '2025-01-31 12:00:00'),
(2, 11, 'all', 'Data absensi telah diperbarui.', 1, '2025-01-20 09:00:00');

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
(1, 1, NULL, 2, 2025, 4500000.00, 600000.00, 300000.00, 4800000.00, '2025-02-28 03:00:00', '2025-02-28 09:50:00', '1234567890', 'Gaji Februari Final', 'final'),
(2, 2, NULL, 2, 2025, 4800000.00, 700000.00, 350000.00, 5150000.00, '2025-02-28 03:05:00', '2025-02-28 10:00:00', '0987654321', 'Gaji Februari Draft', 'draft'),
(3, 3, NULL, 2, 2025, 5200000.00, 800000.00, 400000.00, 5600000.00, '2025-02-28 03:10:00', '2025-02-28 10:05:00', '1122334455', 'Gaji Februari Revisi', 'revisi'),
(4, 4, NULL, 2, 2025, 4700000.00, 500000.00, 200000.00, 5000000.00, '2025-02-28 03:15:00', '2025-02-28 10:10:00', '2233445566', 'Gaji Februari Final', 'final'),
(5, 5, NULL, 2, 2025, 4400000.00, 400000.00, 250000.00, 4550000.00, '2025-02-28 03:20:00', '2025-02-28 10:15:00', '3344556677', 'Gaji Februari Draft', 'draft'),
(6, 6, NULL, 2, 2025, 4600000.00, 350000.00, 150000.00, 4800000.00, '2025-02-28 03:25:00', '2025-02-28 10:20:00', '4455667788', 'Gaji Februari Final', 'final'),
(7, 7, NULL, 2, 2025, 4800000.00, 600000.00, 300000.00, 5100000.00, '2025-02-28 03:30:00', '2025-02-28 10:25:00', '5566778899', 'Gaji Februari Revisi', 'revisi'),
(8, 8, NULL, 2, 2025, 4000000.00, 450000.00, 250000.00, 4200000.00, '2025-02-28 03:35:00', '2025-02-28 10:30:00', '6677889900', 'Gaji Februari Draft', 'draft'),
(9, 9, NULL, 2, 2025, 4200000.00, 500000.00, 200000.00, 4500000.00, '2025-02-28 03:40:00', '2025-02-28 10:35:00', '7788990011', 'Gaji Februari Final', 'final'),
(10, 10, NULL, 2, 2025, 3800000.00, 300000.00, 150000.00, 3950000.00, '2025-02-28 03:45:00', '2025-02-28 10:40:00', '8899001122', 'Gaji Februari Draft', 'draft'),
(11, 11, NULL, 2, 2025, 7500000.00, 900000.00, 400000.00, 8000000.00, '2025-02-28 03:50:00', '2025-02-28 10:45:00', '9900112233', 'Gaji Februari Revisi', 'revisi'),
(12, 12, NULL, 2, 2025, 14000000.00, 1200000.00, 600000.00, 14600000.00, '2025-02-28 03:55:00', '2025-02-28 10:50:00', '1122334455', 'Gaji Februari Final', 'final'),
(13, 13, NULL, 2, 2025, 12300000.00, 1100000.00, 500000.00, 12900000.00, '2025-02-28 04:00:00', '2025-02-28 10:55:00', '4455667788', 'Gaji Februari Draft', 'draft'),
(14, 14, NULL, 2, 2025, 14500000.00, 1500000.00, 700000.00, 15300000.00, '2025-02-28 04:05:00', '2025-02-28 11:00:00', '5566778899', 'Gaji Februari Final', 'final'),
(18, 11, NULL, 2, 2025, 12500000.00, 900000.00, 400000.00, 13000000.00, '2025-02-23 08:18:13', '2025-02-23 15:18:13', '990011', '', 'draft'),
(19, 7, NULL, 2, 2025, 8800000.00, 600000.00, 300000.00, 9100000.00, '2025-02-23 08:26:44', '2025-02-23 15:26:44', '334455', '', 'draft'),
(20, 11, NULL, 1, 2025, 12500000.00, 1000000.00, 400000.00, 13100000.00, '2025-02-23 14:32:17', '2025-02-23 21:32:17', '990011', '', 'draft'),
(21, 12, NULL, 5, 2025, 14000000.00, 400000.00, 375000.00, 14025000.00, '2025-03-08 05:41:13', '2025-03-08 12:41:13', '112233', '', 'draft'),
(23, 12, 12, 5, 2025, 14000000.00, 100000.00, 375000.00, 13725000.00, '2025-03-08 05:45:45', '2025-03-08 12:41:00', '112233', '', 'final'),
(24, 12, NULL, 5, 2025, 14000000.00, 400000.00, 375000.00, 14025000.00, '2025-03-08 06:36:03', '2025-03-08 13:36:03', '112233', '', 'draft'),
(25, 11, NULL, 5, 2025, 12000000.00, 200000.00, 0.00, 12200000.00, '2025-03-08 06:36:32', '2025-03-08 13:36:32', '990011', '', 'draft'),
(27, 11, 13, 5, 2025, 12000000.00, 0.00, 0.00, 12000000.00, '2025-03-08 06:37:54', '2025-03-08 13:36:00', '990011', '', 'final'),
(28, 14, NULL, 5, 2025, 14000000.00, 150000.00, 300000.00, 13850000.00, '2025-03-08 06:46:45', '2025-03-08 13:46:45', '556644', '', 'revisi'),
(29, 14, NULL, 5, 2025, 14000000.00, 150000.00, 300000.00, 13850000.00, '2025-03-08 06:47:08', '2025-03-08 13:47:08', '556644', '', 'draft'),
(30, 14, 14, 5, 2025, 14000000.00, 150000.00, 0.00, 14150000.00, '2025-03-08 06:47:19', '2025-03-08 13:47:00', '556644', '', 'final'),
(31, 13, NULL, 5, 2025, 12000000.00, 0.00, 0.00, 12000000.00, '2025-03-08 08:19:44', '2025-03-08 15:19:44', '445577', '', 'revisi'),
(32, 13, NULL, 5, 2025, 12000000.00, 0.00, 0.00, 12000000.00, '2025-03-08 08:20:01', '2025-03-08 15:20:01', '445577', '', 'draft'),
(33, 13, 15, 5, 2025, 12000000.00, 0.00, 150000.00, 11850000.00, '2025-03-08 08:20:11', '2025-03-08 15:20:00', '445577', '', 'final'),
(34, 10, NULL, 5, 2025, 8000000.00, 0.00, 550000.00, 7450000.00, '2025-03-08 08:24:00', '2025-03-08 15:24:00', '889900', '', 'final'),
(35, 9, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:27:34', '2025-03-08 15:27:34', '778899', '', 'draft'),
(36, 9, 16, 5, 2025, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:27:46', '0000-00-00 00:00:00', '', '', 'final'),
(37, 8, NULL, 5, 2025, 6000000.00, 0.00, 0.00, 6000000.00, '2025-03-08 08:43:22', '2025-03-08 15:43:22', '556677', '', 'draft'),
(38, 8, 17, 5, 2025, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:43:38', '0000-00-00 00:00:00', '', '', 'final'),
(39, 7, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:44:42', '2025-03-08 15:44:42', '334455', '', 'draft'),
(40, 7, 18, 5, 2025, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:44:51', '0000-00-00 00:00:00', '', '', 'final'),
(41, 6, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:47:44', '2025-03-08 15:47:44', '223344', '', 'draft'),
(42, 6, 19, 5, 2025, 0.00, 0.00, 0.00, 0.00, '2025-03-08 08:47:56', '0000-00-00 00:00:00', '', '', 'final'),
(43, 5, NULL, 5, 2025, 10000000.00, 0.00, 0.00, 10000000.00, '2025-03-08 08:57:39', '2025-03-08 15:57:39', '667788', '', 'draft'),
(44, 14, NULL, 3, 2025, 14000000.00, 0.00, 0.00, 14000000.00, '2025-03-08 09:15:33', '2025-03-08 16:15:33', '556644', '', 'draft'),
(45, 14, 20, 3, 2025, 14000000.00, 150000.00, 0.00, 14150000.00, '2025-03-08 09:19:08', '2025-03-08 16:19:00', '556644', '', 'final'),
(46, 13, NULL, 3, 2025, 12000000.00, 0.00, 0.00, 12000000.00, '2025-03-09 04:22:15', '2025-03-09 11:22:15', '445577', '', 'draft'),
(47, 13, 21, 3, 2025, 12000000.00, 0.00, 150000.00, 11850000.00, '2025-03-09 04:32:39', '2025-03-09 11:32:00', '445577', '', 'final'),
(48, 13, NULL, 2, 2025, 12000000.00, 0.00, 0.00, 12000000.00, '2025-03-10 03:28:41', '2025-03-10 10:28:41', '445577', '', 'draft');

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
(2, 1, 3, 'earnings', 100000.00),
(3, 1, 2, 'deductions', 300000.00),
(4, 2, 1, 'earnings', 700000.00),
(5, 2, 2, 'deductions', 350000.00),
(6, 3, 1, 'earnings', 500000.00),
(7, 3, 1, 'earnings', 300000.00),
(8, 3, 2, 'deductions', 400000.00),
(9, 4, 1, 'earnings', 500000.00),
(10, 4, 2, 'deductions', 200000.00),
(11, 5, 1, 'earnings', 400000.00),
(12, 5, 2, 'deductions', 250000.00),
(13, 6, 1, 'earnings', 350000.00),
(14, 6, 2, 'deductions', 150000.00),
(15, 7, 1, 'earnings', 600000.00),
(16, 7, 2, 'deductions', 300000.00),
(17, 8, 1, 'earnings', 450000.00),
(18, 8, 2, 'deductions', 250000.00),
(19, 9, 1, 'earnings', 500000.00),
(20, 9, 2, 'deductions', 200000.00),
(21, 10, 1, 'earnings', 300000.00),
(22, 10, 2, 'deductions', 150000.00),
(23, 11, 1, 'earnings', 900000.00),
(24, 11, 2, 'deductions', 400000.00),
(25, 12, 1, 'earnings', 1200000.00),
(26, 12, 2, 'deductions', 600000.00),
(27, 13, 1, 'earnings', 1100000.00),
(28, 13, 2, 'deductions', 500000.00),
(29, 14, 1, 'earnings', 1500000.00),
(30, 14, 2, 'deductions', 700000.00),
(35, 23, 1, 'earnings', 300000.00),
(36, 23, 2, 'deductions', 125000.00),
(37, 23, 4, 'deductions', 250000.00),
(38, 23, 3, 'earnings', 100000.00),
(40, 27, 3, 'earnings', 200000.00),
(41, 30, 5, 'deductions', 300000.00),
(42, 30, 1, 'earnings', 150000.00),
(43, 33, 3, 'earnings', 200000.00),
(44, 33, 5, 'deductions', 150000.00),
(45, 45, 5, 'deductions', 300000.00),
(46, 45, 1, 'earnings', 150000.00),
(47, 47, 3, 'earnings', 200000.00),
(48, 47, 5, 'deductions', 150000.00);

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
(11, 17, 5, 'Koperasi', 'deductions', 150000.00, 0);

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
  `finalized_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_payroll_asal` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_final`
--

INSERT INTO `payroll_final` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `gaji_bersih`, `tgl_payroll`, `no_rekening`, `catatan`, `finalized_at`, `id_payroll_asal`) VALUES
(1, 1, NULL, 2, 2025, 4500000.00, 600000.00, 300000.00, 4800000.00, '2025-02-28 09:50:00', '1234567890', 'Gaji Februari Final', '2025-02-28 05:00:00', NULL),
(2, 4, NULL, 2, 2025, 4700000.00, 500000.00, 200000.00, 5000000.00, '2025-02-28 10:10:00', '2233445566', 'Gaji Februari Final', '2025-02-28 05:05:00', NULL),
(3, 6, NULL, 2, 2025, 4600000.00, 350000.00, 150000.00, 4800000.00, '2025-02-28 10:20:00', '4455667788', 'Gaji Februari Final', '2025-02-28 05:10:00', NULL),
(4, 9, NULL, 2, 2025, 4200000.00, 500000.00, 200000.00, 4500000.00, '2025-02-28 10:35:00', '7788990011', 'Gaji Februari Final', '2025-02-28 05:15:00', NULL),
(5, 12, NULL, 2, 2025, 14000000.00, 1200000.00, 600000.00, 14600000.00, '2025-02-28 10:50:00', '1122334455', 'Gaji Februari Final', '2025-02-28 05:20:00', NULL),
(6, 14, NULL, 2, 2025, 14500000.00, 1500000.00, 700000.00, 15300000.00, '2025-02-28 11:00:00', '5566778899', 'Gaji Februari Final', '2025-02-28 05:25:00', NULL),
(7, 12, 12, 5, 2025, 14000000.00, 100000.00, 375000.00, 13725000.00, '2025-03-08 12:41:00', '112233', '', '2025-03-08 05:45:45', 23),
(9, 11, 13, 5, 2025, 12000000.00, 0.00, 0.00, 12000000.00, '2025-03-08 13:36:00', '990011', '', '2025-03-08 06:37:54', 27),
(10, 14, 14, 5, 2025, 14000000.00, 150000.00, 0.00, 14150000.00, '2025-03-08 13:47:00', '556644', '', '2025-03-08 06:47:19', 30),
(11, 13, 15, 5, 2025, 12000000.00, 0.00, 150000.00, 11850000.00, '2025-03-08 15:20:00', '445577', '', '2025-03-08 08:20:11', 33),
(12, 9, 16, 5, 2025, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:27:46', 36),
(13, 8, 17, 5, 2025, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:43:38', 38),
(14, 7, 18, 5, 2025, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:44:51', 40),
(15, 6, 19, 5, 2025, 0.00, 0.00, 0.00, 0.00, '0000-00-00 00:00:00', '', '', '2025-03-08 08:47:56', 42),
(16, 14, 20, 3, 2025, 14000000.00, 150000.00, 0.00, 14150000.00, '2025-03-08 16:19:00', '556644', '', '2025-03-08 09:19:08', 45),
(17, 13, 21, 3, 2025, 12000000.00, 0.00, 150000.00, 11850000.00, '2025-03-09 11:32:00', '445577', '', '2025-03-09 04:32:39', 47);

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
(2, '200002', 'Slamet Wijaya', 'Cuti Tahunan', '2025-02-10', 'Mengajukan cuti selama 5 hari', 'Cuti Biasa', 'Diterima', 'Pending');

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
(8, 13, 2, 2025, 0, 0, 0, 0, 0),
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
(21, 13, 3, 2025, 0, 0, 0, 0, 0);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=666;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `payroll_detail_final`
--
ALTER TABLE `payroll_detail_final`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payroll_final`
--
ALTER TABLE `payroll_final`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
