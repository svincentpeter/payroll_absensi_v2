<?php
// File: /payroll_absensi_v2/koneksi.php

// Sertakan helpers untuk inisialisasi error handling dan fungsi lainnya.
require_once __DIR__ . '/helpers.php';

// Inisialisasi error handling (gunakan secara hati-hati di lingkungan produksi)
init_error_handling();

// Atur timezone sesuai kebutuhan.
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi koneksi database.
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "payroll_ujicoba";

// (Opsional) Aktifkan reporting error mysqli secara lebih ketat untuk pengembangan.
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set charset agar mendukung karakter UTF-8
$conn->set_charset("utf8");

?>
