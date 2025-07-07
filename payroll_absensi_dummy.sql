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
(1, 'G-001', '100001', 'e10adc3949ba59abbe56e057f20f883e', 'Ahmad Fauzi', 'SD', NULL, 'S1', 'Guru Matematika', 'Tetap', '2023-01-27', NULL, NULL, 0, 2, 3, 2.00, 'Berpengalaman mengajar matematika', 'L', '1980-01-15', 45, 'Islam', '2A Jl. Empu Sendok Raya', 'Jl. Melati No. 1', '1234567890', '6282227863969', 'S1 Ilmu Komputer', 'Belum Menikah', 'ahmad.fauzi@example.com', '-', 0, '-', '-', '-', 1, 'Level 0', 4500000.00, '0', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(2, 'G-002', '100002', 'e10adc3949ba59abbe56e057f20f883e', 'Siti Rahma', 'SMP', NULL, 'S1', 'Guru Fisika', 'Tetap', '2015-07-01', NULL, NULL, 0, 9, 10, 10.00, 'Menyukai eksperimen fisika', 'P', '1985-05-10', 40, 'Islam', 'Jl. Kenanga No. 2', 'Jl. Kenanga No. 2', '098765', '082182314967', 'S1 Pendidikan', 'Menikah', 'default.jpg', 'Andi Rahma', 1, 'Ayu', '', '', 3, 'Level 2', 5000000.00, '', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(3, 'G-003', '100003', 'e10adc3949ba59abbe56e057f20f883e', 'Budi Santoso', 'SMA', NULL, 'S2', 'Guru Sejarah', 'Tetap', '2010-01-10', NULL, NULL, 0, 15, 4, 15.00, 'Ahli sejarah Indonesia', 'L', '1975-12-25', 50, 'Kristen', 'Jl. Mawar No. 3', 'Jl. Mawar No. 3', '112233', '081345678901', 'S2 Pendidikan', 'Menikah', 'budi.santoso@example.com', '', 3, 'Tono', 'Rina', 'Dewi', 5, 'Level 4', 6000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(4, 'G-004', '100004', 'e10adc3949ba59abbe56e057f20f883e', 'Rina Sari', 'SMK', NULL, 'S1', 'Guru Bahasa', 'Tetap', '2012-03-15', NULL, NULL, 0, 13, 2, 13.00, 'Mengajar dengan metode kreatif', 'P', '1982-07-20', 43, 'Islam', 'Jl. Melati No. 5', 'Jl. Melati No. 5', '445566', '081234000111', 'S1 Sastra', 'Menikah', 'rina.sari@example.com', 'Agus Sari', 1, 'Dewi', '', '', 4, 'Level 3', 5000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(5, 'G-005', '01011995', 'e10adc3949ba59abbe56e057f20f883e', 'Roosalin Chintia Dewi', 'TK', NULL, 'S1', 'Wali Kelas TK', 'Tetap', '2016-08-01', NULL, NULL, 0, 8, 9, 8.00, 'Wali kelas yang disiplin', 'L', '1983-11-30', 41, 'Islam', 'Jl. Pelita No. 3', 'Jl. Pelita No. 3', '667788', '081234112233', 'S1 Pendidikan', 'Menikah', 'dedi.prasetyo@example.com', '', 3, 'Sari', 'Agus', '', 3, 'Level 2', 4000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(6, 'G-006', '100006', 'e10adc3949ba59abbe56e057f20f883e', 'Maya Putri', 'SMP', NULL, 'S1', 'Wali Kelas 2A', 'Tetap', '2018-01-15', NULL, NULL, 0, 7, 4, 7.00, 'Wali kelas kreatif', 'P', '1990-04-10', 35, 'Islam', 'Jl. Merdeka No. 4', 'Jl. Merdeka No. 4', '223344', '081234223344', 'S1 Pendidikan', 'Menikah', 'maya.putri@example.com', 'Budi Putri', 1, 'Dewi', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(7, 'G-007', '100007', 'e10adc3949ba59abbe56e057f20f883e', 'Fitriani', 'SMA', NULL, 'S1', 'Wali Kelas 4 SMP Kelas 1', 'Tetap', '2014-05-01', NULL, NULL, 0, 11, 0, 11.00, 'Wali kelas yang teliti', 'P', '1987-09-15', 38, 'Islam', 'Jl. Sejahtera No. 7', 'Jl. Sejahtera No. 7', '334455', '081234334455', 'S1 Pendidikan', 'Menikah', 'fitriani@example.com', '', 2, 'Agus', 'Siti', '', 4, 'Level 3', 5500000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(8, 'K-001', '200001', 'e10adc3949ba59abbe56e057f20f883e', 'Dewi Lestari', 'SMA', NULL, 'S1', 'Tenaga Kependidikan Administrasi', 'Kontrak', '2025-01-01', NULL, '2025-06-10', 0, 0, 4, 0.00, 'Staff administrasi yang efisien', 'P', '1993-08-15', 32, 'Islam', 'Jl. Pertiwi No. 4', 'Jl. Pertiwi No. 4', '556677', '081234556677', 'S1 Administrasi', 'Belum Menikah', 'dewi.lestari@example.com', '', 0, '', '', '', 1, 'Level 0', 4400000.00, 'default.jpg', 'default_ktp.jpg', 'TK', 0, NULL, 'karyawan', 0, 0, NULL, 0.00, 0.00),
(9, 'K-002', '200002', 'e10adc3949ba59abbe56e057f20f883e', 'Slamet Wijaya', 'SMK', NULL, 'S1', 'Tenaga Kependidikan Operasional', 'Tetap', '2018-06-15', NULL, NULL, 0, 6, 11, 7.00, 'Bertugas di operasional', 'L', '1988-03-05', 37, 'Islam', 'Jl. Industri No. 7', 'Jl. Industri No. 7', '778899', '081298778899', 'S1 Manajemen', 'Menikah', 'slamet.wijaya@example.com', 'Siti Wijaya', 1, 'Dewi', '', '', 3, 'Level 2', 4000000.00, 'default.jpg', 'default_ktp.jpg', 'TK', 0, NULL, 'karyawan', 0, 0, NULL, 0.00, 0.00),
(10, 'K-003', '200003', 'e10adc3949ba59abbe56e057f20f883e', 'Rizki Pratama', 'SMP', NULL, NULL, 'Tenaga Kependidikan Umum', 'Kontrak', '2022-01-01', NULL, '2023-01-01', 0, 3, 4, 3.00, 'Staff pendukung operasional', 'L', '1998-11-12', 27, 'Islam', 'Jl. Sudirman No. 8', 'Jl. Sudirman No. 8', '889900', '081237889900', '', 'Belum Menikah', 'rizki.pratama@example.com', '', 0, '', '', '', 2, 'Level 1', 4000000.00, 'default.jpg', 'default_ktp.jpg', 'TK', 0, NULL, 'karyawan', 0, 0, NULL, 0.00, 0.00),
(11, 'M-001', '300001', 'e10adc3949ba59abbe56e057f20f883e', 'Andini Permata', 'SMA', NULL, 'S2', 'Kepala Sekolah SMA', 'Tetap', '2014-01-27', NULL, NULL, 0, 11, 3, 11.00, 'Memimpin sekolah dengan visi', 'P', '1978-04-22', 47, 'Islam', 'Jl. Merdeka No. 10', 'Jl. Merdeka No. 10', '990011', '081290990011', 'S2 Kesenian', 'Menikah', 'andini.permata@example.com', 'Budi Permata', 2, 'Tina', 'Rina', '', 4, 'Level 3', 6000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(12, 'M-002', '300002', 'e10adc3949ba59abbe56e057f20f883e', 'Sie, Vincent Peter S.', 'SMA', NULL, 'S2', 'Keuangan', 'Tetap', '2008-07-01', NULL, NULL, 0, 16, 10, 16.83, 'Mengelola keuangan dengan transparansi', 'L', '1965-06-21', 60, 'Islam', 'Jl. Pendidikan No. 9', 'Jl. Pendidikan No. 9', '112233', '081298112233', 'S2 Teknologi Informasi', 'Menikah', 'joko.widodo@example.com', 'Iriana Widodo', 3, 'Gibran', 'Khalifah', 'Puan', 5, 'Level 4', 7000000.00, 'default.jpg', 'default_ktp.jpg', 'M', 0, NULL, 'karyawan', 0, 0, NULL, 0.00, 0.00),
(13, 'M-003', '300003', 'e10adc3949ba59abbe56e057f20f883e', 'Sari Utami', 'SMA', '', 'S1', 'SDM', 'Tetap', '2012-11-11', NULL, NULL, 0, 12, 7, 12.00, 'Mengelola SDM dengan profesionalisme', 'P', '1982-02-28', 43, 'Kristen', 'Jl. Simpang Lima No. 5', 'Jl. Simpang Lima No. 56', '445577', '6281298445577', 'S1 Akuntansi', 'Belum Kawin', 'sari.utami@example.com', '-', 0, '-', '-', '-', 4, 'Level 3', 4400000.00, 'http://localhost/payroll_absensi_v2/uploads/profile_pics/sari_utami_sma_m_13.jpg', 'http://localhost/payroll_absensi_v2/uploads/ktp_pics/sari_utami_sma_m_13_ktp.jpg', 'M', 0, NULL, 'guru', 0, 0, '', 0.00, 0.00),
(14, 'M-004', '300004', 'e10adc3949ba59abbe56e057f20f883e', 'Rudi Hartono', 'SMA', NULL, 'D3', 'Superadmin', 'Tetap', '2010-01-01', NULL, NULL, 0, 15, 4, 15.33, 'Administrator sistem IT sekolah', 'L', '1970-12-12', 54, 'Islam', '2A Jl. Empu Sendok Raya', '', '', '', 'D3 Akuntansi', 'Menikah', 'rudi.hartono@example.com', '', 0, '', '', '', 5, 'Level 4', 7000000.00, 'http://localhost/payroll_absensi_v2/uploads/profile_pics/rudi_hartono_sma_m_14.jpg', 'default_ktp.jpg', 'M', 0, NULL, 'karyawan', 0, 0, NULL, 0.00, 0.00),
(16, 'AF292EA2', '100010', 'e10adc3949ba59abbe56e057f20f883e', 'Hizkia Fareza', 'TK', NULL, 'D3', 'Guru Membaca', 'Tetap', '2025-03-24', NULL, NULL, 0, 0, 1, 0.00, 'Mengajar membaca anak TK', 'L', '2025-03-24', 23, 'Katolik', '2A Jl. Empu Sendok Raya', '2A Jl. Empu Sendok Raya', '144345343', '082227863969', 'D3 Akuntansi', 'Belum Menikah', 'hizkia@gmail.com', '-', 0, '-', '-', '-', 1, 'Level 0', 2500000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(17, 'CC95288B', '100011', 'e10adc3949ba59abbe56e057f20f883e', 'Hendra Kurniawan', 'TK', NULL, 'D3', 'Guru Balok', 'Tetap', '2025-03-24', NULL, NULL, 0, 0, 1, 0.00, 'Mengajar kreativitas anak', 'L', '2001-05-06', 23, 'Katolik', 'Jalan Tuah', 'Jalan Tuah', '143453453', '082226544333', 'D3 Teknologi Informasi', 'Belum Menikah', 'hendra@gmail.com', '-', 0, '-', '-', '-', 1, 'Level 0', 2500000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(18, 'ABB41A60', '200010', 'e10adc3949ba59abbe56e057f20f883e', 'Apin Upin', 'SD', NULL, NULL, 'Teknisi Kontrol Sistem', 'Tetap', '2025-03-24', NULL, NULL, 0, 0, 1, 0.00, 'Mengatasi Error Sistem', 'L', '1990-01-24', 30, 'Hindu', 'Jalan Kedung', 'Jalan Kedung', '1454654564', '081234567890', '', 'Belum Menikah', '', '-', 0, '-', '-', '-', 1, 'Level 0', 4000000.00, 'default.jpg', 'default_ktp.jpg', 'TK', 1, '2025-03-25 21:44:45', 'karyawan', 0, 0, NULL, 0.00, 0.00),
(19, '339AAE5F', '100012', 'e10adc3949ba59abbe56e057f20f883e', 'Catherine Wong S', 'SMA', NULL, 'S1', 'Guru Sejarah', 'Tetap', '2025-03-25', NULL, NULL, 0, 0, 1, 0.00, 'Mengajar Sejarah Indonesia', NULL, NULL, 19, '', 'Klipang Raya', 'Klipang Raya', '512443563', '08182344848', 'S1 Sejarah', 'Belum Menikah', 'cathiew@gmail.com', '-', 0, '-', '-', '-', 1, 'Level 0', 5500000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(20, 'M-005', '300005', 'e10adc3949ba59abbe56e057f20f883e', 'Diana Puspitasari', 'TK', NULL, 'S2', 'Kepala Sekolah TK', 'Tetap', '2015-03-01', NULL, NULL, 0, 10, 2, 10.00, 'Spesialis pendidikan anak usia dini', NULL, NULL, 46, '', 'Jl. Anggrek No. 12', 'Jl. Anggrek No. 12', '112233445', '081112223344', 'S2 Pendidikan Anak', 'Menikah', 'diana.puspita@example.com', 'Bambang Puspito', 2, 'Rara', 'Dimas', '', 3, 'Level 2', 4500000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(21, 'M-006', '300006', 'e10adc3949ba59abbe56e057f20f883e', 'Hendra Kurniawan', 'SD', NULL, 'S2', 'Kepala Sekolah SD', 'Tetap', '2010-06-15', NULL, NULL, 0, 14, 11, 15.00, 'Penggagas program literasi sekolah', 'L', '1975-11-05', 49, 'Kristen', 'Jl. Pendidikan No. 45', 'Jl. Pendidikan No. 45', '5544332211', '081334445566', 'S2 Manajemen Pendidikan', 'Menikah', 'hendra.kurnia@example.com', 'Linda Wijaya', 3, 'Kevin', 'Salsa', 'Rafi', 5, 'Level 4', 5000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(22, 'M-007', '300007', 'e10adc3949ba59abbe56e057f20f883e', 'Sri Wahyuni', 'SMP', NULL, 'S2', 'Kepala Sekolah SMP', 'Tetap', '2013-02-20', NULL, NULL, 0, 12, 3, 12.00, 'Penerapan kurikulum merdeka', 'P', '1980-04-30', 44, 'Islam', 'Jl. Cendrawasih No. 8', 'Jl. Cendrawasih No. 8', '6677889900', '081556677889', 'S2 Pendidikan Matematika', 'Menikah', 'sri.wahyuni@example.com', 'Ahmad Fauzi', 1, 'Budi', '', '', 4, 'Level 3', 5500000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(23, 'M-008', '300008', 'e10adc3949ba59abbe56e057f20f883e', 'Rudi Hermawan', 'SMK Nusput 1', NULL, 'S3', 'Kepala Sekolah SMK 1', 'Tetap', '2009-09-01', NULL, NULL, 0, 15, 8, 15.00, 'Fokus pada link and match industri', 'L', '1972-12-12', 52, 'Katolik', 'Jl. Industri No. 22', 'Jl. Industri No. 22', '9988776655', '081778889900', 'S3 Teknik Mesin', 'Menikah', 'rudi.hermawan@example.com', 'Dewi Anggraeni', 2, 'Dika', 'Nina', '', 5, 'Level 4', 7000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, NULL, 0.00, 0.00),
(24, 'M-009', '300009', 'e10adc3949ba59abbe56e057f20f883e', 'Lina Marlina', 'SMK2', '', 'S2', 'Kepala Sekolah SMK 2', 'Tetap', '2017-04-10', NULL, NULL, 0, 8, 2, 8.00, 'Pengembang teaching factory', 'P', '1985-03-25', 40, 'Islam', 'Jl. Teknologi No. 15', 'Jl. Teknologi No. 15', '1234098765', '6281990001122', 'S2 Elektro', 'Menikah', 'lina.marlina@example.com', 'Eko Prasetyo', 1, 'Luna', '', '', 3, 'Level 2', 5000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, '', 6000000.00, 1400000.00),
(25, 'M-010', '300010', 'e10adc3949ba59abbe56e057f20f883e', 'Prof. Dr. Bambang Sutejo, M.Sc.', 'STIFERA', '', 'S3', 'Kepala Sekolah Universitas Stivera', 'Tetap', '2005-01-01', NULL, NULL, 0, 20, 6, 20.00, 'Rektor berprestasi tingkat nasional', 'L', '1968-07-17', 56, 'Buddha', 'Jl. Kampus No. 1', 'Jl. Kampus No. 1', '1357924680', '6282182314967', 'S3 Manajemen Pendidikan', 'Menikah', 'bambang.sutejo@stivera.ac.id', 'Diana Sutejo', 2, 'Adi', 'Rini', '', 5, 'Level 4', 9000000.00, 'default.jpg', 'default_ktp.jpg', 'P', 0, NULL, 'guru', 0, 0, '', 8000000.00, 1800000.00),
(32, '01', '700008', '$2y$10$rRrk/3U888Zw1xtBaoBm5uNYouj7g78ERnvtm8/knNzxx0vf1OoJa', 'Aaron', 'TK', '', 'S2', 'Guru Teknologi Informasi', 'Kontrak', '2025-06-23', 12, '2026-06-22', 0, 0, 0, 0.00, '', 'L', '1998-06-19', 27, 'Kristen', '0', '', '', '', 'S2 Teknologi Informasi', 'Belum Menikah', '', '-', 0, '-', '-', '-', 1, 'Level 0', 4500000.00, '', '', 'P', 0, NULL, 'guru', 1, 0, '0', 0.00, 0.00);

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
-- Dumping data for table `employee_payheads`
--

INSERT INTO `employee_payheads` (`id`, `id_anggota`, `id_payhead`, `jenis`, `amount`, `status`, `remarks`, `support_doc_path`, `upload_file_blob`, `is_rapel`) VALUES
(43, 25, 11, 'deductions', 250000.00, 'revisi', '', '', NULL, 0),
(44, 20, 100, 'earnings', 1000000.00, 'draft', 'Kenaikan Gaji Tahun 2025/2026', '', NULL, 0),
(45, 20, 7, 'earnings', 75000.00, 'draft', '', '', NULL, 0),
(46, 24, 100, 'earnings', 750000.00, 'draft', 'Kenaikan Gaji Tahun 2025/2026', '', NULL, 0),
(47, 24, 11, 'deductions', 250000.00, 'draft', '', '', NULL, 0),
(48, 24, 1, 'earnings', 500000.00, 'draft', '', '', NULL, 0),
(50, 25, 3, 'earnings', 250000.00, 'revisi', '', '', NULL, 0),
(51, 25, 1, 'earnings', 500000.00, 'revisi', '', '', NULL, 0),
(53, 2, 10, 'earnings', 100000.00, 'revisi', '', '', NULL, 0),
(54, 2, 5, 'earnings', 150000.00, 'revisi', '', '', NULL, 0),
(55, 2, 11, 'deductions', 250000.00, 'revisi', '', '', NULL, 0);

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
-- Dumping data for table `kelebihan_jam_mengajar`
--

INSERT INTO `kelebihan_jam_mengajar` (`id`, `id_anggota`, `bulan`, `tahun`, `minggu_ke`, `jam_extra`, `total_honor`, `updated_at`, `is_final`) VALUES
(232, 32, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(233, 1, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(234, 11, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(235, 3, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(236, 19, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(237, 8, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(238, 20, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(239, 7, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(240, 17, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(241, 21, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(242, 16, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(243, 24, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(244, 6, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(245, 25, 6, 2025, 4, 4.00, 200000.00, '2025-07-04 18:01:47', 1),
(246, 4, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(247, 10, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(248, 5, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(249, 23, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(250, 2, 6, 2025, 4, 4.00, 200000.00, '2025-07-04 18:01:47', 1),
(251, 9, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1),
(252, 22, 6, 2025, 4, 0.00, 0.00, '2025-07-04 18:01:47', 1);

-- --------------------------------------------------------

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
-- Dumping data for table `kenaikan_gaji_tahunan`
--

INSERT INTO `kenaikan_gaji_tahunan` (`id`, `id_anggota`, `nama_kenaikan`, `jumlah`, `tanggal_mulai`, `tanggal_berakhir`, `status`, `ranking_id`, `dibuat_pada`, `pindah_ke_lain_lain`) VALUES
(50, 20, 'Kenaikan Gaji Tahun 2025/2026', 1000000.00, '2025-07-01', '2026-06-30', 'aktif', NULL, '2025-07-02 13:01:25', 0),
(51, 25, 'Kenaikan Gaji Tahun 2025/2026', 1000000.00, '1970-01-01', '1970-12-31', 'aktif', NULL, '2025-07-02 13:10:02', 0),
(55, 24, 'Kenaikan Gaji Tahun 2025/2026', 700000.00, '2025-07-01', '2026-06-30', 'aktif', NULL, '2025-07-04 23:11:41', 0),
(56, 25, 'Kenaikan Gaji Tahun 2025/2026', 900000.00, '2025-07-01', '2026-06-30', 'aktif', NULL, '2025-07-04 23:11:41', 0),
(57, 2, 'Kenaikan Gaji Tahun 2025/2026', 1000000.00, '1970-01-01', '1970-12-31', 'aktif', 1, '2025-07-05 00:59:34', 0),
(58, 25, 'Kenaikan Gaji Tahun 2025/2026', 1000000.00, '2025-06-01', '2026-05-31', 'aktif', 1, '2025-07-05 01:02:03', 0),
(59, 2, 'Kenaikan Gaji Tahun 2025/2026', 1000000.00, '2025-06-01', '2026-05-31', 'aktif', 1, '2025-07-05 01:04:35', 0);

-- --------------------------------------------------------

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
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `salary_index_amount`, `total_pendapatan`, `total_potongan`, `potongan_koperasi`, `potongan_absensi`, `honor_jam_lebih`, `gaji_bersih`, `created_at`, `tgl_payroll`, `no_rekening`, `catatan`, `status`) VALUES
(256, 25, NULL, 5, 2025, 9000000.00, 7000000.00, 0.00, 250000.00, 0.00, 0.00, 0.00, 15750000.00, '2025-07-02 06:10:02', '2025-07-02 13:10:02', '1357924680', '', 'draft'),
(257, 24, NULL, 6, 2025, 5000000.00, 5000000.00, 1250000.00, 400000.00, 0.00, 150000.00, 0.00, 10850000.00, '2025-07-02 06:28:33', '2025-07-02 13:28:33', '1234098765', '', 'draft'),
(258, 25, NULL, 6, 2025, 9000000.00, 7000000.00, 1950000.00, 400000.00, 0.00, 150000.00, 200000.00, 17550000.00, '2025-07-04 17:47:09', '2025-07-05 00:47:09', '1357924680', '', 'draft'),
(260, 2, NULL, 6, 2025, 5000000.00, 5000000.00, 450000.00, 400000.00, 0.00, 150000.00, 200000.00, 10050000.00, '2025-07-04 18:04:35', '2025-07-05 01:04:35', '098765', '', 'draft'),
(261, 2, 62, 6, 2025, 5000000.00, 5000000.00, 250000.00, 250000.00, 50000.00, 150000.00, 200000.00, 10150000.00, '2025-07-04 18:18:08', '2025-07-05 01:17:00', '098765', '', 'final');

-- --------------------------------------------------------

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
-- Dumping data for table `payroll_detail`
--

INSERT INTO `payroll_detail` (`id`, `id_payroll`, `id_anggota`, `id_payhead`, `ranking_id`, `jenis`, `amount`, `ranking_nominal`, `status`) VALUES
(273, 256, 25, 11, NULL, 'deductions', 250000.00, NULL, 'draft'),
(274, 257, 24, 100, NULL, 'earnings', 750000.00, NULL, 'draft'),
(275, 257, 24, 11, NULL, 'deductions', 250000.00, NULL, 'draft'),
(276, 257, 24, 1, NULL, 'earnings', 500000.00, NULL, 'draft'),
(277, 258, 25, 11, NULL, 'deductions', 250000.00, NULL, 'draft'),
(278, 258, 25, 100, NULL, 'earnings', 1000000.00, NULL, 'draft'),
(279, 258, 25, 3, NULL, 'earnings', 250000.00, NULL, 'draft'),
(280, 258, 25, 1, NULL, 'earnings', 500000.00, NULL, 'draft'),
(285, 260, 2, 10, NULL, 'earnings', 100000.00, NULL, 'draft'),
(286, 260, 2, 5, NULL, 'earnings', 150000.00, NULL, 'draft'),
(287, 260, 2, 11, NULL, 'deductions', 250000.00, NULL, 'draft'),
(288, 261, 2, 10, NULL, 'earnings', 100000.00, NULL, 'final'),
(289, 261, 2, 5, NULL, 'earnings', 150000.00, NULL, 'final'),
(290, 261, 2, 11, NULL, 'deductions', 250000.00, NULL, 'final');

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
(101, 21, 10, 'Lembur', 'earnings', 100000.00, 0),
(102, 21, 5, 'Tunjangan Pindah Jenjang', 'earnings', 150000.00, 0),
(103, 21, 11, 'BPJS Ketenagakerjaan', 'deductions', 250000.00, 0);

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
-- Dumping data for table `payroll_final`
--

INSERT INTO `payroll_final` (`id`, `id_anggota`, `id_rekap_absensi`, `bulan`, `tahun`, `gaji_pokok`, `salary_index_amount`, `honor_jam_lebih`, `total_pendapatan`, `total_potongan`, `potongan_koperasi`, `potongan_absensi`, `gaji_bersih`, `tgl_payroll`, `no_rekening`, `catatan`, `finalized_at`, `id_payroll_asal`) VALUES
(21, 2, 62, 6, 2025, 5000000.00, 5000000.00, 200000, 250000.00, 250000.00, 50000.00, 150000.00, 10150000.00, '2025-07-05 01:17:00', '098765', '', '2025-07-04 18:18:08', 261);

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
-- Dumping data for table `rekap_absensi`
--

INSERT INTO `rekap_absensi` (`id`, `id_anggota`, `bulan`, `tahun`, `total_hadir`, `total_izin`, `total_cuti`, `total_tanpa_keterangan`, `total_sakit`) VALUES
(58, 25, 5, 2025, 0, 0, 0, 0, 0),
(59, 24, 6, 2025, 28, 1, 1, 0, 0),
(60, 25, 6, 2025, 28, 1, 1, 0, 0),
(61, 2, 5, 2025, 28, 1, 1, 1, 0),
(62, 2, 6, 2025, 28, 1, 1, 1, 0);

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
-- Dumping data for table `salary_history`
--

INSERT INTO `salary_history` (`id`, `id_anggota`, `jenis`, `amount`, `effective_date`, `created_by`, `created_at`) VALUES
(1, 24, 'increment', 700000.00, '2025-07-04', 'cronjob', '2025-07-04 16:11:01'),
(2, 25, 'increment', 900000.00, '2025-07-04', 'cronjob', '2025-07-04 16:11:01'),
(3, 24, 'increment', 700000.00, '2025-07-04', 'cronjob', '2025-07-04 16:11:41'),
(4, 25, 'increment', 900000.00, '2025-07-04', 'cronjob', '2025-07-04 16:11:41');

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
