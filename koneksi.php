<?php
// File: /payroll_absensi_v2/koneksi.php

// Sertakan helpers untuk inisialisasi error handling dan fungsi lainnya.
require_once __DIR__ . '/helpers.php';
init_error_handling();

// Atur timezone sesuai kebutuhan.
date_default_timezone_set('Asia/Jakarta');

$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "payroll_ujicoba";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8");

?>
