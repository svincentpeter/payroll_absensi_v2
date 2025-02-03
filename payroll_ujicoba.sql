-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 02, 2025 at 04:10 PM
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
  `jam_kerja` varchar(20) DEFAULT NULL,
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
(1, '2024-12-09', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2024-12-09 06:32:20', 0, '2024-12-09 00:00:00', '2024-12-09 00:00:00', '14:45:00', '2024-12-09 15:24:42', '-', 'hadir', 5),
(2, '2024-12-10', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2024-12-10 06:19:18', 0, '2024-12-10 00:00:00', '2024-12-10 00:00:00', '14:45:00', '2024-12-10 13:19:41', '-', 'hadir', 5),
(3, '2024-12-11', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2024-12-11 06:07:54', 0, '2024-12-11 00:00:00', '2024-12-11 00:00:00', '14:45:00', '2024-12-11 15:16:17', '-', 'hadir', 5),
(4, '2024-12-12', 'Guru', 'Senin - Kamis Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2024-12-12 06:26:41', 0, '2024-12-12 00:00:00', '2024-12-12 00:00:00', '14:45:00', '2024-12-12 15:41:13', '-', 'hadir', 5),
(5, '2024-12-13', 'Guru', 'Jum\'at - Guru', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '06:30:00', '2024-12-13 06:23:05', 0, '2024-12-13 00:00:00', '2024-12-13 00:00:00', '13:30:00', '2024-12-13 16:24:13', '-', 'hadir', 5),
(6, '2024-12-14', 'Guru', 'Libur Rutin', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '00:00:00', '2024-12-14 00:00:00', 0, '2024-12-14 00:00:00', '2024-12-14 00:00:00', '00:00:00', '2024-12-14 00:00:00', '-', 'hadir', 5),
(7, '2024-12-15', 'Guru', 'Libur Rutin', 0, '010195', '01011995', 'Roosalin Chintia Dewi,SE', 'TK', 0, '00:00:00', '2024-12-15 00:00:00', 0, '2024-12-15 00:00:00', '2024-12-15 00:00:00', '00:00:00', '2024-12-15 00:00:00', '-', 'hadir', 5),
(8, '2024-12-09', 'Karyawan', 'Senin - Jum\'at Karya', 0, '101078', '101078', 'tjendana', '', 0, '08:00:00', '2024-12-09 08:11:00', 1, '2024-12-09 00:00:00', '2024-12-09 00:00:00', '16:00:00', '2024-12-09 17:07:00', 'Normal', 'hadir', 4),
(9, '2024-12-10', 'Karyawan', 'Senin - Jum\'at Karya', 0, '101078', '101078', 'tjendana', 'TK', 0, '08:00:00', '2024-12-10 07:58:32', 0, '2024-12-10 00:00:00', '2024-12-10 00:00:00', '16:00:00', '2024-12-10 17:03:17', '-', 'hadir', 4),
(10, '2024-12-11', 'Karyawan', 'Senin - Jum\'at Karya', 0, '101078', '101078', 'tjendana', 'TK', 0, '08:00:00', '2024-12-11 07:40:53', 0, '2024-12-11 00:00:00', '2024-12-11 00:00:00', '16:00:00', '2024-12-11 17:11:05', '-', 'hadir', 4),
(11, '2024-12-12', 'Karyawan', 'Senin - Jum\'at Karya', 0, '101078', '101078', 'tjendana', 'TK', 0, '08:00:00', '2024-12-12 07:38:58', 0, '2024-12-12 00:00:00', '2024-12-12 00:00:00', '16:00:00', '2024-12-12 17:04:15', '-', 'hadir', 4),
(12, '2024-12-13', 'Karyawan', 'Senin - Jum\'at Karya', 0, '101078', '101078', 'tjendana', 'TK', 0, '08:00:00', '2024-12-13 07:53:11', 0, '2024-12-13 00:00:00', '2024-12-13 00:00:00', '16:00:00', '2024-12-13 17:13:27', '-', 'hadir', 4),
(13, '2024-12-14', 'Karyawan', 'Libur Rutin', 0, '101078', '101078', 'tjendana', 'TK', 0, '00:00:00', '2024-12-14 00:00:00', 0, '2024-12-14 00:00:00', '2024-12-14 00:00:00', '00:00:00', '2024-12-14 00:00:00', '-', 'hadir', 4),
(14, '2024-12-15', 'Karyawan', 'Libur Rutin', 0, '101078', '101078', 'tjendana', 'TK', 0, '00:00:00', '2024-12-15 00:00:00', 0, '2024-12-15 00:00:00', '2024-12-15 00:00:00', '00:00:00', '2024-12-15 00:00:00', '-', 'hadir', 4);

-- --------------------------------------------------------

--
-- Table structure for table `anggota_sekolah`
--

CREATE TABLE `anggota_sekolah` (
  `id` int(11) NOT NULL,
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
  `nama_suami` varchar(100) DEFAULT NULL,
  `jumlah_anak` int(11) DEFAULT 0,
  `nama_anak_1` varchar(100) DEFAULT NULL,
  `nama_anak_2` varchar(100) DEFAULT NULL,
  `nama_anak_3` varchar(100) DEFAULT NULL,
  `salary_index_id` int(11) DEFAULT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_sekolah`
--

INSERT INTO `anggota_sekolah` (`id`, `nip`, `password`, `nama`, `jenjang`, `job_title`, `status_kerja`, `join_start`, `masa_kerja_tahun`, `masa_kerja_bulan`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_suami`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `gaji_pokok`) VALUES
(1, '123456', '', 'John Doe', 'SD', 'Guru Matematika', 'Tetap', '2015-08-01', 8, 5, 'Pengajar berpengalaman', 'L', '1985-05-15', 39, 'Islam', 'Jl. Merdeka 1', 'Jl. Merdeka 1', '1234567890', '081234567890', 'S1 Pendidikan Matematika', 'Menikah', 'johndoe@example.com', 'Jane Doe', 2, 'Anna Doe', 'Bella Doe', NULL, 3, 0.00),
(2, '234567', '', 'Jane Smith', 'SMP', 'Staf Administrasi', 'Kontrak', '2020-01-15', 3, 4, 'Kontrak selama 1 tahun', 'P', '1990-07-20', 34, 'Kristen', 'Jl. Pahlawan 2', 'Jl. Pahlawan 2', '0987654321', '081298765432', 'D3 Administrasi', 'Belum Menikah', 'janesmith@example.com', NULL, 0, NULL, NULL, NULL, 2, 0.00),
(3, '345678', '', 'Mike Johnson', 'SMA', 'Guru Bahasa Inggris', 'Tetap', '2012-03-10', 11, 10, 'Guru senior', 'L', '1980-11-30', 44, 'Kristen', 'Jl. Kebangsaan 3', 'Jl. Kebangsaan 3', '1122334455', '081112223333', 'S2 Pendidikan Bahasa Inggris', 'Menikah', 'mikejohnson@example.com', 'Sarah Johnson', 3, 'Chris Johnson', 'Diana Johnson', 'Evan Johnson', 4, 0.00),
(4, '101078', '$2y$10$BT8aHSbpp59hmQimMQ4zu.1oMY0lWl7Hm15soYy.skkJBWamLEzgG', 'tjendana', 'TK', 'Guru Teknologi Informasi', 'Tetap', '0000-00-00', 0, 0, '0', 'L', '0000-00-00', 0, '0', '', '', '12345678', '', '', '', 'vincentpeter789@gmail.com', NULL, 0, NULL, NULL, NULL, NULL, 0.00),
(5, '01011995', '', 'Roosalin Chintia Dewi,SE', 'TK', 'Guru Kelas TK A', 'Tetap', '2022-07-01', 2, 6, 'Guru TK Berpengalaman', 'P', '1992-03-15', 32, 'Islam', 'Jl. Pendidikan 45', 'Jl. Pendidikan 45', '9876543210', '087765432100', 'S1 PAUD', 'Menikah', 'sarah.k@sekolah.edu', 'Budi Kurniawan', 1, 'Aisya Kurniawan', NULL, NULL, 1, 0.00),
(6, '567890', '', 'Ahmad Riyanto', 'TK', 'Guru Pendamping TK B', 'Kontrak', '2023-01-15', 1, 1, 'Guru Kontrak TK', 'L', '1995-11-20', 29, 'Islam', 'Jl. Merdeka 22', 'Jl. Merdeka 22', '1234567890', '081234567890', 'D3 Pendidikan', 'Belum Menikah', 'ahmad.r@sekolah.edu', NULL, 0, NULL, NULL, NULL, 1, 0.00),
(7, '678901', '', 'Rina Septiani', 'SD', 'Guru Kelas 3 SD', 'Tetap', '2015-08-15', 9, 5, 'Guru SD Senior', 'P', '1985-06-25', 39, 'Kristen', 'Jl. Pendidikan 78', 'Jl. Pendidikan 78', '5432167890', '087654321000', 'S1 Pendidikan Dasar', 'Menikah', 'rina.s@sekolah.edu', 'Dedi Supriyadi', 2, 'Annisa Supriyadi', 'Akbar Supriyadi', NULL, 2, 0.00),
(8, '789012', '', 'Hendra Gunawan', 'SD', 'Guru Olahraga SD', 'Tetap', '2018-03-01', 6, 10, '0', 'L', '1988-09-10', 36, '0', 'Jl. Olahraga 12', 'Jl. Olahraga 12', '6543210987', '085432109870', 'S1 Keolahragaan', 'Menikah', 'hendra.g@sekolah.edu', 'Maya Gunawan', 1, 'Radit Gunawan', NULL, NULL, 2, 0.00),
(9, '890123', '', 'Dewi Marlina', 'SMP', 'Guru Matematika SMP', 'Tetap', '2012-07-01', 12, 6, 'Guru Matematika Berpengalaman', 'P', '1980-12-05', 44, 'Kristen', 'Jl. Cendekia 33', 'Jl. Cendekia 33', '7654321098', '082345678900', 'S2 Pendidikan Matematika', 'Menikah', 'dewi.m@sekolah.edu', 'Firman Setiawan', 2, 'Ayu Setiawan', 'Reza Setiawan', NULL, 3, 0.00),
(10, '901234', '', 'Bambang Hermanto', 'SMP', 'Guru IPA SMP', 'Kontrak', '2021-01-15', 3, 1, 'Guru Kontrak IPA', 'L', '1990-08-18', 33, 'Islam', 'Jl. Ilmu 56', 'Jl. Ilmu 56', '8765432109', '087654321230', 'S1 Pendidikan IPA', 'Belum Menikah', 'bambang.h@sekolah.edu', NULL, 0, NULL, NULL, NULL, 1, 0.00),
(11, '012345', '', 'Siti Nurhaliza', 'SMA', 'Guru Bahasa Indonesia SMA', 'Tetap', '2008-08-01', 16, 4, 'Guru Bahasa Indonesia Senior', 'P', '1975-04-30', 49, 'Islam', 'Jl. Bahasa 89', 'Jl. Bahasa 89', '9876543210', '081234567890', 'S2 Sastra Indonesia', 'Menikah', 'siti.n@sekolah.edu', 'Rizal Mustopa', 3, 'Adi Mustopa', 'Sari Mustopa', 'Ririn Mustopa', 4, 0.00),
(12, '123456', '', 'Budi Setiawan', 'SMA', 'Guru Kimia SMA', 'Tetap', '2016-02-15', 8, 2, 'Guru Kimia Berprestasi', 'L', '1982-11-12', 42, 'Kristen', 'Jl. Sains 45', 'Jl. Sains 45', '2345678901', '085432109870', 'S1 Pendidikan Kimia', 'Menikah', 'budi.s@sekolah.edu', 'Ani Setiawan', 2, 'Dian Setiawan', 'Dana Setiawan', NULL, 2, 0.00),
(13, '234567', '', 'Angga Permana', 'SMK', 'Guru Teknik Komputer SMK', 'Tetap', '2014-07-01', 10, 6, 'Guru Teknik Informatika Senior', 'L', '1979-07-25', 45, 'Islam', 'Jl. Teknologi 67', 'Jl. Teknologi 67', '3456789012', '087654321000', 'S1 Teknik Informatika', 'Menikah', 'angga.p@sekolah.edu', 'Rina Permana', 2, 'Aldi Permana', 'Ayu Permana', NULL, 3, 0.00),
(14, '345678', '', 'Maya Indriati', 'SMK', 'Guru Akuntansi SMK', 'Kontrak', '2022-01-15', 2, 1, 'Guru Akuntansi Kontrak', 'P', '1993-09-08', 31, 'Kristen', 'Jl. Bisnis 23', 'Jl. Bisnis 23', '4567890123', '081234567890', 'S1 Akuntansi', 'Belum Menikah', 'maya.i@sekolah.edu', NULL, 0, NULL, NULL, NULL, 1, 0.00),
(15, '222222', 'e10adc3949ba59abbe56e057f20f883e', 'Vincent Peter', 'TK', 'Guru Sejarah', 'Tetap', '2025-01-18', 1, 1, 'W', 'L', '0000-00-00', 1, 'Katolik', 'edwd', 'wdwd', NULL, '082227124194', 'S1', 'Belum Menikah', 'propro@tokito.xyz', NULL, 0, NULL, NULL, NULL, NULL, 5000000.00),
(16, '808080', '0', 'Catherine Wong', 'TK', 'Guru Sejarah', 'Tetap', '2025-01-19', 1, 0, 'Gabut', 'P', '2025-01-19', 20, 'Katolik', '2A Jl. Empu Sendok Raya', '2A Jl. Empu Sendok Raya', NULL, '082227863969', 'S1', 'Belum Menikah', 'cathie@gmail.com', NULL, 0, NULL, NULL, NULL, NULL, 5000000.00),
(17, '777777', 'e10adc3949ba59abbe56e057f20f883e', 'Cathie W', 'TK', 'Karyawan Administrator', 'Kontrak', '2025-01-19', 1, 0, 'Hai', 'P', '2025-01-19', 21, 'Katolik', '2A Jl. Empu Sendok Raya', '2A Jl. Empu Sendok Raya', '6543210987', '082227863990', 'S1', 'Belum Menikah', 'cathie1@gmail.com', NULL, 0, NULL, NULL, NULL, NULL, 4000000.00);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'ViewUserDetail', 'Melihat detail User ID 1: Username=\'superadmin\', Role=\'superadmin\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:45:40'),
(2, 1, 'CreateUser', 'Menambahkan User: Username=\'vincent\', Role=\'keuangan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:45:52'),
(3, 1, 'CreateUser', 'Menambahkan User: Username=\'keuangan\', Role=\'keuangan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:46:07'),
(4, 1, 'CreateUser', 'Menambahkan User: Username=\'peter\', Role=\'superadmin\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:46:14'),
(5, 1, 'CreateUser', 'Menambahkan User: Username=\'sdm\', Role=\'sdm\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:46:24'),
(6, 1, 'ApplyFilter', 'Pengguna menerapkan filter Role: superadmin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:47:43'),
(7, 1, 'ApplyFilter', 'Pengguna menerapkan filter Role: superadmin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:47:47'),
(8, 1, 'ApplyFilter', 'Pengguna menerapkan filter Role: keuangan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:47:48'),
(9, 1, 'ApplyFilter', 'Pengguna menerapkan filter Role: superadmin', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', '2025-02-02 14:48:28'),
(10, 1, 'ApplyFilter', 'Pengguna menerapkan filter Role: superadmin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:50:40'),
(11, 1, 'ApplyFilter', 'Pengguna menerapkan filter Role: Semua', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:50:42'),
(12, 2, 'Login', 'Pengguna \'vincent\' berhasil login sebagai \'keuangan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 14:51:03'),
(13, 2, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 17.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-02 15:08:56');

-- --------------------------------------------------------

--
-- Table structure for table `employee_payheads`
--

CREATE TABLE `employee_payheads` (
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `id_payhead` int(11) NOT NULL,
  `jenis` enum('earnings','deductions') DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_payheads`
--

INSERT INTO `employee_payheads` (`id`, `id_anggota`, `id_payhead`, `jenis`, `amount`) VALUES
(0, 4, 1, 'earnings', 5000.00),
(0, 4, 2, 'deductions', 1000.00),
(0, 5, 1, 'earnings', 500000.00),
(0, 5, 7, 'earnings', 300000.00),
(0, 5, 2, 'deductions', 100000.00),
(0, 6, 1, 'earnings', 450000.00),
(0, 6, 9, 'earnings', 200000.00),
(0, 6, 5, 'deductions', 75000.00),
(0, 7, 1, 'earnings', 600000.00),
(0, 7, 8, 'earnings', 350000.00),
(0, 7, 2, 'deductions', 150000.00),
(0, 8, 1, 'earnings', 550000.00),
(0, 8, 7, 'earnings', 250000.00),
(0, 8, 5, 'deductions', 100000.00),
(0, 9, 1, 'earnings', 700000.00),
(0, 9, 8, 'earnings', 400000.00),
(0, 9, 2, 'deductions', 200000.00),
(0, 10, 1, 'earnings', 500000.00),
(0, 10, 9, 'earnings', 250000.00),
(0, 10, 5, 'deductions', 100000.00),
(0, 11, 1, 'earnings', 800000.00),
(0, 11, 7, 'earnings', 450000.00),
(0, 11, 2, 'deductions', 250000.00),
(0, 12, 1, 'earnings', 750000.00),
(0, 12, 8, 'earnings', 400000.00),
(0, 12, 5, 'deductions', 200000.00),
(0, 13, 1, 'earnings', 850000.00),
(0, 13, 8, 'earnings', 500000.00),
(0, 13, 2, 'deductions', 300000.00),
(0, 5, 1, 'earnings', 500000.00),
(0, 5, 7, 'earnings', 300000.00),
(0, 5, 2, 'deductions', 100000.00),
(0, 6, 1, 'earnings', 450000.00),
(0, 6, 9, 'earnings', 200000.00),
(0, 6, 5, 'deductions', 75000.00),
(0, 7, 1, 'earnings', 600000.00),
(0, 7, 8, 'earnings', 350000.00),
(0, 7, 2, 'deductions', 150000.00),
(0, 8, 1, 'earnings', 550000.00),
(0, 8, 7, 'earnings', 250000.00),
(0, 8, 5, 'deductions', 100000.00),
(0, 9, 1, 'earnings', 700000.00),
(0, 9, 8, 'earnings', 400000.00),
(0, 9, 2, 'deductions', 200000.00),
(0, 10, 1, 'earnings', 500000.00),
(0, 10, 9, 'earnings', 250000.00),
(0, 10, 5, 'deductions', 100000.00),
(0, 11, 1, 'earnings', 800000.00),
(0, 11, 7, 'earnings', 450000.00),
(0, 11, 2, 'deductions', 250000.00),
(0, 12, 1, 'earnings', 750000.00),
(0, 12, 8, 'earnings', 400000.00),
(0, 12, 5, 'deductions', 200000.00),
(0, 13, 1, 'earnings', 850000.00),
(0, 13, 8, 'earnings', 500000.00),
(0, 13, 2, 'deductions', 300000.00),
(0, 3, 1, '', 1000000.00),
(0, 3, 3, '', 150000.00),
(0, 3, 4, '', 200000.00),
(0, 3, 10, '', 250000.00),
(0, 14, 1, '', 600000.00),
(0, 14, 9, '', 300000.00),
(0, 14, 5, '', 150000.00),
(0, 14, 1, '', 600000.00),
(0, 14, 9, '', 300000.00),
(0, 14, 5, '', 150000.00),
(0, 14, 7, '', 500000.00),
(0, 15, 11, 'deductions', 15555.00),
(0, 16, 11, 'deductions', 150000.00),
(0, 17, 11, 'deductions', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `gaji_pokok_roles`
--

CREATE TABLE `gaji_pokok_roles` (
  `role` varchar(20) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gaji_pokok_roles`
--

INSERT INTO `gaji_pokok_roles` (`role`, `gaji_pokok`) VALUES
('guru', 5000000.00),
('karyawan', 4000000.00);

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
(1, 'Natal 2025', 'Libur Perayaan Natal', '2025-12-24', '');

-- --------------------------------------------------------

--
-- Table structure for table `payheads`
--

CREATE TABLE `payheads` (
  `id` int(11) NOT NULL,
  `nama_payhead` varchar(100) NOT NULL,
  `jenis` enum('earnings','deductions') NOT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payheads`
--

INSERT INTO `payheads` (`id`, `nama_payhead`, `jenis`, `deskripsi`) VALUES
(1, 'Tunjangan', 'earnings', 'Tunjangan Guru'),
(2, 'BPJS Ketenagakerjaan', 'deductions', 'Potongan untuk BPJS Ketenagakerjaan'),
(3, 'Tunjangan Transport', 'earnings', 'Tunjangan untuk transportasi'),
(4, 'Bonus Honor', 'earnings', 'Bonus tahunan'),
(5, 'Pajak', 'deductions', 'Potongan pajak penghasilan'),
(6, 'BPJS Kesehatan', 'deductions', 'Seumur'),
(7, 'Tunjangan Jabatan', 'earnings', 'Additional allowance based on position'),
(8, 'Tunjangan Sertifikasi', 'earnings', 'Certification allowance for qualified teachers'),
(9, 'Tunjangan Khusus Daerah', 'earnings', 'Special area allowance'),
(10, 'Potongan Koperasi', 'deductions', 'Cooperative deductions'),
(11, 'Asuransi Tambahan', 'deductions', 'Additional insurance');

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
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `total_pendapatan`, `total_potongan`, `gaji_bersih`, `created_at`, `tgl_payroll`, `no_rekening`, `catatan`) VALUES
(3, 3, 3, 1, 2025, 3200000.00, 500000.00, 300000.00, 3400000.00, '2025-01-31 03:10:00', '2025-01-30 21:57:42', NULL, NULL),
(12, 5, 4, 1, 2025, 3000000.00, 4600000.00, 200000.00, 4400000.00, '2025-01-16 03:17:42', '2025-01-30 21:57:42', NULL, NULL),
(13, 3, 5, 2, 2025, 6000000.00, 6000000.00, 0.00, 6000000.00, '2025-01-16 03:25:14', '2025-01-30 21:57:42', NULL, NULL),
(14, 3, 6, 3, 2025, 6000000.00, 7350000.00, 250000.00, 7100000.00, '2025-01-16 03:26:27', '2025-01-30 21:57:42', NULL, NULL),
(15, 14, 7, 5, 2025, 3000000.00, 5300000.00, 300000.00, 5000000.00, '2025-01-16 03:49:30', '2025-01-30 21:57:42', NULL, NULL);

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
(1, 12, 1, 'earnings', 500000.00),
(2, 12, 7, 'earnings', 300000.00),
(3, 12, 2, 'deductions', 100000.00),
(4, 12, 1, 'earnings', 500000.00),
(5, 12, 7, 'earnings', 300000.00),
(6, 12, 2, 'deductions', 100000.00),
(7, 14, 1, 'earnings', 1000000.00),
(8, 14, 3, 'earnings', 150000.00),
(9, 14, 4, 'earnings', 200000.00),
(10, 14, 10, 'deductions', 250000.00),
(11, 15, 1, 'earnings', 600000.00),
(12, 15, 9, 'earnings', 300000.00),
(13, 15, 5, 'deductions', 150000.00),
(14, 15, 1, 'earnings', 600000.00),
(15, 15, 9, 'earnings', 300000.00),
(16, 15, 5, 'deductions', 150000.00),
(17, 15, 7, 'earnings', 500000.00);

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
  `status` enum('Diterima','Pending','Ditolak') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rekap_absensi`
--

CREATE TABLE `rekap_absensi` (
  `id` int(11) NOT NULL,
  `id_anggota` int(11) NOT NULL,
  `bulan` varchar(20) NOT NULL,
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
(3, 3, '1', 2025, 18, 1, 2, 1, 0),
(4, 5, '1', 2025, 0, 0, 0, 0, 0),
(5, 3, '2', 2025, 0, 0, 0, 0, 0),
(6, 3, '3', 2025, 0, 0, 0, 0, 0),
(7, 14, '5', 2025, 0, 0, 0, 0, 0),
(8, 17, '1', 2025, 0, 0, 0, 0, 0),
(10, 17, '3', 2025, 0, 0, 0, 0, 0),
(11, 17, '2', 2025, 0, 0, 0, 0, 0);

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
(1, 'Level 0', 0, 2, 3000000.00, 'Gaji pokok untuk masa kerja 0-2 tahun'),
(2, 'Level 1', 3, 5, 4000000.00, 'Gaji pokok untuk masa kerja 3-5 tahun'),
(3, 'Level 2', 6, 10, 5000000.00, 'Gaji pokok untuk masa kerja 6-10 tahun'),
(4, 'Level 3', 11, NULL, 6000000.00, 'Gaji pokok untuk masa kerja di atas 10 tahun'),
(5, 'Level 4', 15, NULL, 7000000.00, 'Gaji pokok untuk masa kerja di atas 15 tahun');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','sdm','keuangan') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'superadmin', 'e10adc3949ba59abbe56e057f20f883e', 'superadmin', '2025-01-18 10:07:40'),
(2, 'vincent', 'e10adc3949ba59abbe56e057f20f883e', 'keuangan', '2025-02-02 14:45:52'),
(3, 'keuangan', 'e10adc3949ba59abbe56e057f20f883e', 'keuangan', '2025-02-02 14:46:07'),
(4, 'peter', 'e10adc3949ba59abbe56e057f20f883e', 'superadmin', '2025-02-02 14:46:14'),
(5, 'sdm', 'e10adc3949ba59abbe56e057f20f883e', 'sdm', '2025-02-02 14:46:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_anggota` (`id_anggota`);

--
-- Indexes for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salary_index_id` (`salary_index_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

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
-- Indexes for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_anggota` (`id_anggota`);

--
-- Indexes for table `salary_indices`
--
ALTER TABLE `salary_indices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level` (`level`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `salary_indices`
--
ALTER TABLE `salary_indices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD CONSTRAINT `fk_salary_index` FOREIGN KEY (`salary_index_id`) REFERENCES `salary_indices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee_payheads`
--
ALTER TABLE `employee_payheads`
  ADD CONSTRAINT `employee_payheads_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_payheads_ibfk_2` FOREIGN KEY (`id_payhead`) REFERENCES `payheads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`id_rekap_absensi`) REFERENCES `rekap_absensi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  ADD CONSTRAINT `payroll_detail_ibfk_1` FOREIGN KEY (`id_payroll`) REFERENCES `payroll` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_detail_ibfk_2` FOREIGN KEY (`id_payhead`) REFERENCES `payheads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  ADD CONSTRAINT `rekap_absensi_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota_sekolah` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
