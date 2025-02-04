<?php
// koneksi.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "payroll_ujicoba";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
