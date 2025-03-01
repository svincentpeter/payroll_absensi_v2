<?php
// File: /payroll_absensi_v2/superadmin/backup_database.php

// =========================
// 1. Pengaturan Awal
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
// Hanya superadmin yang boleh akses halaman ini
authorize(['superadmin']);

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ifsnop\Mysqldump\Mysqldump;

// =========================
// 2. Menangani Permintaan Download
// =========================
// Jika ada parameter ?download=1, maka langsung proses backup dan kirim file ke browser
if (isset($_GET['download']) && $_GET['download'] == '1') {
    directDownloadBackup();
    exit;
}

/**
 * Fungsi directDownloadBackup()
 * Melakukan backup database menggunakan library Mysqldump dan langsung mengirim file .sql.gz ke browser.
 * Pendekatan ini menggunakan stream sementara (php://temp) untuk menghindari masalah "Output file is not writable".
 */
function directDownloadBackup()
{
    global $host, $user, $pass, $dbname;
    try {
        // Nama file yang tampak di browser
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';

        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        // Pastikan tidak ada spasi / echo sebelum header!
        if (ob_get_length()) {
            ob_clean();
        }
        flush();

        $dsn = "mysql:host={$host};dbname={$dbname}";
        $dumpOptions = []; // Tanpa compress
        $dump = new Mysqldump($dsn, $user, $pass, $dumpOptions);

        // Dump langsung ke 'php://output'
        $dump->start('php://output');
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo "Backup database gagal: " . $e->getMessage();
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Backup Database - Superadmin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- SB Admin 2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Font Awesome & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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

                <!-- Breadcrumb (jika ada) -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Page Content -->
                <div class="container-fluid">
                    <!-- Judul Halaman -->
                    <h1 class="h3 mb-4 text-gray-800">
                        <i class="fas fa-database me-2"></i>Backup Database
                    </h1>

                    <!-- Card Backup -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <strong><i class="fas fa-cloud-download-alt me-2"></i>Backup Database Sekarang</strong>
                        </div>
                        <div class="card-body">
                            <p>
                                Tekan tombol di bawah ini untuk melakukan backup database.
                                Browser akan menampilkan dialog <em>Save As</em> untuk mengunduh file .sql.gz.
                            </p>
                            <button id="btnBackup" class="btn btn-primary">
                                <i class="fas fa-download"></i> Backup Database
                            </button>
                        </div>
                    </div>
                </div>
                <!-- end .container-fluid -->
            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        $("#btnBackup").click(function() {
            Swal.fire({
                title: 'Backup Database',
                text: "Apakah Anda yakin ingin melakukan backup database?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Backup!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Arahkan browser ke URL download
                    window.location.href = 'backup_database.php?download=1';
                }
            });
        });
    });
    </script>
</body>
</html>
