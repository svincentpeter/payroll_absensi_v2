<?php
// File: superadmin/dashboard_superadmin.php

// Mulai session, inisialisasi error handling, dan CSRF token menggunakan helpers.php
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk Content-Security-Policy dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';

// Gunakan fungsi authorize() dari helpers.php untuk memastikan hanya superadmin yang bisa akses
authorize('superadmin', '/payroll_absensi_v2/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Superadmin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SB Admin 2 CSS dari CDN -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
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

                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Dashboard Superadmin</h1>
                    
                    <!-- Tampilkan notifikasi (jika ada) -->
                    <?php if (isset($_SESSION['notif_success'])): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($_SESSION['notif_success']); ?>
                        </div>
                        <?php unset($_SESSION['notif_success']); ?>
                    <?php endif; ?>
                    
                    <!-- Isi Konten -->
                    <p>Selamat datang, Superadmin! Anda dapat mengakses seluruh sistem.</p>

                    <div class="row">
                        <div class="col-md-6">
                            <!-- Gunakan kelas border-start-primary (Bootstrap 5) -->
                            <div class="card border-start-primary mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Dashboard Keuangan</h5>
                                    <p class="card-text">
                                        Lihat rekap gaji final, pemotongan manual, dsb.
                                    </p>
                                    <a href="payroll/superadmin/dashboard_superadmin.php" class="btn btn-primary">
                                        Akses Dashboard Keuangan
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Gunakan kelas border-start-success (Bootstrap 5) -->
                            <div class="card border-start-success mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Dashboard SDM</h5>
                                    <p class="card-text">
                                        Kelola absensi, upload excel, dsb.
                                    </p>
                                    <a href="../sdm/dashboard_sdm.php" class="btn btn-success">
                                        Akses Dashboard SDM
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div> <!-- end container-fluid -->
            </div> <!-- end content -->
        </div> <!-- end content-wrapper -->
    </div> <!-- end wrapper -->

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery.easing@1.4.1/jquery.easing.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js"></script>
</body>
</html>
