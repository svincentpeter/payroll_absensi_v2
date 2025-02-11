-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2025 at 07:20 AM
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
(0, '2024-12-01', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-01', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-02', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-02 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-02 14:50:00', '-', 'cuti', 1),
(0, '2024-12-02', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-02 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-02 14:50:00', '-', 'cuti', 2),
(0, '2024-12-03', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-03 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-03 14:50:00', '-', 'hadir', 1),
(0, '2024-12-03', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-03 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-03 14:50:00', '-', 'cuti', 2),
(0, '2024-12-04', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-04 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-04 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2024-12-04', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-04 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-04 14:50:00', '-', 'hadir', 2),
(0, '2024-12-05', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-05 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-05 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2024-12-05', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-05 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-05 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-06', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-06 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-06 13:35:00', '-', 'cuti', 1),
(0, '2024-12-06', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-06 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-06 13:35:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-07', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-07', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-08', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-08', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-09', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-09 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-09 14:50:00', '-', 'sakit', 1),
(0, '2024-12-09', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-09 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-09 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-10', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-10 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-10 14:50:00', '-', 'hadir', 1),
(0, '2024-12-10', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-10 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-10 14:50:00', '-', 'hadir', 2),
(0, '2024-12-11', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-11 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-11 14:50:00', '-', 'cuti', 1),
(0, '2024-12-11', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-11 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-11 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-12', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-12 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-12 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2024-12-12', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-12 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-12 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-13', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-13 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-13 13:35:00', '-', 'cuti', 1),
(0, '2024-12-13', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-13 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-13 13:35:00', '-', 'cuti', 2),
(0, '2024-12-14', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-14', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-15', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-15', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-16', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-16 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-16 14:50:00', '-', 'sakit', 1),
(0, '2024-12-16', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-16 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-16 14:50:00', '-', 'izin', 2),
(0, '2024-12-17', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-17 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-17 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2024-12-17', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-17 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-17 14:50:00', '-', 'izin', 2),
(0, '2024-12-18', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-18 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-18 14:50:00', '-', 'hadir', 1),
(0, '2024-12-18', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-18 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-18 14:50:00', '-', 'cuti', 2),
(0, '2024-12-19', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-19 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-19 14:50:00', '-', 'cuti', 1),
(0, '2024-12-19', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-19 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-19 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-20', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-20 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-20 13:35:00', '-', 'cuti', 1),
(0, '2024-12-20', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-20 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-20 13:35:00', '-', 'cuti', 2),
(0, '2024-12-21', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-21', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-22', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-22', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-23', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-23 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-23 14:50:00', '-', 'izin', 1),
(0, '2024-12-23', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-23 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-23 14:50:00', '-', 'cuti', 2),
(0, '2024-12-24', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-24 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-24 14:50:00', '-', 'izin', 1),
(0, '2024-12-24', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-24 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-24 14:50:00', '-', 'sakit', 2),
(0, '2024-12-25', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-25 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-25 14:50:00', '-', 'izin', 1),
(0, '2024-12-25', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-25 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-25 14:50:00', '-', 'cuti', 2),
(0, '2024-12-26', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-26 06:32:00', 1, NULL, NULL, '14:45:00', '2024-12-26 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2024-12-26', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-26 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-26 14:50:00', '-', 'cuti', 2),
(0, '2024-12-27', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-27 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-27 13:35:00', '-', 'izin', 1),
(0, '2024-12-27', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-27 06:33:00', 0, NULL, NULL, '13:30:00', '2024-12-27 13:35:00', '-', 'cuti', 2),
(0, '2024-12-28', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-28', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-29', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2024-12-29', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2024-12-30', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-30 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-30 14:50:00', '-', 'hadir', 1),
(0, '2024-12-30', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-30 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-30 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2024-12-31', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2024-12-31 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-31 14:50:00', '-', 'cuti', 1),
(0, '2024-12-31', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2024-12-31 06:32:00', 0, NULL, NULL, '14:45:00', '2024-12-31 14:50:00', '-', 'hadir', 2),
(0, '2025-01-01', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-01 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-01 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2025-01-01', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-01 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-01 14:50:00', '-', 'cuti', 2),
(0, '2025-01-02', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-02 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-02 14:50:00', '-', 'izin', 1),
(0, '2025-01-02', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-02 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-02 14:50:00', '-', 'sakit', 2),
(0, '2025-01-03', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-03 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-03 13:35:00', '-', 'hadir', 1),
(0, '2025-01-03', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-03 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-03 13:35:00', '-', 'hadir', 2),
(0, '2025-01-04', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-04', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-05', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-05', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-06', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-06 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-06 14:50:00', '-', 'tanpa_keterangan', 1),
(0, '2025-01-06', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-06 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-06 14:50:00', '-', 'hadir', 2),
(0, '2025-01-07', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-07 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-07 14:50:00', '-', 'izin', 1),
(0, '2025-01-07', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-07 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-07 14:50:00', '-', 'cuti', 2),
(0, '2025-01-08', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-08 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-08 14:50:00', '-', 'cuti', 1),
(0, '2025-01-08', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-08 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-08 14:50:00', '-', 'tanpa_keterangan', 2),
(0, '2025-01-09', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-09 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-09 14:50:00', '-', 'hadir', 1),
(0, '2025-01-09', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-09 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-09 14:50:00', '-', 'hadir', 2),
(0, '2025-01-10', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-10 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-10 13:35:00', '-', 'cuti', 1),
(0, '2025-01-10', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-10 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-10 13:35:00', '-', 'cuti', 2),
(0, '2025-01-11', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-11', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-12', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-12', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-13', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-13 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-13 14:50:00', '-', 'cuti', 1),
(0, '2025-01-13', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-13 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-13 14:50:00', '-', 'hadir', 2),
(0, '2025-01-14', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-14 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-14 14:50:00', '-', 'izin', 1),
(0, '2025-01-14', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-14 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-14 14:50:00', '-', 'sakit', 2),
(0, '2025-01-15', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-15 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-15 14:50:00', '-', 'sakit', 1),
(0, '2025-01-15', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-15 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-15 14:50:00', '-', 'hadir', 2),
(0, '2025-01-16', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-16 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-16 14:50:00', '-', 'sakit', 1),
(0, '2025-01-16', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-16 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-16 14:50:00', '-', 'cuti', 2),
(0, '2025-01-17', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-17 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-17 13:35:00', '-', 'tanpa_keterangan', 1),
(0, '2025-01-17', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-17 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-17 13:35:00', '-', 'sakit', 2),
(0, '2025-01-18', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-18', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-19', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-19', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-20', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-20 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-20 14:50:00', '-', 'cuti', 1),
(0, '2025-01-20', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-20 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-20 14:50:00', '-', 'cuti', 2),
(0, '2025-01-21', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-21 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-21 14:50:00', '-', 'hadir', 1),
(0, '2025-01-21', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-21 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-21 14:50:00', '-', 'izin', 2),
(0, '2025-01-22', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-22 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-22 14:50:00', '-', 'hadir', 1),
(0, '2025-01-22', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-22 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-22 14:50:00', '-', 'hadir', 2),
(0, '2025-01-23', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-23 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-23 14:50:00', '-', 'izin', 1),
(0, '2025-01-23', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-23 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-23 14:50:00', '-', 'cuti', 2),
(0, '2025-01-24', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-24 06:33:00', 1, NULL, NULL, '13:30:00', '2025-01-24 13:35:00', '-', 'cuti', 1),
(0, '2025-01-24', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-24 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-24 13:35:00', '-', 'izin', 2),
(0, '2025-01-25', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-25', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-26', 'Libur Rutin', 'Libur Rutin', 1, '111111', '111111', 'John Doe', 'SD', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 1),
(0, '2025-01-26', 'Libur Rutin', 'Libur Rutin', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '-', 'libur', 2),
(0, '2025-01-27', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-27 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-27 14:50:00', '-', 'cuti', 1),
(0, '2025-01-27', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-27 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-27 14:50:00', '-', 'cuti', 2),
(0, '2025-01-28', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-28 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-28 14:50:00', '-', 'izin', 1),
(0, '2025-01-28', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-28 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-28 14:50:00', '-', 'cuti', 2),
(0, '2025-01-29', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-29 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-29 14:50:00', '-', 'hadir', 1),
(0, '2025-01-29', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-29 06:32:00', 1, NULL, NULL, '14:45:00', '2025-01-29 14:50:00', '-', 'hadir', 2),
(0, '2025-01-30', 'Senin-Kamis Guru', 'Senin-Kamis Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-30 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-30 14:50:00', '-', 'cuti', 1),
(0, '2025-01-30', 'Senin-Kamis Karyawan', 'Senin-Kamis Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-30 06:32:00', 0, NULL, NULL, '14:45:00', '2025-01-30 14:50:00', '-', 'hadir', 2),
(0, '2025-01-31', 'Jumat Guru', 'Jumat Guru', 1, '111111', '111111', 'John Doe', 'SD', 0, '06:30:00', '2025-01-31 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-31 13:35:00', '-', 'hadir', 1),
(0, '2025-01-31', 'Jumat Karyawan', 'Jumat Karyawan', 1, '222222', '222222', 'Jane Smith', 'SMP', 0, '06:30:00', '2025-01-31 06:33:00', 0, NULL, NULL, '13:30:00', '2025-01-31 13:35:00', '-', 'izin', 2);

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
  `nama_suami` varchar(100) DEFAULT NULL,
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

INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `job_title`, `status_kerja`, `join_start`, `masa_kerja_tahun`, `masa_kerja_bulan`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_suami`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `gaji_pokok`, `foto_profil`, `role`) VALUES
(1, 'G-001', '100001', 'e10adc3949ba59abbe56e057f20f883e', 'Ahmad Fauzi', 'SD', 'Guru Kelas 1', 'Tetap', '2017-09-01', 8, 2, 'Pengajar berpengalaman', 'L', '1980-01-15', 45, 'Islam', 'Jl. Melati No. 1', 'Jl. Melati No. 1', '1234567890', '081234567890', 'S1 Pendidikan', 'Menikah', 'ahmad.fauzi@example.com', 'Fatimah Fauzi', 2, 'Budi', 'Siti', '', 1, 4500000.00, '/payroll_absensi_v2/uploads/profile_pics/profile_1_67a9d2c5ce234.jpg', 'P'),
(2, 'G-002', '100002', 'e10adc3949ba59abbe56e057f20f883e', 'Siti Rahma', 'SMP', 'Guru IPA', 'Tetap', '2015-07-01', 10, 0, 'Cinta dengan sains', 'P', '1985-05-10', 40, 'Islam', 'Jl. Kenanga No. 2', 'Jl. Kenanga No. 2', '0987654321', '081298765432', 'S1 Pendidikan', 'Menikah', 'siti.rahma@example.com', 'Andi Rahma', 1, 'Ayu', '', '', 1, 4800000.00, 'default.jpg', 'P'),
(3, 'G-003', '100003', 'e10adc3949ba59abbe56e057f20f883e', 'Budi Santoso', 'SMA', 'Guru Bahasa', 'Tetap', '2010-01-10', 15, 3, 'Pengajar yang berdedikasi', 'L', '1975-12-25', 50, 'Kristen', 'Jl. Mawar No. 3', 'Jl. Mawar No. 3', '1122334455', '081345678901', 'S2 Pendidikan', 'Menikah', 'budi.santoso@example.com', '', 3, 'Tono', 'Rina', 'Dewi', 2, 5200000.00, 'default.jpg', 'P'),
(4, 'K-001', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Lestari', 'SMA', 'Karyawan Administrasi', 'Kontrak', '2021-02-01', 4, 1, 'Staff administrasi yang efisien', 'P', '1993-08-15', 32, 'Islam', 'Jl. Pertiwi No. 4', 'Jl. Pertiwi No. 4', '2233445566', '081234567891', 'S1 Administrasi', 'Belum Menikah', 'dewi.lestari@example.com', '', 0, '', '', '', 2, 4000000.00, 'default.jpg', 'TK'),
(5, 'K-002', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Slamet Wijaya', 'SMK', 'Karyawan Operasional', 'Tetap', '2018-06-15', 7, 0, 'Bertugas di operasional', 'L', '1988-03-05', 37, 'Islam', 'Jl. Industri No. 7', 'Jl. Industri No. 7', '3344556677', '081298700001', 'S1 Manajemen', 'Menikah', 'slamet.wijaya@example.com', 'Siti Wijaya', 1, 'Dewi', '', '', 1, 4200000.00, 'default.jpg', 'TK'),
(6, 'K-003', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Rizki Pratama', 'SMP', 'Karyawan Umum', 'Kontrak', '2023-01-20', 2, 0, 'Staff pendukung operasional', 'L', '1998-11-12', 27, 'Islam', 'Jl. Sudirman No. 8', 'Jl. Sudirman No. 8', '4455667788', '081237654321', 'D3 Manajemen', 'Belum Menikah', 'rizki.pratama@example.com', '', 0, '', '', '', 1, 3800000.00, 'default.jpg', 'TK'),
(7, 'M-001', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Andini Permata', 'SMA', 'Superadmin', 'Tetap', '2010-05-10', 15, 6, 'Memimpin sistem IT sekolah', 'P', '1978-04-22', 47, 'Islam', 'Jl. Merdeka No. 10', 'Jl. Merdeka No. 10', '5566778899', '081290123456', 'S2 Manajemen', 'Menikah', 'andini.permata@example.com', 'Budi Permata', 2, 'Tina', 'Rina', '', 3, 7500000.00, 'default.jpg', 'M'),
(8, 'M-002', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Joko Widodo', 'SMA', 'Kepala Sekolah', 'Tetap', '2008-07-01', 17, 0, 'Bertanggung jawab atas sekolah', 'L', '1965-06-21', 60, 'Islam', 'Jl. Pendidikan No. 9', 'Jl. Pendidikan No. 9', '6677889900', '081298761234', 'S2 Administrasi', 'Menikah', 'joko.widodo@example.com', 'Iriana Widodo', 3, 'Gibran', 'Khalifah', 'Puan', 4, 8000000.00, 'default.jpg', 'M'),
(9, 'M-003', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Sari Utami', 'SMK', 'Keuangan', 'Tetap', '2012-11-11', 12, 3, 'Mengelola keuangan sekolah', 'P', '1982-02-28', 43, 'Kristen', 'Jl. Simpang Lima No. 5', 'Jl. Simpang Lima No. 5', '7788990011', '081298712345', '', 'Menikah', 'sari.utami@example.com', 'Agus Utomo', 2, 'Dina', 'Rini', '', 3, 7300000.00, 'default.jpg', 'M');

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
(3, 2, 1, 'earnings', 600000.00, 'draft', NULL, NULL),
(4, 5, 3, 'earnings', 200000.00, 'revisi', NULL, NULL),
(5, 7, 2, 'deductions', 150000.00, 'draft', NULL, NULL),
(0, 8, 3, 'earnings', 150000.00, 'draft', NULL, NULL),
(0, 8, 2, 'deductions', 250000.00, 'draft', NULL, NULL),
(0, 1, 1, 'earnings', 500000.00, 'draft', NULL, NULL),
(0, 1, 2, 'deductions', 100000.00, 'draft', NULL, NULL),
(0, 1, 3, 'earnings', 600000.00, 'draft', NULL, NULL),
(0, 9, 3, 'earnings', 100000.00, 'revisi', NULL, NULL);

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
(1, 'Natal 2025', 'Libur perayaan Natal', '2025-12-24', 'wajib'),
(2, 'Tahun Baru 2025', 'Libur tahun baru', '2025-01-01', '');

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
(5, 'Koperasi', 'deductions', 'Potongan Iuran Koperasi', 150000.00);

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
(1, 1, 1, 1, 2025, 5000000.00, 600000.00, 300000.00, 5300000.00, '2025-01-31 10:00:00', '2025-01-31 09:50:00', '1111111111', 'Gaji Januari', 'final'),
(2, 2, 2, 1, 2025, 5200000.00, 700000.00, 350000.00, 5550000.00, '2025-01-31 10:10:00', '2025-01-31 09:55:00', '2222222222', 'Gaji Januari', 'final'),
(3, 5, 3, 1, 2025, 4800000.00, 500000.00, 250000.00, 5050000.00, '2025-01-31 10:20:00', '2025-01-31 10:00:00', '3333333333', 'Gaji Januari', 'final'),
(5, 1, 7, 2, 2025, 10000000.00, 1100000.00, 100000.00, 11000000.00, '2025-02-06 09:37:40', '2025-02-06 10:35:00', '1111111111', '', 'draft'),
(6, 3, NULL, 1, 2025, 5200000.00, 600000.00, 300000.00, 5500000.00, '2025-02-11 05:50:20', '2025-01-31 11:00:00', '1122334455', 'Gaji Januari', 'final'),
(7, 4, NULL, 1, 2025, 4000000.00, 500000.00, 250000.00, 4250000.00, '2025-02-11 05:50:20', '2025-01-31 11:05:00', '2233445566', 'Gaji Januari', 'final'),
(8, 6, NULL, 1, 2025, 3800000.00, 450000.00, 200000.00, 4050000.00, '2025-02-11 05:50:20', '2025-01-31 11:10:00', '4455667788', 'Gaji Januari', 'final'),
(9, 7, NULL, 1, 2025, 7500000.00, 800000.00, 350000.00, 7950000.00, '2025-02-11 05:50:20', '2025-01-31 11:15:00', '5566778899', 'Gaji Januari', 'final'),
(10, 8, NULL, 1, 2025, 8000000.00, 900000.00, 400000.00, 8500000.00, '2025-02-11 05:50:20', '2025-01-31 11:20:00', '6677889900', 'Gaji Januari', 'final'),
(11, 9, NULL, 1, 2025, 7300000.00, 800000.00, 350000.00, 7450000.00, '2025-02-11 05:50:20', '2025-01-31 11:25:00', '7788990011', 'Gaji Januari', 'final');

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
(3, 1, 2, 'deductions', 50000.00),
(4, 2, 1, 'earnings', 600000.00),
(5, 3, 2, 'deductions', 250000.00),
(6, 5, 1, 'earnings', 500000.00),
(7, 5, 2, 'deductions', 100000.00),
(8, 5, 3, 'earnings', 600000.00);

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

--
-- Dumping data for table `pengajuan_ijin`
--

INSERT INTO `pengajuan_ijin` (`id`, `nip`, `nama`, `judul_surat`, `tanggal`, `pesan`, `tipe_ijin`, `status`) VALUES
(1, 'G001', 'John Doe', 'Ijin Sakit', '2025-01-20', 'Sakit demam tinggi selama 2 hari', 'Sakit', 'Diterima'),
(2, 'K002', 'Robert Lee', 'Cuti Tahunan', '2025-02-10', 'Pengajuan cuti selama 5 hari untuk keperluan pribadi', 'Cuti Biasa', 'Pending');

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
(1, 1, 0, 2024, 4, 4, 7, 5, 2),
(2, 1, 0, 2025, 6, 5, 7, 3, 2),
(3, 2, 0, 2024, 3, 2, 9, 7, 1),
(4, 2, 0, 2025, 8, 3, 8, 1, 3),
(5, 8, 2, 2025, 0, 0, 0, 0, 0),
(6, 7, 2, 2025, 0, 0, 0, 0, 0),
(7, 1, 2, 2025, 0, 0, 0, 0, 0),
(8, 9, 4, 2025, 0, 0, 0, 0, 0),
(9, 5, 2, 2025, 0, 0, 0, 0, 0);

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
(3, 'sdm', 'e10adc3949ba59abbe56e057f20f883e', 'sdm', '2025-02-02 14:46:24');

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
