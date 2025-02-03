<?php
// File: /payroll_absensi_v2/absensi/guru/dashboard_guru.php

// =========================
// 1. Pengaturan Keamanan & Session
// =========================

// Atur parameter cookie session sebelum session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => true,      // Hanya lewat HTTPS
    'httponly' => true,      // Tidak dapat diakses via JavaScript
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
generate_csrf_token();

// Buat nonce untuk Content Security Policy (CSP) dan simpan di session
$nonce = base64_encode(random_bytes(16));
$_SESSION['csp_nonce'] = $nonce;

// Paksa penggunaan HTTPS jika belum digunakan
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

// HSTS header
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Proteksi CSRF
generate_csrf_token();

// Role Checking: hanya 'guru' dan 'karyawan' yang boleh mengakses dashboard
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'karyawan'])) {
    header("Location: ../../login.php");
    exit();
}

// Koneksi ke database
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

// Terapkan Content-Security-Policy (CSP) dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net 'nonce-$nonce'; 
    style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.jsdelivr.net 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =========================
// 2. LOGGING & PENGAMBILAN DATA PENGGUNA DAN ABSENSI
// =========================

// Ambil peran dan NIP pengguna dari session
$role = $_SESSION['role'];
$nip = $_SESSION['nip'] ?? '';

if (empty($nip)) {
    echo "NIP tidak ditemukan dalam session.";
    exit();
}

// Catat audit log bahwa dashboard guru diakses
$user_id = $_SESSION['user_id'] ?? 0;
add_audit_log($conn, $user_id, 'AccessDashboardGuru', 'Mengakses dashboard guru.');

// Query untuk mendapatkan nama pengguna dari tabel `anggota_sekolah` berdasarkan NIP
$name_query = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
$stmt_name = $conn->prepare($name_query);
$stmt_name->bind_param("s", $nip);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$nama_pengguna = $result_name->fetch_assoc()['nama'] ?? 'Nama Tidak Diketahui';
$stmt_name->close();

// Tentukan jam masuk standar (misal: 08:00:00)
$jam_masuk_standar = '08:00:00';

// Query untuk mendapatkan data absensi pengguna
// Perbaikan: mengganti kondisi pada "jam_masuk" dengan kondisi pada "scan_masuk" agar konsisten.
$query = "
    SELECT 
        COUNT(*) AS total_records,
        SUM(
            CASE 
                WHEN scan_masuk = '0000-00-00 00:00:00' 
                     AND scan_pulang = '0000-00-00 00:00:00' 
                THEN 1 
                ELSE 0 
            END
        ) AS total_alpha,
        SUM(
            CASE 
                WHEN TIME(scan_masuk) > '$jam_masuk_standar' 
                THEN 1 
                ELSE 0 
            END
        ) AS total_terlambat,
        SUM(
            CASE 
                WHEN jenis_absensi = 'izin' 
                THEN 1 
                ELSE 0 
            END
        ) AS total_izin,
        SUM(
            CASE 
                WHEN scan_masuk != '0000-00-00 00:00:00'
                     AND scan_pulang != '0000-00-00 00:00:00'
                     AND TIME(scan_masuk) <= '$jam_masuk_standar' 
                THEN 1
                ELSE 0 
            END
        ) AS total_hadir
    FROM absensi 
    WHERE nip = ? 
      AND scan_masuk != '0000-00-00 00:00:00'
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();
$attendance_data = $result->fetch_assoc();
$stmt->close();

// Pastikan data absensi tidak null
$total_alpha      = (int)($attendance_data['total_alpha'] ?? 0);
$total_terlambat  = (int)($attendance_data['total_terlambat'] ?? 0);
$total_izin       = (int)($attendance_data['total_izin'] ?? 0);
$total_hadir      = (int)($attendance_data['total_hadir'] ?? 0);

// Konversi data absensi ke format JSON untuk JavaScript
$attendance_json = json_encode([
    'alpha'     => $total_alpha,
    'terlambat' => $total_terlambat,
    'izin'      => $total_izin,
    'hadir'     => $total_hadir
]);

// Tentukan judul dashboard berdasarkan peran
$dashboard_title = ($role === 'guru') ? 'Dashboard Guru' : 'Dashboard Karyawan';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($dashboard_title) ?></title>
    <!-- Font Awesome & SB Admin 2 CSS -->
    <link href="../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <link href="../../assets/css/sb-admin-2.min.css" rel="stylesheet" nonce="<?php echo $nonce; ?>">
    <!-- Chart.js & Plugin Data Labels -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2" nonce="<?php echo $nonce; ?>"></script>
    <style nonce="<?php echo $nonce; ?>">
        /* Styling untuk memperbesar grafik */
        #attendanceChart {
            width: 100% !important;
            height: 600px !important;
        }
        /* Styling tambahan */
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
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a href="../../logout.php" class="btn btn-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Menampilkan nama pengguna yang login dan judul dashboard -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 text-gray-800"><?= htmlspecialchars($dashboard_title) ?></h1>
                            <p class="mb-0">Selamat datang, <strong><?= htmlspecialchars($nama_pengguna) ?></strong></p>
                        </div>
                    </div>
                    
                    <!-- Notifikasi Sukses (jika ada) -->
                    <?php
                    if (isset($_SESSION['success_message'])) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>";
                        echo htmlspecialchars($_SESSION['success_message']);
                        echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                        echo "<span aria-hidden='true'>&times;</span>";
                        echo "</button></div>";
                        unset($_SESSION['success_message']);
                    }
                    ?>

                    <p>Laporan Absensi dan Pengajuan Surat Ijin</p>
                    
                    <!-- Elemen untuk grafik -->
                    <div class="row justify-content-center">
                        <div class="col-lg-12">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- End of Page Content -->
            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?= date("Y"); ?> Payroll Management System</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- JavaScript Bootstrap dan dependencies -->
    <script src="../../assets/vendor/jquery/jquery.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="../../assets/vendor/jquery-easing/jquery.easing.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="../../assets/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    
    <!-- Script untuk Chart.js dengan Data Labels -->
    <script nonce="<?php echo $nonce; ?>">
        // Ambil data absensi dari PHP
        const attendanceData = <?= $attendance_json; ?>;
        
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar', // Tipe grafik (bar, pie, line, dll.)
            data: {
                labels: ['Hadir', 'Terlambat', 'Izin', 'Alpha'],
                datasets: [{
                    label: 'Jumlah Kehadiran',
                    data: [
                        attendanceData.hadir, 
                        attendanceData.terlambat, 
                        attendanceData.izin, 
                        attendanceData.alpha
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.6)',   // Hadir
                        'rgba(255, 206, 86, 0.6)',    // Terlambat
                        'rgba(54, 162, 235, 0.6)',    // Izin
                        'rgba(255, 99, 132, 0.6)'     // Alpha
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#444',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: function(value) {
                            return value;
                        }
                    },
                    title: {
                        display: true,
                        text: 'Statistik Kehadiran'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
