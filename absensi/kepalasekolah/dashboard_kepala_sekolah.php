<?php
// dashboard_kepala_sekolah.php

// Aktifkan error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../koneksi.php'; // Pastikan path ini benar

// Periksa apakah pengguna sudah login dan role adalah 'kepalasekolah'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kepalasekolah') {
    header("Location: ../../login.php");
    exit();
}

// Ambil data kepala sekolah dari session
$nip = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? 'Kepala Sekolah';

// (Opsional) Jika diperlukan, Anda dapat mengambil data tambahan dari database
// $stmt = $conn->prepare("SELECT * FROM anggota_sekolah WHERE nip = ?");
// $stmt->bind_param("s", $nip);
// $stmt->execute();
// $result = $stmt->get_result();
// $dataKepala = $result->fetch_assoc();
// $stmt->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kepala Sekolah</title>
    <!-- Link CSS SB Admin 2 dan Bootstrap -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .welcome-message {
            margin-bottom: 20px;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include '../../sidebar.php'; ?>
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm" title="Logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Kepala Sekolah</h1>
                    <div class="alert alert-info">
                        Selamat datang Kepala Sekolah
                    </div>
                    <!-- Tambahkan konten dashboard sesuai kebutuhan -->
                    <div class="row">
                        <div class="col-lg-12">
                            <p>Dashboard Kepala Sekolah menampilkan informasi terkini mengenai absensi, jadwal, dan laporan terkait sekolah. Silakan gunakan menu di sidebar untuk mengakses fitur-fitur lainnya.</p>
                        </div>
                    </div>
                </div>
                <!-- End Page Content -->
            </div>
            <!-- End Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; Sistem Nusaputera 2025</span>
                    </div>
                </div>
            </footer>
            <!-- End Footer -->
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript SB Admin 2 & Bootstrap -->
    <script src="../../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../assets/js/sb-admin-2.min.js"></script>
</body>
</html>
