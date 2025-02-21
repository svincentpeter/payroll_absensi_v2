<?php
// File: /payroll_absensi_v2/absensi/kepalasekolah/dashboard_kepala_sekolah.php

// Inisiasi session secara aman, buat CSRF token, dan batasi akses hanya untuk kepala sekolah
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
generate_csrf_token();
authorize('kepala_sekolah'); // Pastikan role yang diizinkan 'kepala_sekolah'

// Koneksi database
require_once __DIR__ . '/../../koneksi.php';

// Ambil data kepala sekolah dari session
$nip  = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? 'Kepala Sekolah';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kepala Sekolah</title>
    <!-- FontAwesome, Bootstrap v5.3.3, dan SB Admin 2 CSS via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS (pastikan kompatibel dengan Bootstrap 5) -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" rel="stylesheet">

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
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>
                <!-- End of Breadcrumb -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Kepala Sekolah</h1>
                    <div class="alert alert-info">
                        Selamat datang, <?= htmlspecialchars($nama) ?>!
                    </div>
                    <!-- Konten Dashboard -->
                    <div class="row">
                        <div class="col-lg-12">
                            <p>
                                Dashboard Kepala Sekolah menampilkan informasi terkini mengenai absensi, jadwal,
                                dan laporan terkait sekolah. Silakan gunakan menu di sidebar untuk mengakses fitur-fitur lainnya.
                            </p>
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

    <!-- JavaScript: jQuery, Bootstrap v5.3.3, dan SB Admin 2 JS via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>
</body>
</html>
