<?php
// File: /payroll_absensi_v2/sdm/dashboard_sdm.php

// =========================
// 1. Pengaturan Awal
// =========================
require_once __DIR__ . '/../../helpers.php';
start_session_safe();
init_error_handling();
require_once __DIR__ . '/../../koneksi.php';

// Otorisasi pengguna (hanya role sdm & superadmin yang boleh)
authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

// Pastikan CSRF token telah di-generate
generate_csrf_token();

// =========================
// 2. Tangani Permintaan AJAX
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Verifikasi CSRF Token (jika dikirim)
    if (!isset($_POST['csrf_token'])) {
        send_response(403, 'Token CSRF tidak ditemukan.');
    }
    verify_csrf_token($_POST['csrf_token']);

    // (Opsional) Cek ulang otorisasi jika diperlukan
    authorize(['sdm', 'superadmin'], '/payroll_absensi_v2/login.php');

    $action = $_POST['action'];

    if ($action === 'get_data' && isset($_POST['id_anggota'])) {
        // Mengambil Data Absensi untuk Anggota Tertentu
        $id_anggota = intval($_POST['id_anggota']);
        if ($id_anggota <= 0) {
            send_response(1, 'ID anggota tidak valid.');
        }

        $query = "SELECT status_kehadiran, terlambat, tanggal FROM absensi WHERE id_anggota = ? ORDER BY tanggal ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            send_response(1, 'Gagal menyiapkan query: ' . $conn->error);
        }
        $stmt->bind_param("i", $id_anggota);
        $stmt->execute();
        $result = $stmt->get_result();

        $attendance_records = [];
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        $stmt->close();

        // Inisialisasi kategori status
        $status_categories = ['hadir', 'terlambat', 'izin', 'sakit', 'cuti', 'tanpa_keterangan', 'libur'];
        $monthly_data = []; // Format: ['YYYY-MM' => ['hadir'=>x, 'terlambat'=>y, ...], ...]
        foreach ($attendance_records as $record) {
            $tanggal = $record['tanggal'];
            $status = strtolower($record['status_kehadiran']);
            $terlambat = intval($record['terlambat']);
            $month = date('Y-m', strtotime($tanggal));
            if (!isset($monthly_data[$month])) {
                foreach ($status_categories as $cat) {
                    $monthly_data[$month][$cat] = 0;
                }
            }
            if ($status === 'hadir') {
                if ($terlambat === 1) {
                    $monthly_data[$month]['terlambat'] += 1;
                } else {
                    $monthly_data[$month]['hadir'] += 1;
                }
            } elseif (in_array($status, $status_categories)) {
                $monthly_data[$month][$status] += 1;
            }
        }

        // Siapkan data untuk Chart.js
        $labels = [];
        $data_hadir = [];
        $data_terlambat = [];
        $data_izin = [];
        $data_sakit = [];
        $data_cuti = [];
        $data_tanpa_keterangan = [];
        $data_libur = [];
        ksort($monthly_data);
        foreach ($monthly_data as $month => $data) {
            $labels[] = $month;
            $data_hadir[] = $data['hadir'];
            $data_terlambat[] = $data['terlambat'];
            $data_izin[] = $data['izin'];
            $data_sakit[] = $data['sakit'];
            $data_cuti[] = $data['cuti'];
            $data_tanpa_keterangan[] = $data['tanpa_keterangan'];
            $data_libur[] = $data['libur'];
        }
        // Data untuk grafik pie
        $total_hadir = array_sum($data_hadir);
        $total_terlambat = array_sum($data_terlambat);
        $total_izin = array_sum($data_izin);
        $total_sakit = array_sum($data_sakit);
        $total_cuti = array_sum($data_cuti);
        $total_tanpa_keterangan = array_sum($data_tanpa_keterangan);
        $total_libur = array_sum($data_libur);
        $pie_data = [
            'hadir' => $total_hadir,
            'terlambat' => $total_terlambat,
            'izin' => $total_izin,
            'sakit' => $total_sakit,
            'cuti' => $total_cuti,
            'tanpa_keterangan' => $total_tanpa_keterangan,
            'libur' => $total_libur
        ];

        // Catat audit log
        $user_nip = $_SESSION['nip'] ?? '';
        $details_log = "Mengambil data absensi untuk anggota dengan NIP: $user_nip, ID anggota: $id_anggota.";
        add_audit_log($conn, $user_nip, 'GetAttendanceData', $details_log);

        send_response(0, [
            'monthly_labels' => $labels,
            'hadir' => $data_hadir,
            'terlambat' => $data_terlambat,
            'izin' => $data_izin,
            'sakit' => $data_sakit,
            'cuti' => $data_cuti,
            'tanpa_keterangan' => $data_tanpa_keterangan,
            'libur' => $data_libur,
            'pie_data' => $pie_data
        ]);

    } elseif ($action === 'get_performance') {
        // Mengambil data performa untuk semua anggota
        $query = "SELECT id, nama, nip FROM anggota_sekolah ORDER BY nama ASC";
        $result = $conn->query($query);
        $anggota = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $anggota[] = $row;
            }
        }
        $performance_data = [];
        foreach ($anggota as $a) {
            $id = intval($a['id']);
            $nama = $a['nama'];
            $nip = $a['nip'];
            $query_absensi = "SELECT 
                                SUM(CASE WHEN status_kehadiran = 'hadir' AND terlambat = 0 THEN 1 ELSE 0 END) AS total_hadir,
                                SUM(CASE WHEN status_kehadiran = 'hadir' AND terlambat = 1 THEN 1 ELSE 0 END) AS total_terlambat,
                                SUM(CASE WHEN status_kehadiran = 'izin' THEN 1 ELSE 0 END) AS total_izin,
                                SUM(CASE WHEN status_kehadiran = 'sakit' THEN 1 ELSE 0 END) AS total_sakit,
                                SUM(CASE WHEN status_kehadiran = 'cuti' THEN 1 ELSE 0 END) AS total_cuti,
                                SUM(CASE WHEN status_kehadiran = 'tanpa_keterangan' THEN 1 ELSE 0 END) AS total_tanpa_keterangan,
                                SUM(CASE WHEN status_kehadiran = 'libur' THEN 1 ELSE 0 END) AS total_libur
                              FROM absensi 
                              WHERE id_anggota = ?";
            $stmt_absensi = $conn->prepare($query_absensi);
            if (!$stmt_absensi) {
                continue;
            }
            $stmt_absensi->bind_param("i", $id);
            $stmt_absensi->execute();
            $result_absensi = $stmt_absensi->get_result();
            $absensi = $result_absensi->fetch_assoc();
            $stmt_absensi->close();

            // Hitung skor performa (misalnya: total_hadir - (total_terlambat + total_izin))
            $score = $absensi['total_hadir'] - ($absensi['total_terlambat'] + $absensi['total_izin']);
            $performance_data[] = [
                'id' => $id,
                'nama' => $nama,
                'nip' => $nip,
                'score' => $score
            ];
        }
        // Urutkan data performa (terbaik ke terburuk)
        usort($performance_data, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        $top_performers = array_slice($performance_data, 0, 3);
        $bottom_performers = array_slice($performance_data, -3, 3);
        $selected_performers = array_merge($top_performers, $bottom_performers);
        $labels = [];
        $scores = [];
        foreach ($selected_performers as $p) {
            $labels[] = $p['nama'] . " (" . $p['nip'] . ")";
            $scores[] = $p['score'];
        }
        // Catat audit log
        $user_nip = $_SESSION['nip'] ?? '';
        $details_log = "Mengambil data performa untuk semua anggota oleh pengguna dengan NIP $user_nip.";
        add_audit_log($conn, $user_nip, 'GetPerformanceData', $details_log);
        send_response(0, [
            'labels' => $labels,
            'scores' => $scores
        ]);
    } else {
        send_response(404, 'Aksi tidak dikenali.');
    }
    exit();
}

