-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 05, 2025 at 05:55 AM
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
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_sekolah`
--

INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `job_title`, `status_kerja`, `join_start`, `masa_kerja_tahun`, `masa_kerja_bulan`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_suami`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `gaji_pokok`) VALUES
(1, 'G-001', '111111', 'e10adc3949ba59abbe56e057f20f883e', 'John Doe', 'SMP', 'Guru Matematika', 'Tetap', '2015-08-01', 8, 5, 'Pengalaman 10 tahun', '', '1985-05-15', 39, 'Islam', 'Jl. Merdeka No.1', 'Jl. Merdeka No.1', '1111111111', '081234567891', 'S1 Pendidikan Matematika', 'Menikah', 'john.doe@example.com', 'Jane Doe', 0, '0', '', '', 3, 5000000.00),
(2, 'G-002', '222222', 'e10adc3949ba59abbe56e057f20f883e', 'Mike Johnson', 'SMA', 'Guru Bahasa Inggris', 'Tetap', '2012-03-10', 11, 10, 'Guru senior', 'L', '1980-11-30', 44, 'Kristen', 'Jl. Kebangsaan No.3', 'Jl. Kebangsaan No.3', '2222222222', '081112223333', 'S2 Pendidikan Bahasa Inggris', 'Menikah', 'mike.johnson@example.com', 'Sarah Johnson', 3, 'Chris', 'Diana', 'Evan', 4, 5200000.00),
(3, 'G-003', '333333', 'e10adc3949ba59abbe56e057f20f883e', 'Roosalin Chintia Dewi,SE', 'TK', 'Guru Kelas TK A', 'Tetap', '2022-07-01', 2, 6, 'Berpengalaman mengajar anak TK', 'P', '1992-03-15', 32, 'Islam', 'Jl. Pendidikan No.45', 'Jl. Pendidikan No.45', '3333333333', '087765432100', 'S1 PAUD', 'Menikah', 'roosalin@example.com', 'Budi Kurniawan', 1, 'Aisya', '', '', 1, 4800000.00),
(4, 'K-001', '444444', 'e10adc3949ba59abbe56e057f20f883e', 'Jane Smith', 'SMP', 'Staf Administrasi', 'Kontrak', '2020-01-15', 3, 4, 'Admin di bagian keuangan', 'P', '1990-07-20', 34, 'Kristen', 'Jl. Pahlawan No.2', 'Jl. Pahlawan No.2', '4444444444', '081298765432', 'D3 Administrasi', 'Belum Menikah', 'jane.smith@example.com', NULL, 0, '', '', '', 2, 4000000.00),
(5, 'K-002', '555555', 'e10adc3949ba59abbe56e057f20f883e', 'Robert Lee', 'SMK', 'Karyawan Operasional', 'Tetap', '2018-05-01', 6, 0, 'Bertugas di operasional sekolah', 'L', '1988-10-10', 36, 'Islam', 'Jl. Operasional No.7', 'Jl. Operasional No.7', '5555555555', '081298700000', 'S1 Manajemen', 'Menikah', 'robert.lee@example.com', 'Anna Lee', 2, 'Tom', 'Jerry', '', 1, 4200000.00),
(6, 'G-004', '666666', 'e10adc3949ba59abbe56e057f20f883e', 'Rina Septiani', 'SD', 'Guru Kelas 3 SD', 'Tetap', '2015-08-15', 9, 5, 'Guru berpengalaman', 'P', '1985-06-25', 39, 'Kristen', 'Jl. Pendidikan No.78', 'Jl. Pendidikan No.78', '6666666666', '081234567890', 'S1 Pendidikan Dasar', 'Menikah', 'rina.septiani@example.com', 'Dedi Septiani', 2, 'Lia', 'Mia', '', 2, 5000000.00),
(7, 'K-003', '777777', 'e10adc3949ba59abbe56e057f20f883e', 'Robert S.', 'SMK', 'Karyawan Administrasi', 'Kontrak', '2023-01-01', 1, 0, 'Bertugas di administrasi umum', 'L', '1995-04-15', 29, 'Islam', 'Jl. Administrasi No.5', 'Jl. Administrasi No.5', '7777777777', '081234500000', 'S1 Administrasi', 'Belum Menikah', 'robert.s@example.com', NULL, 0, '', '', '', NULL, 4000000.00),
(8, 'G-005', '888888', 'e10adc3949ba59abbe56e057f20f883e', 'Hendra Gunawan', 'SD', 'Guru Olahraga SD', 'Tetap', '2018-03-01', 6, 10, 'Mengajar olahraga', '', '1988-09-10', 36, 'Islam', 'Jl. Olahraga No.12', 'Jl. Olahraga No.12', '8888888888', '085432109870', 'S1 Keolahragaan', '', 'hendra.g@example.com', 'Maya Gunawan', 1, '0', '', '', 2, 5100000.00);

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
(1, 1, 'ViewUserDetail', 'Melihat detail User: superadmin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2025-02-02 14:45:40'),
(2, 2, 'CreateGuru', 'Menambah data guru: John Doe (G-001)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2025-02-03 09:15:00'),
(3, 2, 'UpdateGuru', 'Mengupdate data guru: Mike Johnson (G-002)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2025-02-03 09:45:00'),
(4, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:12:17'),
(5, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:12:32'),
(6, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:35:17'),
(7, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:35:22'),
(8, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 8: NIP=\'888888\', Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:35:23'),
(9, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:41:59'),
(10, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 8: NIP=\'888888\', Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:42:01'),
(11, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 08:49:42'),
(12, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2025-02-03 09:00:04'),
(13, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:11:03'),
(14, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 2: Nama=\'Mike Johnson\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:11:15'),
(15, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 3: Nama=\'Roosalin Chintia Dewi,SE\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:11:22'),
(16, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:13:32'),
(17, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:14:16'),
(18, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:14:19'),
(19, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:19:24'),
(20, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:21:37'),
(21, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:21:43'),
(22, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:28:18'),
(23, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:28:23'),
(24, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:28:32'),
(25, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:28:55'),
(26, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:29:04'),
(27, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:29:09'),
(28, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:39:54'),
(29, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:39:59'),
(30, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:41:01'),
(31, 1, 'UpdateHoliday', 'Mengupdate Hari Libur ID 2: Judul=\'Tahun Baru 2025\', Tanggal=\'2025-01-01\', Jenis=\'wajib\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:41:57'),
(32, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:45:58'),
(33, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:45:58'),
(34, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:50:45'),
(35, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 15:51:02'),
(36, 1, 'Login', 'Pengguna \'superadmin\' berhasil login sebagai \'superadmin\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:41:16'),
(37, 1, 'ApplyFilter', 'Pengguna menerapkan filter data guru/karyawan.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:43:43'),
(38, 1, 'ApplyFilter', 'Pengguna menerapkan filter data guru/karyawan.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:43:46'),
(39, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:43:48'),
(40, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:52:13'),
(41, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:52:16'),
(42, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:53:40'),
(43, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:53:44'),
(44, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:53:56'),
(45, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:53:58'),
(46, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 17:55:37'),
(47, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:03:47'),
(48, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:03:50'),
(49, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:03:55'),
(50, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:03:59'),
(51, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:04:02'),
(52, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:04:07'),
(53, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:06:11'),
(54, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:06:13'),
(55, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:06:34'),
(56, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:07:31'),
(57, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:14:55'),
(58, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:14:58'),
(59, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:15:00'),
(60, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:15:55'),
(61, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:15:58'),
(62, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 2: Nama=\'Mike Johnson\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:16:13'),
(63, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:16:20'),
(64, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 8: NIP=\'888888\', Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:16:28'),
(65, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:16:31'),
(66, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 8: Nama=\'Hendra Gunawan\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:16:40'),
(67, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 4: Nama=\'Jane Smith\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:17:08'),
(68, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 5: Nama=\'Robert Lee\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:17:11'),
(69, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:23:14'),
(70, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:23:17'),
(71, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:23:25'),
(72, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:23:27'),
(73, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 4: Nama=\'Jane Smith\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:25:00'),
(74, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:31:35'),
(75, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:31:40'),
(76, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:31:43'),
(77, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:31:46'),
(78, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:31:48'),
(79, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:31:54'),
(80, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:32:00'),
(81, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:32:02'),
(82, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:32:06'),
(83, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:37:43'),
(84, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:37:47'),
(85, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:37:52'),
(86, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:37:53'),
(87, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:37:54'),
(88, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:13'),
(89, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 2: Nama=\'Mike Johnson\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:18'),
(90, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:25'),
(91, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:30'),
(92, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 2: Nama=\'Mike Johnson\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:32'),
(93, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:35'),
(94, 1, 'UpdateGuru', 'Mengupdate data guru/karyawan ID 1: NIP=\'111111\', Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:40'),
(95, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:38:42'),
(96, 1, 'GetGuruDetail', 'Melihat detail data guru/karyawan ID 1: Nama=\'John Doe\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-03 18:40:42'),
(97, 1, 'Login', 'Pengguna \'superadmin\' berhasil login sebagai \'superadmin\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 12:36:15'),
(98, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:37:40'),
(99, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:37:47'),
(100, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:37:51'),
(101, 1, 'GetAllPayheads', 'Mengakses semua payheads.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:37:51'),
(102, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:37:53'),
(103, 1, 'GetAllPayheads', 'Mengakses semua payheads.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:37:53'),
(104, 1, 'AssignPayheadsToEmployee', 'Menetapkan payheads ke karyawan ID 8. Payheads: 3, 2.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:38:07'),
(105, 1, 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 8 pada bulan  tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:38:11'),
(106, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 7.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:45:59'),
(107, 1, 'GetAllPayheads', 'Mengakses semua payheads.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:45:59'),
(108, 1, 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 7 pada bulan  tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:46:04'),
(109, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:54:55'),
(110, 1, 'GetAllPayheads', 'Mengakses semua payheads.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 14:54:55'),
(111, 1, 'ViewEmployeeDetail', 'Mengakses detail karyawan ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 15:01:13'),
(112, 1, 'GetAllPayheads', 'Mengakses semua payheads.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 15:01:13'),
(113, 1, 'AssignPayheadsToEmployee', 'Menetapkan payheads ke karyawan ID 8. Payheads: 3, 2.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 15:01:15'),
(114, 1, 'ViewPayroll', 'Mengakses Review Payroll untuk Karyawan ID 8 pada bulan 2 tahun 2025.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-04 15:01:18'),
(115, 1, 'Login', 'Pengguna \'superadmin\' berhasil login sebagai \'superadmin\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 03:58:43'),
(116, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:06'),
(117, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:07'),
(118, 1, 'LoadingRekapPayroll', 'Pengguna dengan ID 1 dan peran \'superadmin\' memuat data rekap payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:07'),
(119, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll Details untuk jenjang \'SMA\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:10'),
(120, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll Details untuk jenjang \'SMA\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:10'),
(121, 1, 'LoadingRekapPayrollDetails', 'Pengguna dengan ID 1 memuat detail rekap payroll untuk jenjang \'SMA\'.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:10'),
(122, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:11'),
(123, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:11'),
(124, 1, 'LoadingRekapPayroll', 'Pengguna dengan ID 1 dan peran \'superadmin\' memuat data rekap payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:33:11'),
(125, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:38:23'),
(126, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:40:38'),
(127, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:41:39'),
(128, 1, 'AccessPage', 'Pengguna dengan ID 1 dan peran \'superadmin\' mengakses halaman Rekap Payroll.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', '2025-02-05 04:42:15');

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
(1, 1, 1, 'earnings', 500000.00),
(2, 1, 2, 'deductions', 100000.00),
(3, 2, 1, 'earnings', 600000.00),
(4, 5, 3, 'earnings', 200000.00),
(5, 7, 2, 'deductions', 150000.00),
(0, 8, 3, 'earnings', 150000.00),
(0, 8, 2, 'deductions', 250000.00);

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
(1, 'Tunjangan Tetap', 'earnings', 'Tunjangan dasar untuk guru/karyawan'),
(2, 'Potongan Pajak', 'deductions', 'Potongan pajak penghasilan'),
(3, 'Bonus Kinerja', 'earnings', 'Bonus berdasarkan kinerja'),
(4, 'Potongan BPJS', 'deductions', 'Potongan BPJS Kesehatan');

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
(1, 1, 1, 1, 2025, 5000000.00, 600000.00, 300000.00, 5300000.00, '2025-01-31 10:00:00', '2025-01-31 09:50:00', '1111111111', 'Gaji Januari'),
(2, 2, 2, 1, 2025, 5200000.00, 700000.00, 350000.00, 5550000.00, '2025-01-31 10:10:00', '2025-01-31 09:55:00', '2222222222', 'Gaji Januari'),
(3, 5, 3, 1, 2025, 4800000.00, 500000.00, 250000.00, 5050000.00, '2025-01-31 10:20:00', '2025-01-31 10:00:00', '3333333333', 'Gaji Januari');

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
(5, 3, 2, 'deductions', 250000.00);

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
(0, 1, 'December', 2024, 4, 4, 7, 5, 2),
(0, 1, 'January', 2025, 6, 5, 7, 3, 2),
(0, 2, 'December', 2024, 3, 2, 9, 7, 1),
(0, 2, 'January', 2025, 8, 3, 8, 1, 3),
(0, 8, '2', 2025, 0, 0, 0, 0, 0),
(0, 7, '2', 2025, 0, 0, 0, 0, 0);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
