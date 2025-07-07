-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 07, 2025 at 07:03 AM
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
-- Database: `payroll_absensi_dummy`
--

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
  `jenjang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `unit_penempatan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `strata` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `job_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_kerja` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `join_start` date DEFAULT NULL,
  `lama_kontrak` int UNSIGNED DEFAULT NULL,
  `tgl_kontrak_selesai` date DEFAULT NULL,
  `sudah_kontrak` int NOT NULL DEFAULT '0',
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
  `foto_ktp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'default_ktp.jpg',
  `role` enum('P','TK','M') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_delete` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `kategori` enum('guru','karyawan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `faskes_bpjs` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=tidak terdaftar,1=terdaftar',
  `faskes_inhealth` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=tidak,1=terdaftar',
  `faskes_ket` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'keterangan fasilitas',
  `gaji_strata` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_increment` decimal(15,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota_sekolah`
--

INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `unit_penempatan`, `strata`, `job_title`, `status_kerja`, `join_start`, `lama_kontrak`, `tgl_kontrak_selesai`, `sudah_kontrak`, `masa_kerja_tahun`, `masa_kerja_bulan`, `masa_kerja_efektif`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_pasangan`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `salary_index_level`, `gaji_pokok`, `foto_profil`, `foto_ktp`, `role`, `is_delete`, `deleted_at`, `kategori`, `faskes_bpjs`, `faskes_inhealth`, `faskes_ket`, `gaji_strata`, `total_increment`) VALUES
(1, '09', '900001', 'e10adc3949ba59abbe56e057f20f883e', 'A. Ratna Wulandari, SE, M.Si', 'MANAJER', NULL, NULL, 'SDM', 'Tetap', '2010-10-01', NULL, NULL, 0, 14, 8, 14.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'M', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(2, '09', '900002', 'e10adc3949ba59abbe56e057f20f883e', 'Yolanda Dipa, SE', 'MANAJER', NULL, NULL, 'Keuangan', 'Tetap', '2009-08-01', NULL, NULL, 0, 15, 10, 15.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'M', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(3, '09', '900003', 'e10adc3949ba59abbe56e057f20f883e', 'Kabut Hadi Saputra, ST', 'MANAJER', NULL, NULL, 'Superadmin', 'Tetap', '2014-04-04', NULL, NULL, 0, 11, 2, 11.17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'M', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(4, '09', '900004', 'e10adc3949ba59abbe56e057f20f883e', 'Linda Susilawati Kawidjaja,S.Pd.,N', 'MANAJER', NULL, NULL, 'SDM', 'Tetap', '1989-07-01', NULL, NULL, 0, 35, 11, 35.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'M', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(5, '01', '100001', 'e10adc3949ba59abbe56e057f20f883e', 'Retno Nur Astuti SPd', 'TK', NULL, NULL, 'Guru', 'Tetap', '2014-03-11', NULL, NULL, 0, 11, 3, 11.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(6, '01', '100002', 'e10adc3949ba59abbe56e057f20f883e', 'Jauw gabriell fabiola pratikno', 'TK', NULL, NULL, 'Guru', 'Tetap', '2023-08-11', NULL, NULL, 0, 1, 10, 1.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(7, '01', '100003', 'e10adc3949ba59abbe56e057f20f883e', 'Lauti Retnaning Wulan, S.S', 'TK', NULL, NULL, 'Guru', 'Tetap', '2024-07-15', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(8, '01', '100004', 'e10adc3949ba59abbe56e057f20f883e', 'Tjendana Maha Hendrawati Anggraini SE', 'TK', NULL, NULL, 'Guru', 'Tetap', '2024-07-15', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(9, '01', '100005', 'e10adc3949ba59abbe56e057f20f883e', 'Florentina Wira Hastari,S.Pd', 'TK', NULL, NULL, 'Guru', 'Tetap', '2012-07-02', NULL, NULL, 0, 12, 11, 12.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(10, '01', '100006', 'e10adc3949ba59abbe56e057f20f883e', 'Roosalin Chintia Dewi S.E', 'TK', NULL, NULL, 'Guru', 'Tetap', '2024-07-16', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(11, '01', '100007', 'e10adc3949ba59abbe56e057f20f883e', 'Koo, Josephine Irma Koerniawan,B.Ed', 'TK', NULL, NULL, 'Guru', 'Tetap', '2021-07-11', NULL, NULL, 0, 3, 11, 3.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(12, '01', '100008', 'e10adc3949ba59abbe56e057f20f883e', 'Yuliana Poniyati,S.Pd', 'TK', NULL, NULL, 'Guru', 'Tetap', '2018-06-20', NULL, NULL, 0, 6, 11, 6.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(13, '02', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'FRIDA DWI SISWARI,S.PD.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2003-07-01', NULL, NULL, 0, 21, 11, 21.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(14, '02', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Hasan Basri, S.Pd', 'SD', NULL, NULL, 'Guru', 'Tetap', '2020-01-07', NULL, NULL, 0, 5, 5, 5.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(15, '02', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Puji Rahayu, S.Th.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2012-07-03', NULL, NULL, 0, 12, 11, 12.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(16, '02', '200004', 'e10adc3949ba59abbe56e057f20f883e', 'Jiem, Sabrina Oktaviani Gunawan, B.ed', 'SD', NULL, NULL, 'Guru', 'Tetap', '2024-07-15', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(17, '02', '200005', 'e10adc3949ba59abbe56e057f20f883e', 'Antonius Suraji, S.Pd.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2005-03-15', NULL, NULL, 0, 20, 3, 20.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(18, '02', '200006', 'e10adc3949ba59abbe56e057f20f883e', 'Auring Heranu Permatasari, S.Pd', 'SD', NULL, NULL, 'Guru', 'Tetap', '2023-07-17', NULL, NULL, 0, 1, 10, 1.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(19, '02', '200007', 'e10adc3949ba59abbe56e057f20f883e', 'Tutut Idharwati, S.Pd.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2024-08-13', NULL, NULL, 0, 0, 10, 0.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(20, '02', '200008', 'e10adc3949ba59abbe56e057f20f883e', 'Prima Widyatmoko, S.Pd. M.P.d', 'SD', NULL, NULL, 'Guru', 'Tetap', '2018-08-01', NULL, NULL, 0, 6, 10, 6.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(21, '02', '200009', 'e10adc3949ba59abbe56e057f20f883e', 'HAN NING RUM, S.Pd.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2006-07-12', NULL, NULL, 0, 18, 11, 18.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(22, '02', '200010', 'e10adc3949ba59abbe56e057f20f883e', 'David Prima Ardyan, S. Kom', 'SD', NULL, NULL, 'Guru', 'Tetap', '2024-07-19', NULL, NULL, 0, 0, 10, 0.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(23, '02', '200011', 'e10adc3949ba59abbe56e057f20f883e', 'Florentia Ivony Wokabelolo,S.Pd.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2024-02-26', NULL, NULL, 0, 1, 3, 1.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(24, '02', '200012', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Rizqi Maharani, S.Pd', 'SD', NULL, NULL, 'Guru', 'Tetap', '2011-07-01', NULL, NULL, 0, 13, 11, 13.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(25, '02', '200013', 'e10adc3949ba59abbe56e057f20f883e', 'Henny Ayu Pramesti, S. Si.', 'SD', NULL, NULL, 'Guru', 'Tetap', '2024-07-11', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(26, '02', '200014', 'e10adc3949ba59abbe56e057f20f883e', 'Galih Mahendra S.Kom', 'SD', NULL, NULL, 'Guru', 'Tetap', '2013-02-25', NULL, NULL, 0, 12, 3, 12.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(27, '02', '200015', 'e10adc3949ba59abbe56e057f20f883e', 'Wisnu Wijaya,S.Akt', 'SD', NULL, NULL, 'Guru', 'Tetap', '2016-09-01', NULL, NULL, 0, 8, 9, 8.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(28, '02', '200016', 'e10adc3949ba59abbe56e057f20f883e', 'Elisabeth Anastasia G.C.B.,B.Ed', 'SD', NULL, NULL, 'Guru', 'Tetap', '2023-03-13', NULL, NULL, 0, 2, 3, 2.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(29, '02', '200017', 'e10adc3949ba59abbe56e057f20f883e', 'Nathania Yolanda Setiawan,B.Ed', 'SD', NULL, NULL, 'Guru', 'Tetap', '2024-07-15', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(30, '03', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Tentrem Al Trima,S.Pd', 'SMP', NULL, NULL, 'Guru', 'Tetap', '1997-04-01', NULL, NULL, 0, 28, 2, 28.17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(31, '03', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Yoga Huda Nada, S.Pd', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2020-07-01', NULL, NULL, 0, 4, 11, 4.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(32, '03', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Florentina Suganda, S.M.', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2023-03-08', NULL, NULL, 0, 2, 3, 2.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(33, '03', '300004', 'e10adc3949ba59abbe56e057f20f883e', 'Umi Kasiyati, S. Pd.', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2006-02-01', NULL, NULL, 0, 19, 4, 19.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(34, '03', '300005', 'e10adc3949ba59abbe56e057f20f883e', 'Hananeel Tesalonika Supriyadi, S.Psi', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2024-09-02', NULL, NULL, 0, 0, 9, 0.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(35, '03', '300006', 'e10adc3949ba59abbe56e057f20f883e', 'Dwi Yunianto, S.Pd.', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2014-12-15', NULL, NULL, 0, 10, 6, 10.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(36, '03', '300007', 'e10adc3949ba59abbe56e057f20f883e', 'Fathurohim', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2012-09-01', NULL, NULL, 0, 12, 9, 12.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(37, '03', '300008', 'e10adc3949ba59abbe56e057f20f883e', 'Karina, S.Pd.', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2024-01-25', NULL, NULL, 0, 1, 4, 1.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(38, '03', '300009', 'e10adc3949ba59abbe56e057f20f883e', 'Partiwi, S. Pd.', 'SMP', NULL, NULL, 'Guru', 'Tetap', '1994-07-01', NULL, NULL, 0, 30, 11, 30.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(39, '03', '300010', 'e10adc3949ba59abbe56e057f20f883e', 'Eko Budi Hendiko, S.Si.', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2005-01-15', NULL, NULL, 0, 20, 5, 20.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(40, '03', '300011', 'e10adc3949ba59abbe56e057f20f883e', 'Theofilus Riyanto, S.Th', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2019-09-03', NULL, NULL, 0, 5, 9, 5.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(41, '03', '300012', 'e10adc3949ba59abbe56e057f20f883e', 'Koo, Enrico Satya Koerniawan,B.Ed', 'SMP', NULL, NULL, 'Guru', 'Tetap', '2023-07-17', NULL, NULL, 0, 1, 10, 1.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(42, '04', '400001', 'e10adc3949ba59abbe56e057f20f883e', 'Zaldy Chandra, S.Si', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2007-10-31', NULL, NULL, 0, 17, 7, 17.58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(43, '04', '400002', 'e10adc3949ba59abbe56e057f20f883e', 'Bambang Setiawan, S.Pd., M.Pd', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2022-01-25', NULL, NULL, 0, 3, 4, 3.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(44, '04', '400003', 'e10adc3949ba59abbe56e057f20f883e', 'Cynthia Christiana, B.Ed', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2021-07-11', NULL, NULL, 0, 3, 11, 3.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(45, '04', '400004', 'e10adc3949ba59abbe56e057f20f883e', 'Fransiskus Xaverius Aris Wahyu Prasetyo, M.Ed', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2022-05-19', NULL, NULL, 0, 3, 0, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(46, '04', '400005', 'e10adc3949ba59abbe56e057f20f883e', 'Rico Yuliar Wicaksono, S.Pd', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2016-02-19', NULL, NULL, 0, 9, 3, 9.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(47, '04', '400006', 'e10adc3949ba59abbe56e057f20f883e', 'Gloria Putri Ixora, S.Pd.', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2024-09-09', NULL, NULL, 0, 0, 9, 0.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(48, '04', '400007', 'e10adc3949ba59abbe56e057f20f883e', 'Edi Santoso, S.Pd', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2024-09-02', NULL, NULL, 0, 0, 9, 0.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(49, '04', '400008', 'e10adc3949ba59abbe56e057f20f883e', 'Mely Isnaeni, S.Pd.', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2024-07-18', NULL, NULL, 0, 0, 10, 0.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(50, '04', '400009', 'e10adc3949ba59abbe56e057f20f883e', 'Frisca Kristya Dewi, S.Psi', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2024-09-02', NULL, NULL, 0, 0, 9, 0.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(51, '04', '400010', 'e10adc3949ba59abbe56e057f20f883e', 'Levi Yunitasari, S.Pd Gr.', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2016-01-06', NULL, NULL, 0, 9, 5, 9.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(52, '04', '400011', 'e10adc3949ba59abbe56e057f20f883e', 'Yuliana Widjanjingtias', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2003-04-03', NULL, NULL, 0, 22, 2, 22.17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(53, '04', '400012', 'e10adc3949ba59abbe56e057f20f883e', 'Yuniarti, S.S., M.Pd.', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2006-06-10', NULL, NULL, 0, 19, 0, 19.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(54, '04', '400013', 'e10adc3949ba59abbe56e057f20f883e', 'Dinar Setiawan, S.Kom', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2013-07-17', NULL, NULL, 0, 11, 10, 11.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(55, '04', '400014', 'e10adc3949ba59abbe56e057f20f883e', 'Emanuel Suryajaya, S.Kom', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2022-09-02', NULL, NULL, 0, 2, 9, 2.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(56, '04', '400015', 'e10adc3949ba59abbe56e057f20f883e', 'Mega Asterina', 'SMA', NULL, NULL, 'Guru', 'Tetap', '2021-01-04', NULL, NULL, 0, 4, 5, 4.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(57, '05', '500001', 'e10adc3949ba59abbe56e057f20f883e', 'Joko Riyanto S. Kom. MM. Gr. Gp.', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2008-07-21', NULL, NULL, 0, 16, 10, 16.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(58, '05', '500002', 'e10adc3949ba59abbe56e057f20f883e', 'Ester TryLestari Silalahi, S.Pd.', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2023-06-27', NULL, NULL, 0, 1, 11, 1.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(59, '05', '500003', 'e10adc3949ba59abbe56e057f20f883e', 'Tripitoyo, S.Pd', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2020-07-15', NULL, NULL, 0, 4, 11, 4.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(60, '05', '500004', 'e10adc3949ba59abbe56e057f20f883e', 'Priskila Narulitasari', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2024-02-27', NULL, NULL, 0, 1, 3, 1.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(61, '05', '500005', 'e10adc3949ba59abbe56e057f20f883e', 'Chr​isma Purwa Mahendra, S.Ds.', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2023-10-02', NULL, NULL, 0, 1, 8, 1.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(62, '05', '500006', 'e10adc3949ba59abbe56e057f20f883e', 'Melinda Safitri, S.Kom', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2022-07-18', NULL, NULL, 0, 2, 10, 2.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(63, '05', '500007', 'e10adc3949ba59abbe56e057f20f883e', 'Syaiful Anas', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2019-07-01', NULL, NULL, 0, 5, 11, 5.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(64, '05', '500008', 'e10adc3949ba59abbe56e057f20f883e', 'Timmy Gondo Atmodjo, ST., M.Kom.', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2010-07-01', NULL, NULL, 0, 14, 11, 14.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(65, '05', '500009', 'e10adc3949ba59abbe56e057f20f883e', 'Drs. Ariawan Sudagijono, M.Kom.', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2006-01-02', NULL, NULL, 0, 19, 5, 19.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(66, '05', '500010', 'e10adc3949ba59abbe56e057f20f883e', 'Nining Tri Palupi, SPd, MPd', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '1995-08-01', NULL, NULL, 0, 29, 10, 29.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(67, '05', '500011', 'e10adc3949ba59abbe56e057f20f883e', 'Amanda Geraldine M., B.Ed', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2023-07-17', NULL, NULL, 0, 1, 10, 1.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(68, '05', '500012', 'e10adc3949ba59abbe56e057f20f883e', 'Vincentius Sam Yolando Rekso Samudro', 'SMK1', NULL, NULL, 'Guru', 'Tetap', '2022-11-17', NULL, NULL, 0, 2, 6, 2.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(69, '06', '600001', 'e10adc3949ba59abbe56e057f20f883e', 'Bayu Candra Wijaya, S.Pd', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2021-09-06', NULL, NULL, 0, 3, 9, 3.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(70, '06', '600002', 'e10adc3949ba59abbe56e057f20f883e', 'DRS. HERNO AGUS PURWANTO, APT.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '1996-10-01', NULL, NULL, 0, 28, 8, 28.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(71, '06', '600003', 'e10adc3949ba59abbe56e057f20f883e', 'Drs. Fery Norhendy, Apt.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2000-07-01', NULL, NULL, 0, 24, 11, 24.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(72, '06', '600004', 'e10adc3949ba59abbe56e057f20f883e', 'Margareta Nini Moeljati, S.KM., M. Par., M. Si.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2019-07-15', NULL, NULL, 0, 5, 11, 5.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(73, '06', '600005', 'e10adc3949ba59abbe56e057f20f883e', 'Rita Andayani', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '1992-10-12', NULL, NULL, 0, 32, 8, 32.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(74, '06', '600006', 'e10adc3949ba59abbe56e057f20f883e', 'Rini Roslianti, AMd Farm, S.Sos', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2024-01-02', NULL, NULL, 0, 1, 5, 1.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(75, '06', '600007', 'e10adc3949ba59abbe56e057f20f883e', 'Peni Indaryanti, ST', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '1994-07-01', NULL, NULL, 0, 30, 11, 30.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(76, '06', '600008', 'e10adc3949ba59abbe56e057f20f883e', 'Ika Lestarinningsih, A.Md.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2006-06-21', NULL, NULL, 0, 18, 11, 18.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(77, '06', '600009', 'e10adc3949ba59abbe56e057f20f883e', 'Apt. Maya Ary Wardhani, S. Farm', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2012-07-13', NULL, NULL, 0, 12, 11, 12.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(78, '06', '600010', 'e10adc3949ba59abbe56e057f20f883e', 'Achmad Faozan', 'SMK2', NULL, NULL, 'Guru', 'Tetap', NULL, NULL, NULL, 0, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(79, '06', '600011', 'e10adc3949ba59abbe56e057f20f883e', 'Wamelinda Dwi W., S.Farm', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2023-07-13', NULL, NULL, 0, 1, 11, 1.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(80, '06', '600012', 'e10adc3949ba59abbe56e057f20f883e', 'Novi Istiani', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2013-09-02', NULL, NULL, 0, 11, 9, 11.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(81, '06', '600013', 'e10adc3949ba59abbe56e057f20f883e', 'NINUNG WAHYU HANA PERTIWI, S.Tr.Par.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2011-08-01', NULL, NULL, 0, 13, 10, 13.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(82, '06', '600014', 'e10adc3949ba59abbe56e057f20f883e', 'Dian Listriana Y., S.Si., Apt.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2007-07-17', NULL, NULL, 0, 17, 10, 17.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(83, '06', '600015', 'e10adc3949ba59abbe56e057f20f883e', 'A kasiman', 'SMK2', NULL, NULL, 'Guru', 'Tetap', NULL, NULL, NULL, 0, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(84, '06', '600016', 'e10adc3949ba59abbe56e057f20f883e', 'Vica Anggraeni Puspitasari, S.Pd.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2024-07-01', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(85, '06', '600017', 'e10adc3949ba59abbe56e057f20f883e', 'Muhamad Syafiq Naim, S.Pd.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2022-02-15', NULL, NULL, 0, 3, 4, 3.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(86, '06', '600018', 'e10adc3949ba59abbe56e057f20f883e', 'Riyanti', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2013-01-09', NULL, NULL, 0, 12, 5, 12.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(87, '06', '600019', 'e10adc3949ba59abbe56e057f20f883e', 'Dicky Adi Kurniawan, S.Pd', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2022-07-06', NULL, NULL, 0, 2, 11, 2.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(88, '06', '600020', 'e10adc3949ba59abbe56e057f20f883e', 'Asinik Soedjono, SE, MM', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2007-10-10', NULL, NULL, 0, 17, 8, 17.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(89, '06', '600021', 'e10adc3949ba59abbe56e057f20f883e', 'SOPHIA SARASWATI HABSARI SUMARTO, S. Farm., Apt.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2012-07-01', NULL, NULL, 0, 12, 11, 12.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(90, '06', '600022', 'e10adc3949ba59abbe56e057f20f883e', 'Oei Poe Jen, AMD Farm', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2010-01-20', NULL, NULL, 0, 15, 4, 15.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(91, '06', '600023', 'e10adc3949ba59abbe56e057f20f883e', 'Kamila Kurnia Sari, S.Pd.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2025-02-18', NULL, NULL, 0, 0, 3, 0.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(92, '06', '600024', 'e10adc3949ba59abbe56e057f20f883e', 'Selvanika Fergi Purba Mardista, S.Pd., Kons.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2022-02-04', NULL, NULL, 0, 3, 4, 3.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(93, '06', '600025', 'e10adc3949ba59abbe56e057f20f883e', 'Imamatulatifah, S.Si., Apt', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2003-10-01', NULL, NULL, 0, 21, 8, 21.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(94, '06', '600026', 'e10adc3949ba59abbe56e057f20f883e', 'Cucu Tri Eka Yuliana, S.Kom.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2014-09-01', NULL, NULL, 0, 10, 9, 10.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(95, '06', '600027', 'e10adc3949ba59abbe56e057f20f883e', 'Bilozer Ngastivio Hastunar, S.Si', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2024-07-01', NULL, NULL, 0, 0, 11, 0.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(96, '06', '600028', 'e10adc3949ba59abbe56e057f20f883e', 'Naada Zakiyah, S.Tr.Par.', 'SMK2', NULL, NULL, 'Guru', 'Tetap', '2024-09-04', NULL, NULL, 0, 0, 9, 0.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(97, '07', '700001', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Rizky Ardian Hartanto Sawal, M.Farm.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2017-11-06', NULL, NULL, 0, 7, 7, 7.58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(98, '07', '700002', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Eleonora Maryeta Toyo, M. Farm', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2019-05-23', NULL, NULL, 0, 6, 0, 6.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(99, '07', '700003', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Sri Suwarni, M. Sc.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2007-07-16', NULL, NULL, 0, 17, 11, 17.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(100, '07', '700004', 'e10adc3949ba59abbe56e057f20f883e', 'Poppy Diah Palupi, M.Sc., Apt', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2007-07-16', NULL, NULL, 0, 17, 11, 17.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(101, '07', '700005', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Wahyu Setiyaningsih, M.Farm.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2020-03-09', NULL, NULL, 0, 5, 3, 5.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(102, '07', '700006', 'e10adc3949ba59abbe56e057f20f883e', 'Rima Oktaliani', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2013-12-23', NULL, NULL, 0, 11, 5, 11.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(103, '07', '700007', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Sandi Mahesa Yudhantra, M.Farm', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2019-04-04', NULL, NULL, 0, 6, 2, 6.17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(104, '07', '700008', 'e10adc3949ba59abbe56e057f20f883e', 'Deddy Christsetyadi, S.E.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2021-06-22', NULL, NULL, 0, 3, 11, 3.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(105, '07', '700009', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Ferika Indra Sari, S.Farm., MH', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2015-07-06', NULL, NULL, 0, 9, 11, 9.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(106, '07', '700010', 'e10adc3949ba59abbe56e057f20f883e', 'Margareta Retno Priamsari', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2016-01-08', NULL, NULL, 0, 9, 5, 9.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(107, '07', '700011', 'e10adc3949ba59abbe56e057f20f883e', 'Ayu Ina Solichah, M.Pharm.Sci.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2012-12-04', NULL, NULL, 0, 12, 6, 12.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(108, '07', '700012', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Odilia Dea Christina, M.Farm', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2020-09-23', NULL, NULL, 0, 4, 8, 4.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(109, '07', '700013', 'e10adc3949ba59abbe56e057f20f883e', 'Ayu Novita Dewi, S.E', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2021-12-14', NULL, NULL, 0, 3, 6, 3.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(110, '07', '700014', 'e10adc3949ba59abbe56e057f20f883e', 'Tiara Sekar Putri Hardiningrum A.Md.S.I', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2022-10-17', NULL, NULL, 0, 2, 7, 2.58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(111, '07', '700015', 'e10adc3949ba59abbe56e057f20f883e', 'apt. Agustina Putri Pitarisa Sudarsono, M.Pharm.Sci.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2012-07-01', NULL, NULL, 0, 12, 11, 12.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(112, '07', '700016', 'e10adc3949ba59abbe56e057f20f883e', 'Dr. Buanasari, S.T., M.T', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2010-11-01', NULL, NULL, 0, 14, 7, 14.58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(113, '07', '700017', 'e10adc3949ba59abbe56e057f20f883e', 'Yithro Serang, M.Farm., Apt', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2015-03-05', NULL, NULL, 0, 10, 3, 10.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(114, '07', '700018', 'e10adc3949ba59abbe56e057f20f883e', 'Metrikana Novembrina, M.Sc, Apt', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2015-11-23', NULL, NULL, 0, 9, 6, 9.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(115, '07', '700019', 'e10adc3949ba59abbe56e057f20f883e', 'Khairullah Mahdi Murdiansyah, S.Kom.', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2019-01-07', NULL, NULL, 0, 6, 5, 6.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(116, '07', '700020', 'e10adc3949ba59abbe56e057f20f883e', 'Atalia Tamo Ina Bulu, M.Farm., Apt', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2019-07-22', NULL, NULL, 0, 5, 10, 5.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(117, '07', '700021', 'e10adc3949ba59abbe56e057f20f883e', 'Vonny Febriani', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2019-10-04', NULL, NULL, 0, 5, 8, 5.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(118, '07', '700022', 'e10adc3949ba59abbe56e057f20f883e', 'Margareta Retno Priamsari, S.Si., M.Sc., Apt', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2020-09-01', NULL, NULL, 0, 4, 9, 4.75, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(119, '07', '700023', 'e10adc3949ba59abbe56e057f20f883e', 'Karol Giovani Battista Leki, M.Farm., Apt', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2020-09-23', NULL, NULL, 0, 4, 8, 4.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(120, '07', '700024', 'e10adc3949ba59abbe56e057f20f883e', 'Nanda Dwi Akbar, S.Farm., M.Pharm., Sci', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2022-06-21', NULL, NULL, 0, 2, 11, 2.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(121, '07', '700025', 'e10adc3949ba59abbe56e057f20f883e', 'Modestus Ratu, S.Farm', 'STIFERA', NULL, NULL, 'Guru', 'Tetap', '2024-11-18', NULL, NULL, 0, 0, 6, 0.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(122, '08', '800001', 'e10adc3949ba59abbe56e057f20f883e', 'Andi Darmawan, A.Md.Kom', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2010-06-01', NULL, NULL, 0, 15, 0, 15.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(123, '08', '800002', 'e10adc3949ba59abbe56e057f20f883e', 'Anggoro Kristiawan', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2008-06-25', NULL, NULL, 0, 16, 11, 16.92, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(124, '08', '800003', 'e10adc3949ba59abbe56e057f20f883e', 'Atik Mulyaningtyas, SE', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2010-02-08', NULL, NULL, 0, 15, 4, 15.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(125, '08', '800004', 'e10adc3949ba59abbe56e057f20f883e', 'Beni Kristanto', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2004-01-12', NULL, NULL, 0, 21, 5, 21.42, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(126, '08', '800005', 'e10adc3949ba59abbe56e057f20f883e', 'Sri Haryanti', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '1994-10-01', NULL, NULL, 0, 30, 8, 30.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(127, '08', '800006', 'e10adc3949ba59abbe56e057f20f883e', 'Sukiran', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '1996-09-19', NULL, NULL, 0, 28, 8, 28.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(128, '08', '800007', 'e10adc3949ba59abbe56e057f20f883e', 'Sumaryono', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2002-02-01', NULL, NULL, 0, 23, 4, 23.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(129, '08', '800008', 'e10adc3949ba59abbe56e057f20f883e', 'Suratman', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2006-04-11', NULL, NULL, 0, 19, 2, 19.17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(130, '08', '800009', 'e10adc3949ba59abbe56e057f20f883e', 'Yustina Retno Tirakati, A.Md', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2012-11-06', NULL, NULL, 0, 12, 7, 12.58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(131, '08', '800010', 'e10adc3949ba59abbe56e057f20f883e', 'Wahyu Saputro', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2011-10-01', NULL, NULL, 0, 13, 8, 13.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(132, '08', '800011', 'e10adc3949ba59abbe56e057f20f883e', 'Sutrisno', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2015-02-16', NULL, NULL, 0, 10, 4, 10.33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(133, '08', '800012', 'e10adc3949ba59abbe56e057f20f883e', 'Fuji Fitriani, SE., MM', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2017-05-02', NULL, NULL, 0, 8, 1, 8.08, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(134, '08', '800013', 'e10adc3949ba59abbe56e057f20f883e', 'Quintus Dawampi Bajo', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', NULL, NULL, NULL, 0, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(135, '08', '800014', 'e10adc3949ba59abbe56e057f20f883e', 'Kristian Ika Setiawan, S.Kom', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2004-05-01', NULL, NULL, 0, 21, 1, 21.08, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(136, '08', '800015', 'e10adc3949ba59abbe56e057f20f883e', 'Pradipta Avin, S.Kom', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2020-03-02', NULL, NULL, 0, 5, 3, 5.25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(137, '08', '800016', 'e10adc3949ba59abbe56e057f20f883e', 'Suryo Supeno', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2019-10-16', NULL, NULL, 0, 5, 8, 5.67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(138, '08', '800017', 'e10adc3949ba59abbe56e057f20f883e', 'Taufiqrohman Mandra A., A.Md', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2017-03-20', NULL, NULL, 0, 8, 2, 8.17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(139, '08', '800018', 'e10adc3949ba59abbe56e057f20f883e', 'Jelita Septa Anggraeni', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2023-06-09', NULL, NULL, 0, 2, 0, 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00);
INSERT INTO `anggota_sekolah` (`id`, `uid`, `nip`, `password`, `nama`, `jenjang`, `unit_penempatan`, `strata`, `job_title`, `status_kerja`, `join_start`, `lama_kontrak`, `tgl_kontrak_selesai`, `sudah_kontrak`, `masa_kerja_tahun`, `masa_kerja_bulan`, `masa_kerja_efektif`, `remark`, `jenis_kelamin`, `tanggal_lahir`, `usia`, `agama`, `alamat_domisili`, `alamat_ktp`, `no_rekening`, `no_hp`, `pendidikan`, `status_perkawinan`, `email`, `nama_pasangan`, `jumlah_anak`, `nama_anak_1`, `nama_anak_2`, `nama_anak_3`, `salary_index_id`, `salary_index_level`, `gaji_pokok`, `foto_profil`, `foto_ktp`, `role`, `is_delete`, `deleted_at`, `kategori`, `faskes_bpjs`, `faskes_inhealth`, `faskes_ket`, `gaji_strata`, `total_increment`) VALUES
(140, '08', '800019', 'e10adc3949ba59abbe56e057f20f883e', 'Michael Ignatius Soebahagia Dharma Oetama', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2023-11-13', NULL, NULL, 0, 1, 7, 1.58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00),
(141, '08', '800020', 'e10adc3949ba59abbe56e057f20f883e', 'Ignatius Harris C., S.Pd', 'UMUM', 'TK', NULL, 'Guru', 'Tetap', '2023-08-10', NULL, NULL, 0, 1, 10, 1.83, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', '', '', NULL, NULL, 0.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, NULL, 0, 0, NULL, 0.00, 0.00);

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
-- Table structure for table `backup_dismiss`
--

CREATE TABLE `backup_dismiss` (
  `user_id` int NOT NULL,
  `yyyymm` char(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `jenjang` varchar(20) NOT NULL,
  `strata` varchar(10) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gaji_pokok_strata_guru`
--

INSERT INTO `gaji_pokok_strata_guru` (`jenjang`, `strata`, `gaji_pokok`) VALUES
('SD', 'D3', 3500000.00),
('SD', 'S1', 4500000.00),
('SD', 'S2', 5000000.00),
('SD', 'S3', 5500000.00),
('SMA', 'D3', 4500000.00),
('SMA', 'S1', 5500000.00),
('SMA', 'S2', 6000000.00),
('SMA', 'S3', 7000000.00),
('SMK Nusput 1', 'D3', 4500000.00),
('SMK Nusput 1', 'S1', 5500000.00),
('SMK Nusput 1', 'S2', 6000000.00),
('SMK Nusput 1', 'S3', 7000000.00),
('SMK Nusput 2', 'D3', 4500000.00),
('SMK Nusput 2', 'S1', 5500000.00),
('SMK Nusput 2', 'S2', 6000000.00),
('SMK Nusput 2', 'S3', 7000000.00),
('SMP', 'D3', 4000000.00),
('SMP', 'S1', 5000000.00),
('SMP', 'S2', 5500000.00),
('SMP', 'S3', 6000000.00),
('STIFERA', 'D3', 6000000.00),
('STIFERA', 'S1', 7000000.00),
('STIFERA', 'S2', 8000000.00),
('STIFERA', 'S3', 9000000.00),
('TK', 'D3', 2500000.00),
('TK', 'S1', 4000000.00),
('TK', 'S2', 4500000.00),
('TK', 'S3', 5000000.00),
('UMUM', 'D3', 3000000.00),
('UMUM', 'S1', 3500000.00),
('UMUM', 'S2', 4000000.00),
('UMUM', 'S3', 4500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `gaji_pokok_strata_karyawan`
--

CREATE TABLE `gaji_pokok_strata_karyawan` (
  `jenjang` varchar(20) NOT NULL,
  `strata` varchar(10) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gaji_pokok_strata_karyawan`
--

INSERT INTO `gaji_pokok_strata_karyawan` (`jenjang`, `strata`, `gaji_pokok`) VALUES
('SD', 'D3', 3300000.00),
('SD', 'S1', 3700000.00),
('SD', 'S2', 4000000.00),
('SD', 'S3', 4300000.00),
('SMA', 'D3', 4000000.00),
('SMA', 'S1', 4400000.00),
('SMA', 'S2', 5000000.00),
('SMA', 'S3', 5500000.00),
('SMK Nusput 1', 'D3', 4000000.00),
('SMK Nusput 1', 'S1', 4400000.00),
('SMK Nusput 1', 'S2', 5000000.00),
('SMK Nusput 1', 'S3', 5500000.00),
('SMK Nusput 2', 'D3', 4000000.00),
('SMK Nusput 2', 'S1', 4400000.00),
('SMK Nusput 2', 'S2', 5000000.00),
('SMK Nusput 2', 'S3', 5500000.00),
('SMP', 'D3', 3800000.00),
('SMP', 'S1', 4200000.00),
('SMP', 'S2', 4700000.00),
('SMP', 'S3', 5000000.00),
('STIFERA', 'D3', 5000000.00),
('STIFERA', 'S1', 6000000.00),
('STIFERA', 'S2', 6500000.00),
('STIFERA', 'S3', 7000000.00),
('TK', 'D3', 3000000.00),
('TK', 'S1', 3500000.00),
('TK', 'S2', 3800000.00),
('TK', 'S3', 4000000.00),
('UMUM', 'D3', 2800000.00),
('UMUM', 'S1', 3200000.00),
('UMUM', 'S2', 3600000.00),
('UMUM', 'S3', 4000000.00);

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

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_piket`
--

CREATE TABLE `jadwal_piket` (
  `id_jadwal` int NOT NULL,
  `nip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_guru` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenjang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waktu_piket` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tahun` int NOT NULL,
  `status` enum('pending','hadir','tidak hadir') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jenjang_sekolah`
--

CREATE TABLE `jenjang_sekolah` (
  `id` int NOT NULL,
  `kode_jenjang` varchar(50) NOT NULL,
  `nama_jenjang` varchar(100) NOT NULL,
  `deskripsi` text,
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `color_bg` varchar(16) DEFAULT '#6c757d',
  `color_fg` varchar(16) DEFAULT '#ffffff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jenjang_sekolah`
--

INSERT INTO `jenjang_sekolah` (`id`, `kode_jenjang`, `nama_jenjang`, `deskripsi`, `is_aktif`, `color_bg`, `color_fg`) VALUES
(1, 'TK', 'TK', 'Jenjang prasekolah', 1, '#f8bbd0', '#212529'),
(2, 'SD', 'SD', 'Jenjang pendidikan dasar', 1, '#fff59d', '#212529'),
(3, 'SMP', 'SMP', 'Jenjang pendidikan menengah pertama', 1, '#80deea', '#212529'),
(4, 'SMA', 'SMA', 'Jenjang pendidikan menengah atas', 1, '#aed581', '#212529'),
(5, 'SMK1', 'SMK Nusput 1', 'SMK Nusputera 1', 1, '#ffd180', '#212529'),
(6, 'SMK2', 'SMK Nusput 2', 'SMK Nusputera 2', 1, '#ce93d8', '#212529'),
(7, 'STIFERA', 'STIFERA', 'Sekolah Tinggi Ilmu Farmasi Nusaputera', 1, '#b3e5fc', '#212529'),
(8, 'UMUM', 'Umum', 'Pegawai non-jenjang', 1, '#41e678', '#000000'),
(10, 'MANAJER', 'Manajerial', 'Manajer Sekolah', 1, '#b44ef9', '#000000');

-- --------------------------------------------------------

--
-- Table structure for table `kelebihan_jam_mengajar`
--

CREATE TABLE `kelebihan_jam_mengajar` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL COMMENT 'FK ke anggota_sekolah.id',
  `bulan` tinyint NOT NULL COMMENT '1-12',
  `tahun` smallint NOT NULL COMMENT 'Misal 2025',
  `minggu_ke` tinyint NOT NULL COMMENT 'Pilihan: 4 atau 5 minggu',
  `jam_extra` decimal(8,2) NOT NULL COMMENT 'Total jam ekstra',
  `total_honor` decimal(10,2) DEFAULT '0.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu terakhir edit',
  `is_final` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `kenaikan_gaji_tahunan`
--

CREATE TABLE `kenaikan_gaji_tahunan` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `nama_kenaikan` varchar(255) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tanggal_mulai` date NOT NULL,
  `tanggal_berakhir` date NOT NULL,
  `status` enum('aktif','selesai') NOT NULL DEFAULT 'aktif',
  `ranking_id` int DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pindah_ke_lain_lain` tinyint(1) NOT NULL DEFAULT '0'
) ;


--
-- Table structure for table `laporan_surat`
--

CREATE TABLE `laporan_surat` (
  `id` int NOT NULL,
  `id_pengirim` int NOT NULL,
  `id_penerima` int NOT NULL,
  `is_read_receiver` tinyint(1) NOT NULL DEFAULT '0',
  `jenis_surat` enum('peringatan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'peringatan',
  `judul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `isi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_keluar` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('terkirim','dibaca') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'terkirim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `msg_read`
--

CREATE TABLE `msg_read` (
  `user_id` int NOT NULL,
  `msg_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `notification_type` enum('info','success','warning','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'info',
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `priority` int DEFAULT '5' COMMENT 'Nilai prioritas; semakin kecil nilainya semakin tinggi prioritasnya',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `is_once` tinyint(1) NOT NULL DEFAULT '0',
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
(1, 'Tunjangan Struktural', 'earnings', 'Tunjangan untuk jabatan struktural', 500000.00),
(2, 'Tunjangan Wali Kelas', 'earnings', 'Tunjangan tugas sebagai wali kelas', 200000.00),
(3, 'Tunjangan Strata', 'earnings', 'Tunjangan berdasarkan strata pendidikan', 250000.00),
(4, 'Tunjangan Profesi', 'earnings', 'Tunjangan profesi guru sesuai sertifikasi', 300000.00),
(5, 'Tunjangan Pindah Jenjang', 'earnings', 'Tunjangan untuk perpindahan jenjang pendidikan', 150000.00),
(6, 'Reward', 'earnings', 'Bonus reward kinerja atau capaian khusus', 150000.00),
(7, 'Honor Pelajaran Tambahan', 'earnings', 'Honor untuk pelajaran tambahan di luar kurikulum', 75000.00),
(8, 'Resiko', 'earnings', 'Tunjangan resiko (misal laboratorium, lapangan)', 300000.00),
(9, 'Uang Makan', 'earnings', 'Tunjangan uang makan per bulan', 364000.00),
(10, 'Lembur', 'earnings', 'Honor lembur per jam kerja lembur', 100000.00),
(11, 'BPJS Ketenagakerjaan', 'deductions', 'Potongan iuran BPJS Ketenagakerjaan', 250000.00),
(12, 'Potongan Lain-lain', 'deductions', 'Potongan lain-lain sesuai kebijakan', 0.00),
(100, 'Kenaikan Gaji Tahunan', 'earnings', 'Kenaikan gaji berdasarkan evaluasi tahunan', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `payhead_groups`
--

CREATE TABLE `payhead_groups` (
  `id` int NOT NULL,
  `group_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `payhead_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenis` enum('earnings','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('guru','karyawan') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'guru',
  `sort_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payhead_groups`
--

INSERT INTO `payhead_groups` (`id`, `group_name`, `payhead_name`, `jenis`, `role`, `sort_order`) VALUES
(5, 'Wali Kelas /Strata/Profesi/ Pindah Jenjang', 'Tunjangan Pindah Jenjang', 'earnings', 'guru', 1),
(6, 'Wali Kelas /Strata/Profesi/ Pindah Jenjang', 'Tunjangan Profesi', 'earnings', 'guru', 1),
(7, 'Wali Kelas /Strata/Profesi/ Pindah Jenjang', 'Tunjangan Strata', 'earnings', 'guru', 1),
(8, 'Wali Kelas /Strata/Profesi/ Pindah Jenjang', 'Tunjangan Wali Kelas', 'earnings', 'guru', 1);

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
  `salary_index_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_pendapatan` decimal(15,2) DEFAULT NULL,
  `total_potongan` decimal(15,2) DEFAULT NULL,
  `potongan_koperasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `potongan_absensi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `honor_jam_lebih` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tgl_payroll` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `no_rekening` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` enum('draft','revisi','final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `payroll_detail`
--

CREATE TABLE `payroll_detail` (
  `id` int NOT NULL,
  `id_payroll` int NOT NULL,
  `id_anggota` int NOT NULL,
  `id_payhead` int NOT NULL,
  `ranking_id` int DEFAULT NULL,
  `jenis` enum('earnings','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `ranking_nominal` decimal(15,2) DEFAULT NULL,
  `status` enum('draft','revisi','final') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `payroll_final`
--

CREATE TABLE `payroll_final` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `id_rekap_absensi` int DEFAULT NULL,
  `bulan` int NOT NULL,
  `tahun` int NOT NULL,
  `gaji_pokok` decimal(15,2) DEFAULT NULL,
  `salary_index_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `honor_jam_lebih` double DEFAULT '0',
  `total_pendapatan` decimal(15,2) DEFAULT NULL,
  `total_potongan` decimal(15,2) DEFAULT NULL,
  `potongan_koperasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `potongan_absensi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gaji_bersih` decimal(15,2) DEFAULT NULL,
  `tgl_payroll` datetime NOT NULL,
  `no_rekening` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `finalized_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_payroll_asal` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `tanggal_piket` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `potongan_ketidakhadiran`
--

CREATE TABLE `potongan_ketidakhadiran` (
  `id` int NOT NULL,
  `tahun` int NOT NULL,
  `role` enum('P','TK','M') NOT NULL,
  `biaya_per_hari` decimal(15,2) NOT NULL,
  `max_hari` int DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `potongan_ketidakhadiran`
--

INSERT INTO `potongan_ketidakhadiran` (`id`, `tahun`, `role`, `biaya_per_hari`, `max_hari`, `keterangan`) VALUES
(1, 2025, 'P', 75000.00, 2, 'Potongan tidak hadir Guru/Karyawan'),
(2, 2025, 'TK', 75000.00, 2, 'Potongan tidak hadir Tenaga Kependidikan'),
(3, 2025, 'M', 50000.00, NULL, 'Potongan tidak hadir Manajerial');

-- --------------------------------------------------------

--
-- Table structure for table `ranking_kenaikan`
--

CREATE TABLE `ranking_kenaikan` (
  `id` int NOT NULL,
  `nama_ranking` varchar(100) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `deskripsi` text,
  `is_aktif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ranking_kenaikan`
--

INSERT INTO `ranking_kenaikan` (`id`, `nama_ranking`, `jumlah`, `deskripsi`, `is_aktif`) VALUES
(1, 'A', 1000000.00, 'Top Performance – kenaikan 1.000.000', 1),
(2, 'B', 750000.00, 'Excellent Performance – kenaikan 750.000', 1),
(3, 'C', 500000.00, 'Good Performance – kenaikan 500.000', 1),
(4, 'D', 250000.00, 'Satisfactory – kenaikan 250.000', 1),
(5, 'E', 100000.00, 'Needs Improvement – kenaikan 100.000', 1);

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

-- --------------------------------------------------------

--
-- Table structure for table `salary_history`
--

CREATE TABLE `salary_history` (
  `id` int NOT NULL,
  `id_anggota` int NOT NULL,
  `jenis` enum('increment') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `effective_date` date NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Table structure for table `tarif_honor_jam_lebih`
--

CREATE TABLE `tarif_honor_jam_lebih` (
  `id` int NOT NULL,
  `nominal` decimal(12,2) NOT NULL COMMENT 'Tarif per jam ekstra (Rp)',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu terakhir diupdate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tarif_honor_jam_lebih`
--

INSERT INTO `tarif_honor_jam_lebih` (`id`, `nominal`, `updated_at`) VALUES
(1, 12500.00, '2025-06-25 14:13:52');

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
(1, 'Ulang Tahun', 'Ulang Tahun', 'Selamat Ulang Tahun kepada Ibu/Bapak, semoga mimpi-mimpi di tahun ini tercapai dan terealisasikan semua.', 'perorangan', 14, '2025-03-11 10:05:11', '2025-06-19 10:33:57', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_nip_tanggal` (`nip`,`tanggal`),
  ADD KEY `fk_absensi_anggota` (`id_anggota`),
  ADD KEY `idx_absensi_nip_tgl_late` (`nip`,`tanggal`,`terlambat`);

--
-- Indexes for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nip` (`nip`),
  ADD KEY `salary_index_id` (`salary_index_id`),
  ADD KEY `idx_kontrak_expiry` (`status_kerja`,`tgl_kontrak_selesai`),
  ADD KEY `idx_kontrak_status_tgl` (`status_kerja`,`tgl_kontrak_selesai`),
  ADD KEY `idx_unit_penempatan` (`unit_penempatan`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`nip`),
  ADD KEY `idx_audit_created_action` (`created_at`,`action`);

--
-- Indexes for table `backup_dismiss`
--
ALTER TABLE `backup_dismiss`
  ADD PRIMARY KEY (`user_id`,`yyyymm`);

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
  ADD KEY `fk_jadwal_piket_anggota` (`nip`),
  ADD KEY `idx_piket_nip_tanggal` (`nip`,`tanggal`),
  ADD KEY `idx_jenjang_tanggal` (`jenjang`,`tanggal`);

--
-- Indexes for table `jenjang_sekolah`
--
ALTER TABLE `jenjang_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_jenjang` (`kode_jenjang`);

--
-- Indexes for table `kelebihan_jam_mengajar`
--
ALTER TABLE `kelebihan_jam_mengajar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_period` (`id_anggota`,`bulan`,`tahun`);

--
-- Indexes for table `kenaikan_gaji_tahunan`
--
ALTER TABLE `kenaikan_gaji_tahunan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_kgt` (`id_anggota`,`tanggal_mulai`),
  ADD KEY `ranking_id` (`ranking_id`);

--
-- Indexes for table `laporan_surat`
--
ALTER TABLE `laporan_surat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_laporan_read` (`id_penerima`,`is_read_receiver`);

--
-- Indexes for table `msg_read`
--
ALTER TABLE `msg_read`
  ADD PRIMARY KEY (`user_id`,`msg_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_role_read` (`role_target`,`is_read`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `payheads`
--
ALTER TABLE `payheads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payhead_groups`
--
ALTER TABLE `payhead_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_group_payhead` (`group_name`,`payhead_name`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `id_rekap_absensi` (`id_rekap_absensi`),
  ADD KEY `idx_payroll_bulantahun_stat` (`bulan`,`tahun`,`status`),
  ADD KEY `idx_payroll_honor` (`bulan`,`tahun`,`honor_jam_lebih`);

--
-- Indexes for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payroll` (`id_payroll`),
  ADD KEY `idx_payhead` (`id_payhead`),
  ADD KEY `idx_ranking` (`ranking_id`);

--
-- Indexes for table `payroll_detail_final`
--
ALTER TABLE `payroll_detail_final`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_detail_once` (`id_payroll_final`,`id_payhead`),
  ADD KEY `idx_payroll_final` (`id_payroll_final`),
  ADD KEY `idx_payhead` (`id_payhead`);

--
-- Indexes for table `payroll_final`
--
ALTER TABLE `payroll_final`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_payrollfinal_once` (`id_payroll_asal`),
  ADD KEY `id_anggota` (`id_anggota`),
  ADD KEY `bulan` (`bulan`,`tahun`),
  ADD KEY `idx_payrollfinal_anggota_blnthn` (`id_anggota`,`bulan`,`tahun`),
  ADD KEY `idx_pf_anggota_blnthn` (`id_anggota`,`bulan`,`tahun`);

--
-- Indexes for table `pengajuan_ijin`
--
ALTER TABLE `pengajuan_ijin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ijin_kepsek` (`status_kepalasekolah`,`status`),
  ADD KEY `idx_ijin_nip_stat` (`nip`,`status`);

--
-- Indexes for table `permintaan_tukar_jadwal`
--
ALTER TABLE `permintaan_tukar_jadwal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_once` (`id_jadwal_pengaju`,`id_jadwal_tujuan`,`nip_tujuan`,`status`),
  ADD KEY `id_jadwal_tujuan` (`id_jadwal_tujuan`);

--
-- Indexes for table `potongan_ketidakhadiran`
--
ALTER TABLE `potongan_ketidakhadiran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ranking_kenaikan`
--
ALTER TABLE `ranking_kenaikan`
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
-- Indexes for table `salary_history`
--
ALTER TABLE `salary_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_date` (`id_anggota`,`effective_date`);

--
-- Indexes for table `salary_indices`
--
ALTER TABLE `salary_indices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level` (`level`);

--
-- Indexes for table `tarif_honor_jam_lebih`
--
ALTER TABLE `tarif_honor_jam_lebih`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT for table `employee_payheads`
--
ALTER TABLE `employee_payheads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jadwal_piket`
--
ALTER TABLE `jadwal_piket`
  MODIFY `id_jadwal` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=518;

--
-- AUTO_INCREMENT for table `jenjang_sekolah`
--
ALTER TABLE `jenjang_sekolah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `kelebihan_jam_mengajar`
--
ALTER TABLE `kelebihan_jam_mengajar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=316;

--
-- AUTO_INCREMENT for table `kenaikan_gaji_tahunan`
--
ALTER TABLE `kenaikan_gaji_tahunan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laporan_surat`
--
ALTER TABLE `laporan_surat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payheads`
--
ALTER TABLE `payheads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `payhead_groups`
--
ALTER TABLE `payhead_groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=262;

--
-- AUTO_INCREMENT for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=291;

--
-- AUTO_INCREMENT for table `payroll_detail_final`
--
ALTER TABLE `payroll_detail_final`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `payroll_final`
--
ALTER TABLE `payroll_final`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `pengajuan_ijin`
--
ALTER TABLE `pengajuan_ijin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `permintaan_tukar_jadwal`
--
ALTER TABLE `permintaan_tukar_jadwal`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `potongan_ketidakhadiran`
--
ALTER TABLE `potongan_ketidakhadiran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ranking_kenaikan`
--
ALTER TABLE `ranking_kenaikan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rekap_absensi`
--
ALTER TABLE `rekap_absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `rekap_mingguan`
--
ALTER TABLE `rekap_mingguan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `salary_history`
--
ALTER TABLE `salary_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `template_surat`
--
ALTER TABLE `template_surat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `anggota_sekolah`
--
ALTER TABLE `anggota_sekolah`
  ADD CONSTRAINT `fk_anggota_salary_indices` FOREIGN KEY (`salary_index_id`) REFERENCES `salary_indices` (`id`);

--
-- Constraints for table `employee_payheads`
--
ALTER TABLE `employee_payheads`
  ADD CONSTRAINT `fk_employee_payheads_payheads` FOREIGN KEY (`id_payhead`) REFERENCES `payheads` (`id`);

--
-- Constraints for table `jadwal_piket`
--
ALTER TABLE `jadwal_piket`
  ADD CONSTRAINT `fk_jadwal_piket_anggota` FOREIGN KEY (`nip`) REFERENCES `anggota_sekolah` (`nip`) ON DELETE CASCADE;

--
-- Constraints for table `kenaikan_gaji_tahunan`
--
ALTER TABLE `kenaikan_gaji_tahunan`
  ADD CONSTRAINT `kenaikan_gaji_tahunan_ibfk_1` FOREIGN KEY (`ranking_id`) REFERENCES `ranking_kenaikan` (`id`);

--
-- Constraints for table `payroll_detail`
--
ALTER TABLE `payroll_detail`
  ADD CONSTRAINT `fk_payroll_detail_payheads` FOREIGN KEY (`id_payhead`) REFERENCES `payheads` (`id`),
  ADD CONSTRAINT `fk_payroll_detail_payroll` FOREIGN KEY (`id_payroll`) REFERENCES `payroll` (`id`);

--
-- Constraints for table `permintaan_tukar_jadwal`
--
ALTER TABLE `permintaan_tukar_jadwal`
  ADD CONSTRAINT `fk_ptj_jadwal_pengaju` FOREIGN KEY (`id_jadwal_pengaju`) REFERENCES `jadwal_piket` (`id_jadwal`) ON DELETE CASCADE,
  ADD CONSTRAINT `permintaan_tukar_jadwal_ibfk_1` FOREIGN KEY (`id_jadwal_tujuan`) REFERENCES `jadwal_piket` (`id_jadwal`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
