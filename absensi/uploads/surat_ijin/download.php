<?php
// download.php

session_start();
require_once 'koneksi.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['role'])) {
    header("Location: login.php"); // Ganti dengan halaman login Anda
    exit();
}

// Pastikan parameter file ada
if (!isset($_GET['file'])) {
    echo "File tidak ditemukan.";
    exit();
}

$file = basename($_GET['file']); // Ambil nama file saja
$filePath = __DIR__ . '/uploads/surat_ijin/' . $file;

// Periksa apakah file ada
if (!file_exists($filePath)) {
    echo "File tidak ditemukan.";
    exit();
}

// Cek izin akses
// Misalnya, hanya guru dan SDM yang bisa mengakses
if ($_SESSION['role'] !== 'guru' && $_SESSION['role'] !== 'sdm') {
    echo "Anda tidak memiliki izin untuk mengakses file ini.";
    exit();
}

// Mengatur header untuk download
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>
