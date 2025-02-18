<?php
// File: /payroll_absensi_v2/absensi/guru/dashboard_guru.php

// =============================================================================
// 1. Pengaturan Keamanan, Session, dan CSP
// =============================================================================
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

// Terapkan Content-Security-Policy (CSP) dengan nonce
header("Content-Security-Policy: default-src 'self'; 
    script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com 'nonce-$nonce'; 
    style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'nonce-$nonce'; 
    img-src 'self'; 
    font-src 'self' https://cdnjs.cloudflare.com; 
    connect-src 'self'");

// =============================================================================
// 2. Otorisasi Akses
// =============================================================================
// Hanya pengguna dengan role 'guru' atau 'karyawan' yang boleh mengakses dashboard ini
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru', 'karyawan'])) {
    header("Location: ../../login.php");
    exit();
}

// =============================================================================
// 3. Koneksi Database & Inisialisasi
// =============================================================================
require_once __DIR__ . '/../../koneksi.php';
if (ob_get_length()) ob_end_clean();

// Ambil data pengguna dari session
$role = $_SESSION['role'];
$nip = $_SESSION['nip'] ?? '';

if (empty($nip)) {
    echo "NIP tidak ditemukan dalam session.";
    exit();
}

// Catat audit log bahwa dashboard guru diakses
$user_id = $_SESSION['user_id'] ?? 0;
add_audit_log($conn, $user_id, 'AccessDashboardGuru', 'Mengakses dashboard guru.');

// Query untuk mendapatkan nama pengguna dari tabel `anggota_sekolah`
$name_query = "SELECT nama FROM anggota_sekolah WHERE nip = ?";
$stmt_name = $conn->prepare($name_query);
$stmt_name->bind_param("s", $nip);
$stmt_name->execute();
$result_name = $stmt_name->get_result();
$nama_pengguna = $result_name->fetch_assoc()['nama'] ?? 'Nama Tidak Diketahui';
$stmt_name->close();

// Tentukan jam masuk standar (misalnya: 08:00:00)
$jam_masuk_standar = '08:00:00';

// Query untuk mendapatkan data absensi pengguna
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
$total_alpha     = (int)($attendance_data['total_alpha'] ?? 0);
$total_terlambat = (int)($attendance_data['total_terlambat'] ?? 0);
$total_izin      = (int)($attendance_data['total_izin'] ?? 0);
$total_hadir     = (int)($attendance_data['total_hadir'] ?? 0);

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
   <!-- Bootstrap 5 CSS dan SB Admin 2 CSS -->
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" nonce="<?php echo $nonce; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/css/sb-admin-2.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Font Awesome untuk icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" nonce="<?php echo $nonce; ?>">
    <!-- Chart.js & Chartjs Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2" nonce="<?php echo $nonce; ?>"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" nonce="<?php echo $nonce; ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" nonce="<?php echo $nonce; ?>"></script>
   <style nonce="<?= htmlspecialchars($nonce); ?>">
        /* Perbesar area grafik */
        #attendanceChart {
            width: 100% !important;
            height: 600px !important;
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
                <!-- Navbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- Breadcrumb -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 text-gray-800"><?= htmlspecialchars($dashboard_title) ?></h1>
                            <p class="mb-0">Selamat datang, <strong><?= htmlspecialchars($nama_pengguna) ?></strong></p>
                        </div>
                    </div>

                    <!-- Tempatkan notifikasi jika ada -->
                    <?php
                    if (isset($_SESSION['success_message'])) {
                        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>"
                             . htmlspecialchars($_SESSION['success_message']) .
                             "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
                        unset($_SESSION['success_message']);
                    }
                    ?>

                    <!-- Area Grafik Absensi -->
                    <div class="row justify-content-center">
                        <div class="col-lg-12">
                            <canvas id="attendanceChart"></canvas>
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
                        <span>&copy; <?= date("Y"); ?> Payroll Management System</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- End Content Wrapper -->
    </div>
    <!-- End Page Wrapper -->

    <!-- JavaScript Dependencies -->
    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js" nonce="<?php echo $nonce; ?>"></script>
    
    <!-- Script untuk Chart.js -->
    <script nonce="<?= htmlspecialchars($nonce); ?>">
        // Data absensi dari PHP
        const attendanceData = <?= $attendance_json; ?>;
        
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar',
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
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)'
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