// =========================
// 3. Render Halaman HTML (bukan AJAX)
// =========================

// Ambil data anggota (guru/karyawan) untuk dropdown filter
$query = "SELECT id, nama, nip, job_title FROM anggota_sekolah ORDER BY nama ASC";
$result = $conn->query($query);
$anggota = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $anggota[] = $row;
    }
}

// Jika menggunakan nonce untuk CSP, pastikan variabel $nonce sudah didefinisikan, misalnya:
$nonce = bin2hex(random_bytes(8));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard SDM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
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
    <style nonce="<?php echo $nonce; ?>">
        .chart-container {
            position: relative;
            width: 100%;
            height: 400px;
        }
        .card-body {
            overflow: hidden;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <!-- End Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include __DIR__ . '/../../navbar.php'; ?>
                <!-- End Topbar -->

                <!-- Breadcrumb -->
                <?php include __DIR__ . '/../../breadcrumb.php'; ?>

                <!-- Main Container -->
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-users me-2"></i>Dashboard SDM</h1>

                    <!-- Card Filter -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <strong><i class="fas fa-filter me-1"></i>Filter Absensi</strong>
                        </div>
                        <div class="card-body">
                            <form id="filterForm" class="row align-items-end">
                                <div class="col-md-6 mb-2">
                                    <label for="anggotaSelect" class="form-label">
                                        <i class="fas fa-user me-1"></i>Pilih Guru/Karyawan
                                    </label>
                                    <select id="anggotaSelect" class="form-select" required>
                                        <option value="" disabled selected>-- Pilih Guru/Karyawan --</option>
                                        <?php foreach ($anggota as $a): ?>
                                            <option value="<?= htmlspecialchars($a['id']); ?>">
                                                <?= htmlspecialchars($a['nama']) . " (" . htmlspecialchars($a['nip']) . ")"; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <button type="button" id="resetFilter" class="btn btn-secondary w-100">
                                        <i class="fas fa-undo me-1"></i>Reset Filter
                                    </button>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <button type="button" id="viewPerformance" class="btn btn-info w-100">
                                        <i class="fas fa-chart-bar me-1"></i>Lihat Performa Guru/Karyawan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Grafik Absensi -->
                    <div class="row" id="attendanceChartsContainer">
                        <!-- Bar Chart -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <strong><i class="fas fa-chart-bar me-1"></i>Grafik Absensi Bulanan</strong>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="barChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Line Chart -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <strong><i class="fas fa-chart-line me-1"></i>Grafik Tren Absensi</strong>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="lineChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Pie -->
                    <div class="row" id="pieChartContainer">
                        <div class="col-lg-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <strong><i class="fas fa-chart-pie me-1"></i>Grafik Proporsi Absensi</strong>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="pieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Performa -->
                    <div class="row" id="performanceChartContainer" style="display: none;">
                        <div class="col-lg-12 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <strong><i class="fas fa-trophy me-1"></i>Grafik Performa Terbaik & Terburuk</strong>
                                    <!-- Dropdown aksi -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm" type="button" id="performanceActions" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="performanceActions">
                                            <li>
                                                <a class="dropdown-item" href="#" id="refreshPerformance">
                                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="performanceChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- end .container-fluid -->
            </div> <!-- end #content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date("Y"); ?> Payroll Management System | Developed By [Nama Anda]</span>
                    </div>
                </div>
            </footer>
        </div> <!-- End content-wrapper -->
    </div> <!-- End wrapper -->

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.3/js/sb-admin-2.min.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
    $(document).ready(function() {
        // Inisialisasi SweetAlert2 Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        function showToast(message, icon = 'success') {
            Toast.fire({
                icon: icon,
                title: message
            });
        }

        let barChart, lineChart, pieChart, performanceChart;

        function renderAttendanceCharts(data) {
            const labels = data.monthly_labels;
            const hadir = data.hadir;
            const terlambat = data.terlambat;
            const izin = data.izin;
            const sakit = data.sakit;
            const cuti = data.cuti;
            const tanpa_keterangan = data.tanpa_keterangan;
            const libur = data.libur;
            const pieData = data.pie_data;

            if (barChart) barChart.destroy();
            if (lineChart) lineChart.destroy();
            if (pieChart) pieChart.destroy();

            const ctxBar = document.getElementById('barChart').getContext('2d');
            barChart = new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Hadir',
                            data: hadir,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Terlambat',
                            data: terlambat,
                            backgroundColor: 'rgba(255, 206, 86, 0.6)',
                            borderColor: 'rgba(255, 206, 86, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Izin',
                            data: izin,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Sakit',
                            data: sakit,
                            backgroundColor: 'rgba(153, 102, 255, 0.6)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Cuti',
                            data: cuti,
                            backgroundColor: 'rgba(255, 159, 64, 0.6)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Tanpa Keterangan',
                            data: tanpa_keterangan,
                            backgroundColor: 'rgba(201, 203, 207, 0.6)',
                            borderColor: 'rgba(201, 203, 207, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Libur',
                            data: libur,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Absensi Bulanan' }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });

            const ctxLine = document.getElementById('lineChart').getContext('2d');
            lineChart = new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Hadir',
                            data: hadir,
                            fill: false,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            tension: 0.1
                        },
                        {
                            label: 'Terlambat',
                            data: terlambat,
                            fill: false,
                            borderColor: 'rgba(255, 206, 86, 1)',
                            tension: 0.1
                        },
                        {
                            label: 'Izin',
                            data: izin,
                            fill: false,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            tension: 0.1
                        },
                        {
                            label: 'Sakit',
                            data: sakit,
                            fill: false,
                            borderColor: 'rgba(153, 102, 255, 1)',
                            tension: 0.1
                        },
                        {
                            label: 'Cuti',
                            data: cuti,
                            fill: false,
                            borderColor: 'rgba(255, 159, 64, 1)',
                            tension: 0.1
                        },
                        {
                            label: 'Tanpa Keterangan',
                            data: tanpa_keterangan,
                            fill: false,
                            borderColor: 'rgba(201, 203, 207, 1)',
                            tension: 0.1
                        },
                        {
                            label: 'Libur',
                            data: libur,
                            fill: false,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Tren Absensi' }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });

            const ctxPie = document.getElementById('pieChart').getContext('2d');
            pieChart = new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Cuti', 'Tanpa Keterangan', 'Libur'],
                    datasets: [{
                        label: 'Proporsi Absensi',
                        data: [pieData.hadir, pieData.terlambat, pieData.izin, pieData.sakit, pieData.cuti, pieData.tanpa_keterangan, pieData.libur],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(153, 102, 255, 0.6)',
                            'rgba(255, 159, 64, 0.6)',
                            'rgba(201, 203, 207, 0.6)',
                            'rgba(255, 99, 132, 0.6)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(201, 203, 207, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Proporsi Absensi' }
                    }
                }
            });
        }

        function renderPerformanceChart(data) {
            const labels = data.labels;
            const scores = data.scores;
            if (performanceChart) performanceChart.destroy();
            const ctxPerformance = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(ctxPerformance, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Skor Performa',
                        data: scores,
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 99, 132, 0.6)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Performa Terbaik dan Terburuk (Top 3 & Bottom 3)' }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Skor' } }
                    }
                }
            });
        }

        function fetchAttendanceData(id_anggota) {
            $.ajax({
                url: 'dashboard_sdm.php',
                method: 'POST',
                data: { 
                    action: 'get_data',
                    id_anggota: id_anggota,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.code !== 0) {
                        showToast(response.result, 'error');
                        return;
                    }
                    $('#attendanceChartsContainer').show();
                    $('#pieChartContainer').show();
                    $('#performanceChartContainer').hide();
                    renderAttendanceCharts(response.result);
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    showToast('Terjadi kesalahan saat mengambil data absensi.', 'error');
                }
            });
        }

        function fetchPerformanceData() {
            $.ajax({
                url: 'dashboard_sdm.php',
                method: 'POST',
                data: { 
                    action: 'get_performance',
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.code !== 0) {
                        showToast(response.result, 'error');
                        return;
                    }
                    $('#attendanceChartsContainer').hide();
                    $('#pieChartContainer').hide();
                    $('#performanceChartContainer').show();
                    renderPerformanceChart(response.result);
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    showToast('Terjadi kesalahan saat mengambil data performa.', 'error');
                }
            });
        }

        function resetCharts() {
            if (barChart) barChart.destroy();
            if (lineChart) lineChart.destroy();
            if (pieChart) pieChart.destroy();
            if (performanceChart) performanceChart.destroy();
            $('#attendanceChartsContainer').hide();
            $('#pieChartContainer').hide();
            $('#performanceChartContainer').hide();
        }

        $('#anggotaSelect').on('change', function() {
            const id_anggota = $(this).val();
            if (id_anggota) {
                fetchAttendanceData(id_anggota);
            } else {
                resetCharts();
            }
        });

        $('#resetFilter').on('click', function() {
            $('#anggotaSelect').val('');
            resetCharts();
            showToast('Filter direset.', 'info');
        });

        $('#viewPerformance').on('click', function(e) {
            e.preventDefault();
            fetchPerformanceData();
        });

        $('#refreshPerformance').on('click', function(e) {
            e.preventDefault();
            fetchPerformanceData();
        });

        // Inisialisasi: sembunyikan semua grafik pada awalnya
        resetCharts();
    });
    </script>
</body>
</html>
