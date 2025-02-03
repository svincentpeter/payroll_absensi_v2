<?php
// koneksi.php

$host = "localhost";       // Ganti dengan host database Anda
$user = "root";            // Ganti dengan username database Anda
$pass = "";                // Ganti dengan password database Anda
$dbname = "payroll_ujicoba"; // Ganti dengan nama database Anda

// Membuat koneksi menggunakan mysqli OOP
$conn = new mysqli($host, $user, $pass, $dbname);

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Mengatur karakter set ke UTF-8
$conn->set_charset("utf8");
?>
