<?php
// File: /payroll_absensi_v2/payroll/keuangan/error_log.php

require_once __DIR__ . '/../helpers.php';
// Proteksi CSRF
generate_csrf_token();

// Pastikan hanya superadmin yang dapat mengakses halaman ini
authorize('M:superadmin', '/payroll_absensi_v2/login.php');

// Koneksi ke database
require_once __DIR__ . '/../koneksi.php';
if (ob_get_length()) {
    ob_end_clean();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Error Log Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5.3.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        .card {
            margin: 20px auto;
            width: 95%;       /* Lebar card lebih lebar */
            max-width: 1200px; /* Max-width ditingkatkan */
        }
        pre {
            background-color: #f8f9fc;
            padding: 20px;
            border-radius: 6px;
            max-height: 700px;
            overflow: auto;
            font-size: 0.95rem;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../navbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 text-gray-800">
                            <i class="fas fa-exclamation-triangle"></i> Error Log Viewer
                        </h1>
                    </div>
                    
                    <!-- Card untuk menampilkan error.log -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">Error Log</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Tentukan path ke file error.log
                            $file = __DIR__ . '/../error.log';
                            if (file_exists($file)) {
                                $logContent = file_get_contents($file);
                                if (trim($logContent) === '') {
                                    echo '<div class="alert alert-info">File error.log kosong. Tidak ada kesalahan yang tercatat.</div>';
                                } else {
                                    // Tampilkan isi file dengan mengamankan output
                                    echo '<pre>' . htmlspecialchars($logContent) . '</pre>';
                                }
                            } else {
                                echo '<div class="alert alert-warning">File error.log tidak ditemukan.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Sistem Gaji Nusaputera</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
</body>
</html>
